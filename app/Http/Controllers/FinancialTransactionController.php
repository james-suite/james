<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\TransactionStatus;
use App\Http\Requests\StoreFinancialTransactionRequest;
use App\Http\Requests\StoreFinancialTransferRequest;
use App\Http\Requests\StoreNfceImportRequest;
use App\Http\Requests\UpdateFinancialTransactionRequest;
use App\Http\Requests\UpdateFinancialTransferRequest;
use App\Jobs\ScrapeNfceInvoiceJob;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\FinancialTransactionItem;
use App\Models\SettlementGroup;
use App\Services\Nfce\Exceptions\InvalidNfceUrlException;
use App\Services\Nfce\Exceptions\UnsupportedNfceProviderException;
use App\Services\Nfce\NfceSourceResolver;
use App\Traits\HandlesAttachments;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinancialTransactionController extends Controller
{
    use HandlesAttachments;

    public function index(Request $request): View
    {
        $query = FinancialTransaction::query()->with(['account', 'invoice.creditCard', 'media', 'tags']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->search($request->search, ['description'])
                    ->orWhereHas('items', function ($q2) use ($request) {
                        $q2->whereRaw('unaccent(description) ILIKE unaccent(?)', ["%{$request->search}%"]);
                    });
            });
        }

        if ($request->filled('account_id')) {
            $query->where('financial_account_id', $request->account_id);
        }

        if ($request->filled('credit_card_invoice_id')) {
            $query->where('financial_credit_card_invoice_id', $request->credit_card_invoice_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('date_start')) {
            $query->whereDate('date', '>=', $request->date_start);
        }

        if ($request->filled('date_end')) {
            $query->whereDate('date', '<=', $request->date_end);
        }

        if ($request->filled('tag_id')) {
            $query->where(function ($query) use ($request) {
                $query->whereHas('tags', function ($tagQuery) use ($request) {
                    $tagQuery->whereKey($request->tag_id);
                })->orWhereHas('items.tags', function ($tagQuery) use ($request) {
                    $tagQuery->whereKey($request->tag_id);
                });
            });
        }

        $transactions = $query->orderBy('date', 'desc')->orderBy('updated_at', 'desc')->paginate(25)->withQueryString();

        $accounts = FinancialAccount::orderBy('name')->get();
        $tags = FinancialTag::orderBy('name')->get();
        $hasTrashed = FinancialTransaction::onlyTrashed()->exists();

        return view('finance.transactions.index', compact('transactions', 'accounts', 'tags', 'hasTrashed'));
    }

    public function create(): View
    {
        $accounts = FinancialAccount::orderBy('name')->get();
        $cards = FinancialCreditCard::orderBy('name')->get();
        $tags = FinancialTag::orderBy('name')->get()->map(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'color_hex' => $tag->color_hex,
                'svg' => Blade::render('<x-dynamic-component :component="$icon" class="size-3.5" />', ['icon' => $tag->icon]),
            ];
        });

        return view('finance.transactions.create', compact('accounts', 'cards', 'tags'));
    }

    public function store(StoreFinancialTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $hasItems = ! empty($validated['items']);

        DB::transaction(function () use ($validated, $hasItems): void {
            $mode = $validated['mode'];

            if ($mode === 'single') {
                if (! empty($validated['financial_credit_card_id'])) {
                    $card = FinancialCreditCard::findOrFail($validated['financial_credit_card_id']);
                    $date = Carbon::parse($validated['date']);
                    $invoice = FinancialCreditCardInvoice::resolveForDate($card, $date);

                    $transaction = $invoice->transactions()->create([
                        'type' => $validated['type'],
                        'amount' => $validated['amount'],
                        'description' => $validated['description'],
                        'date' => $date,
                        'status' => TransactionStatus::Pending,
                    ]);
                } else {
                    $transaction = FinancialTransaction::create([
                        'financial_account_id' => $validated['financial_account_id'],
                        'type' => $validated['type'],
                        'amount' => $validated['amount'],
                        'description' => $validated['description'],
                        'date' => Carbon::parse($validated['date']),
                        'status' => $validated['status'] ?? TransactionStatus::Posted,
                    ]);
                }

                $globalTags = $validated['tags'] ?? [];
                $globalPrimaryId = $hasItems ? null : ($validated['primary_tag_id'] ?? null);
                $this->syncTagsWithPrimary($transaction, $globalTags, $globalPrimaryId);

                if (! empty($validated['items'])) {
                    foreach ($validated['items'] as $itemData) {
                        $item = $transaction->items()->create([
                            'description' => $itemData['description'],
                            'quantity' => $itemData['quantity'],
                            'unit_price' => $itemData['unit_price'],
                            'total' => $itemData['quantity'] * $itemData['unit_price'],
                        ]);
                        $this->syncTagsWithPrimary($item, $itemData['tags'] ?? [], $itemData['primary_tag_id'] ?? null);
                    }
                }
            } elseif ($mode === 'installment') {
                if (! empty($validated['financial_credit_card_id'])) {
                    $card = FinancialCreditCard::findOrFail($validated['financial_credit_card_id']);
                    $transactions = $card->createInstallmentPurchase(
                        Carbon::parse($validated['date']),
                        $validated['amount'],
                        $validated['installments'],
                        $validated['description']
                    );
                } else {
                    $account = FinancialAccount::findOrFail($validated['financial_account_id']);
                    $transactions = FinancialTransaction::createInstallmentsOnAccount(
                        $account,
                        Carbon::parse($validated['date']),
                        $validated['amount'],
                        $validated['installments'],
                        $validated['description'],
                        $validated['type']
                    );
                }

                $globalTags = $validated['tags'] ?? [];
                $globalPrimaryId = $hasItems ? null : ($validated['primary_tag_id'] ?? null);

                if (! empty($validated['tags']) || ! empty($validated['items'])) {
                    foreach ($transactions as $t) {
                        $this->syncTagsWithPrimary($t, $globalTags, $globalPrimaryId);

                        if (! empty($validated['items'])) {
                            $installmentItemsTotalCents = 0;

                            foreach ($validated['items'] as $itemData) {
                                $originalUnitPriceCents = (int) round($itemData['unit_price'] * 100);
                                $unitPriceInstallmentCents = (int) floor($originalUnitPriceCents / $validated['installments']);

                                $isLastInstallment = $t->installment_current === $t->installment_total;
                                if ($isLastInstallment) {
                                    $unitPriceRemainderCents = $originalUnitPriceCents - ($unitPriceInstallmentCents * $validated['installments']);
                                    $unitPriceInstallmentCents += $unitPriceRemainderCents;
                                }

                                $currentUnitPrice = round($unitPriceInstallmentCents / 100, 2);
                                $currentItemTotal = round($itemData['quantity'] * $currentUnitPrice, 2);
                                $installmentItemsTotalCents += (int) round($currentItemTotal * 100);

                                $item = $t->items()->create([
                                    'description' => $itemData['description'],
                                    'quantity' => $itemData['quantity'],
                                    'unit_price' => $currentUnitPrice,
                                    'total' => $currentItemTotal,
                                ]);
                                $this->syncTagsWithPrimary($item, $itemData['tags'] ?? [], $itemData['primary_tag_id'] ?? null);
                            }

                            $t->update(['amount' => round($installmentItemsTotalCents / 100, 2)]);
                        }
                    }
                }

                $firstTransaction = $transactions->first();
                if ($firstTransaction) {
                    $this->syncAttachments($firstTransaction, $validated);
                }
            }

            if ($mode === 'single' && isset($transaction)) {
                $this->syncAttachments($transaction, $validated);
            }
        });

        return redirect()->route('financial.transactions.index')->with('success', 'Transação criada com sucesso.');
    }

    public function importNfce(StoreNfceImportRequest $request, NfceSourceResolver $sourceResolver): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $source = $sourceResolver->resolve($validated['url']);
        } catch (InvalidNfceUrlException|UnsupportedNfceProviderException $exception) {
            return back()->withErrors(['url' => $exception->getMessage()])->withInput();
        }

        if (FinancialTransaction::withTrashed()->where('nfce_access_key', $source->accessKey)->exists()) {
            return back()->withErrors(['url' => 'Esta NFC-e já foi importada.'])->withInput();
        }

        ScrapeNfceInvoiceJob::dispatch(
            requesterId: $request->user()->id,
            provider: $source->provider,
            accessKey: $source->accessKey,
            uf: $source->uf,
            sourceEndpoint: $source->sourceEndpoint,
            requestParameterSuffix: $source->requestParameterSuffix,
        );

        return redirect()->route('financial.transactions.index')
            ->with('success', 'Importação enviada para processamento. Você será notificado quando terminar.');
    }

    public function retryNfceImport(Request $request): RedirectResponse
    {
        $payload = $this->retryNfcePayload($request);

        abort_unless($payload['requester_id'] === $request->user()->id, 403);

        if (FinancialTransaction::withTrashed()->where('nfce_access_key', $payload['access_key'])->exists()) {
            return redirect()->route('notifications.index')
                ->with('success', 'Esta NFC-e já foi importada.');
        }

        ScrapeNfceInvoiceJob::dispatch(
            requesterId: $payload['requester_id'],
            provider: $payload['provider'],
            accessKey: $payload['access_key'],
            uf: $payload['uf'],
            sourceEndpoint: $payload['source_endpoint'],
            requestParameterSuffix: $payload['request_parameter_suffix'],
        );

        return redirect()->route('notifications.index')
            ->with('success', 'Importação reenviada para processamento. Você será notificado quando terminar.');
    }

    public function storeTransfer(StoreFinancialTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $from = FinancialAccount::findOrFail($validated['from_account_id']);
        $to = FinancialAccount::findOrFail($validated['to_account_id']);

        FinancialTransaction::createTransfer(
            $from,
            $to,
            $validated['amount'],
            Carbon::parse($validated['date']),
            $validated['description'],
            $validated['fee_amount'] ?? null,
            ! empty($validated['fee_amount']) ? ($validated['fee_tag_id'] ?? FinancialTag::JUROS_ID) : null
        );

        return redirect()->route('financial.transactions.index')->with('success', 'Transferência criada com sucesso.');
    }

    public function show(FinancialTransaction $transaction): View
    {
        $transaction->load(['account', 'invoice.creditCard', 'tags', 'items.tags', 'settlements']);

        $settlementGroup = SettlementGroup::where('financial_transaction_id', $transaction->id)->first();

        $isSettlementTransaction = $settlementGroup || $transaction->settlements->isNotEmpty();

        $editRoute = route('financial.transactions.edit', $transaction->id);
        if ($settlementGroup) {
            $editRoute = route('settlements.groups.edit', $settlementGroup);
        } elseif ($transaction->settlements->isNotEmpty()) {
            $editRoute = route('settlements.edit', $transaction->settlements->first());
        } elseif ($transaction->transfer_pair_id && $transaction->transferPair) {
            $editRoute = route('financial.transactions.transfer.edit', $transaction->id);
        }

        return view('finance.transactions.show', compact('transaction', 'settlementGroup', 'isSettlementTransaction', 'editRoute'));
    }

    public function edit(FinancialTransaction $transaction): View
    {
        abort_if($transaction->transfer_pair_id, 404, 'Transferências devem ser editadas pelo formulário de transferência.');

        $transaction->load(['tags', 'items.tags', 'invoice.creditCard']);
        $accounts = FinancialAccount::orderBy('name')->get();
        $cards = FinancialCreditCard::orderBy('name')->get();
        $tags = FinancialTag::orderBy('name')->get()->map(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'color_hex' => $tag->color_hex,
                'svg' => Blade::render('<x-dynamic-component :component="$icon" class="size-3.5" />', ['icon' => $tag->icon]),
            ];
        });

        return view('finance.transactions.edit', compact('transaction', 'accounts', 'cards', 'tags'));
    }

    public function editTransfer(FinancialTransaction $transaction): View
    {
        [$expense, $income] = $this->transferEntries($transaction);
        $fee = $this->findTransferFee($expense);
        $feeTagId = $fee?->tags()->first()?->id;
        $accounts = FinancialAccount::orderBy('name')->get();
        $feeTags = FinancialTag::query()
            ->where('id', '!=', FinancialTag::TRANSFERENCIA_ID)
            ->orderBy('name')
            ->get(['id', 'name']);
        $isPosted = old('status', $expense->status?->value) === TransactionStatus::Posted->value;

        return view('finance.transactions.transfer-edit', compact(
            'expense',
            'income',
            'fee',
            'feeTagId',
            'accounts',
            'feeTags',
            'isPosted',
        ));
    }

    public function updateTransfer(UpdateFinancialTransferRequest $request, FinancialTransaction $transaction): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $transaction): void {
            $lockedTransaction = FinancialTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            [$expense, $income] = $this->transferEntries($lockedTransaction);
            $fee = $this->findTransferFee($expense);
            $date = Carbon::parse($validated['date']);
            $status = $validated['status'] ?? $expense->status?->value;
            $description = $validated['description'];

            $expense->update([
                'financial_account_id' => $validated['from_account_id'],
                'type' => 'expense',
                'amount' => $validated['amount'],
                'description' => $description,
                'date' => $date,
                'status' => $status,
            ]);
            $income->update([
                'financial_account_id' => $validated['to_account_id'],
                'type' => 'income',
                'amount' => $validated['amount'],
                'description' => $description,
                'date' => $date,
                'status' => $status,
            ]);

            if (! empty($validated['fee_amount'])) {
                $fee ??= new FinancialTransaction;
                $fee->fill([
                    'financial_account_id' => $validated['from_account_id'],
                    'type' => 'expense',
                    'amount' => $validated['fee_amount'],
                    'description' => 'Taxa/imposto — '.$description,
                    'date' => $date,
                    'status' => $status,
                ]);
                $fee->save();
                $feeTagId = $validated['fee_tag_id'] ?? FinancialTag::JUROS_ID;
                $fee->tags()->sync([$feeTagId => ['is_primary' => true]]);
            } elseif ($fee) {
                $fee->delete();
            }
        });

        return redirect()->route('financial.transactions.show', $transaction)
            ->with('success', 'Transferência atualizada com sucesso.');
    }

    public function update(UpdateFinancialTransactionRequest $request, FinancialTransaction $transaction): RedirectResponse
    {
        $validated = $request->validated();
        DB::transaction(function () use ($request, $transaction, $validated): void {
            if (! empty($validated['financial_credit_card_id'])) {
                $card = FinancialCreditCard::findOrFail($validated['financial_credit_card_id']);
                $date = Carbon::parse($validated['date']);

                $invoiceDate = $date->copy();
                if ($transaction->installment_current > 1) {
                    $invoiceDate->addMonthsNoOverflow($transaction->installment_current - 1);
                }

                $invoice = FinancialCreditCardInvoice::resolveForDate($card, $invoiceDate);

                $transaction->update([
                    'financial_account_id' => null,
                    'financial_credit_card_invoice_id' => $invoice->id,
                    'type' => $validated['type'],
                    'amount' => $validated['amount'],
                    'description' => $validated['description'],
                    'date' => $date,
                    'status' => TransactionStatus::Pending,
                ]);
            } else {
                $date = Carbon::parse($validated['date']);
                $transaction->update([
                    'financial_account_id' => $validated['financial_account_id'],
                    'financial_credit_card_invoice_id' => null,
                    'type' => $validated['type'],
                    'amount' => $validated['amount'],
                    'description' => $validated['description'],
                    'date' => $date,
                    'status' => $validated['status'] ?? TransactionStatus::Posted,
                ]);
            }

            $syncsItems = $request->boolean('items_present') || $request->exists('items');
            $hasItems = $syncsItems
                ? ! empty($validated['items'])
                : $transaction->items()->exists();

            $globalTags = $validated['tags'] ?? [];
            $globalPrimaryId = $hasItems ? null : ($validated['primary_tag_id'] ?? null);
            $this->syncTagsWithPrimary($transaction, $globalTags, $globalPrimaryId);

            if ($syncsItems) {
                $this->syncTransactionItems($transaction, $validated['items'] ?? []);
            }

            $this->syncAttachments($transaction, $validated);
        });

        return redirect()->route('financial.transactions.show', $transaction)->with('success', 'Transação atualizada com sucesso.');
    }

    public function destroy(FinancialTransaction $transaction): RedirectResponse
    {
        $transaction->delete();

        return redirect()->route('financial.transactions.index')->with('success', 'Transação movida para a lixeira.');
    }

    public function trashed(Request $request): View
    {
        $transactions = FinancialTransaction::onlyTrashed()
            ->with([
                'account',
                'invoice.creditCard',
                'tags',
                'settlements' => fn ($q) => $q->withTrashed(),
                'settlementGroup' => fn ($q) => $q->withTrashed(),
            ])
            ->orderByDesc('date')
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('finance.transactions.trashed', compact('transactions'));
    }

    public function restore(FinancialTransaction $transaction): RedirectResponse
    {
        $transaction->restore();

        return redirect()->back()->with('success', 'Transação restaurada com sucesso.');
    }

    public function forceDestroy(FinancialTransaction $transaction): RedirectResponse
    {
        $transaction->forceDelete();

        return redirect()->back()->with('success', 'Transação excluída permanentemente.');
    }

    /**
     * @return array{0: FinancialTransaction, 1: FinancialTransaction}
     */
    private function transferEntries(FinancialTransaction $transaction): array
    {
        abort_unless($transaction->transfer_pair_id, 404);

        $pair = FinancialTransaction::query()
            ->where('transfer_pair_id', $transaction->transfer_pair_id)
            ->where('id', '!=', $transaction->id)
            ->firstOrFail();

        return $transaction->type === 'expense'
            ? [$transaction, $pair]
            : [$pair, $transaction];
    }

    private function findTransferFee(FinancialTransaction $expense): ?FinancialTransaction
    {
        return FinancialTransaction::query()
            ->where('financial_account_id', $expense->financial_account_id)
            ->where('type', 'expense')
            ->whereDate('date', $expense->date)
            ->where('description', 'Taxa/imposto — '.$expense->description)
            ->whereNull('transfer_pair_id')
            ->first();
    }

    /**
     * @return array{requester_id: int, provider: string, access_key: string, uf: string|null, source_endpoint: string, request_parameter_suffix: string}
     */
    private function retryNfcePayload(Request $request): array
    {
        $encryptedPayload = $request->query('payload');

        abort_unless(is_string($encryptedPayload), 404);

        try {
            $payload = Crypt::decrypt($encryptedPayload);
        } catch (DecryptException) {
            abort(404);
        }

        $isValidPayload = is_array($payload)
            && is_int($payload['requester_id'] ?? null)
            && is_string($payload['provider'] ?? null)
            && is_string($payload['access_key'] ?? null)
            && (is_string($payload['uf'] ?? null) || ($payload['uf'] ?? null) === null)
            && is_string($payload['source_endpoint'] ?? null)
            && is_string($payload['request_parameter_suffix'] ?? null);

        abort_unless($isValidPayload, 404);

        return $payload;
    }

    private function syncTagsWithPrimary(FinancialTransaction|FinancialTransactionItem $model, array $tags, ?int $primaryId): void
    {
        if (empty($tags)) {
            $model->tags()->detach();

            return;
        }

        $syncData = [];
        foreach ($tags as $tagId) {
            $syncData[$tagId] = ['is_primary' => ($tagId == $primaryId)];
        }
        $model->tags()->sync($syncData);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncTransactionItems(FinancialTransaction $transaction, array $items): void
    {
        $existingItems = $transaction->items()->get()->keyBy('id');
        $submittedItemIds = collect($items)
            ->pluck('id')
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id);

        $existingItems
            ->reject(fn (FinancialTransactionItem $item): bool => $submittedItemIds->contains($item->id))
            ->each(function (FinancialTransactionItem $item): void {
                $item->tags()->detach();
                $this->forceDeleteTransactionItem($item);
            });

        foreach ($items as $itemData) {
            $item = isset($itemData['id'])
                ? $existingItems->get((int) $itemData['id'])
                : null;

            if ($item) {
                $item->update([
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total' => $itemData['quantity'] * $itemData['unit_price'],
                ]);
            } else {
                $item = $transaction->items()->create([
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total' => $itemData['quantity'] * $itemData['unit_price'],
                ]);
            }

            $item->tags()->detach();
            $this->syncTagsWithPrimary($item, $itemData['tags'] ?? [], $itemData['primary_tag_id'] ?? null);
        }
    }

    private function forceDeleteTransactionItem(FinancialTransactionItem $item): void
    {
        $oldAttributes = collect($item->getFillable())
            ->mapWithKeys(fn (string $attribute): array => [$attribute => $item->getAttribute($attribute)])
            ->all();

        activity('financial_transaction_item')
            ->event(AuditAction::ForceDeleted->value)
            ->performedOn($item)
            ->withChanges(['old' => $oldAttributes])
            ->log(AuditAction::ForceDeleted->value);

        $item->disableLogging()->delete();
    }

    public function attachment(FinancialTransaction $transaction, int $mediaId): BinaryFileResponse
    {
        $media = $transaction->getMedia('attachments')->where('id', $mediaId)->first();

        if (! $media) {
            abort(404);
        }

        return response()->file($media->getPath(), [
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }
}
