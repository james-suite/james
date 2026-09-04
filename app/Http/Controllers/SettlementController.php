<?php

namespace App\Http\Controllers;

use App\Enums\SettlementType;
use App\Enums\TransactionStatus;
use App\Http\Requests\StoreSettlementRequest;
use App\Http\Requests\UpdateSettlementRequest;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactSettlementArchive;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\Settlement;
use App\Models\SettlementGroup;
use App\Traits\HandlesAttachments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettlementController extends Controller
{
    use HandlesAttachments;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $showArchived = $request->boolean('archived');

        $theyOweSql = "(SELECT COALESCE(SUM(amount), 0) FROM settlements WHERE contact_id = contacts.id AND type = '".SettlementType::TheyOwe->value."' AND deleted_at IS NULL)";
        $theyPaidSql = "(SELECT COALESCE(SUM(amount), 0) FROM settlements WHERE contact_id = contacts.id AND type = '".SettlementType::TheyPaid->value."' AND deleted_at IS NULL)";

        $iOweSql = "(SELECT COALESCE(SUM(amount), 0) FROM settlements WHERE contact_id = contacts.id AND type = '".SettlementType::IOwe->value."' AND deleted_at IS NULL)";
        $iPaidSql = "(SELECT COALESCE(SUM(amount), 0) FROM settlements WHERE contact_id = contacts.id AND type = '".SettlementType::IPaid->value."' AND deleted_at IS NULL)";

        $toReceiveSql = "GREATEST(0, $theyOweSql - $theyPaidSql)";
        $toPaySql = "GREATEST(0, $iOweSql - $iPaidSql)";

        $netBalanceSql = "($toReceiveSql - $toPaySql)";
        $settlementsCountSql = '(SELECT COUNT(*) FROM settlements WHERE contact_id = contacts.id AND deleted_at IS NULL)';

        $contacts = Contact::with(['groups', 'media'])
            ->when($showArchived, function ($query) {
                $query->whereHas('settlementArchive');
            }, function ($query) {
                $query->notSettlementArchived();
            })
            ->withSum(['settlements as they_owe' => fn ($q) => $q->where('type', SettlementType::TheyOwe->value)], 'amount')
            ->withSum(['settlements as they_paid' => fn ($q) => $q->where('type', SettlementType::TheyPaid->value)], 'amount')
            ->withSum(['settlements as i_owe' => fn ($q) => $q->where('type', SettlementType::IOwe->value)], 'amount')
            ->withSum(['settlements as i_paid' => fn ($q) => $q->where('type', SettlementType::IPaid->value)], 'amount')
            ->withCount('settlements')
            ->orderByRaw("
                CASE 
                    WHEN $netBalanceSql > 0 THEN 3
                    WHEN $netBalanceSql < 0 THEN 2
                    WHEN $settlementsCountSql > 0 THEN 1
                    ELSE 0
                END DESC
            ")
            ->orderByRaw("
                CASE 
                    WHEN $netBalanceSql > 0 THEN $netBalanceSql
                    WHEN $netBalanceSql < 0 THEN ABS($netBalanceSql)
                    ELSE 0
                END DESC
            ")
            ->get()
            ->map(function ($contact) {
                $toReceive = max(0, round(($contact->they_owe ?? 0) - ($contact->they_paid ?? 0), 2));
                $toPay = max(0, round(($contact->i_owe ?? 0) - ($contact->i_paid ?? 0), 2));

                $contact->to_receive = $toReceive;
                $contact->to_pay = $toPay;
                $contact->net_balance = round($toReceive - $toPay, 2);
                $contact->avatar_url = $contact->avatar;
                // Add group_ids for filtering in Alpine
                $contact->group_ids = $contact->groups->pluck('id')->toArray();

                return $contact;
            })
            ->values();

        $toReceive = round((float) $contacts->sum(fn ($c) => max(0, $c->net_balance)), 2);
        $toPay = round((float) $contacts->sum(fn ($c) => max(0, -$c->net_balance)), 2);
        $netBalance = round($toReceive - $toPay, 2);

        $groups = ContactGroup::orderBy('name')->get();

        $hasArchived = ContactSettlementArchive::exists();
        $hasHistory = Settlement::exists();
        $hasGroups = SettlementGroup::exists();

        // Obter todas as chaves PIX (apenas o valor, ignorando os rótulos) das contas financeiras do usuário
        $pixKeys = FinancialAccount::whereNotNull('pix_keys')
            ->get()
            ->pluck('pix_keys')
            ->flatten(1)
            ->pluck('value')
            ->filter()
            ->unique()
            ->values();

        return view('settlements.index', compact('contacts', 'toReceive', 'toPay', 'netBalance', 'showArchived', 'groups', 'hasArchived', 'hasHistory', 'hasGroups', 'pixKeys'));
    }

    /**
     * Display a global history of settlements.
     */
    public function history(Request $request): View
    {
        $query = Settlement::with(['contact', 'contact.media', 'media', 'group.media']);

        $settlements = $query->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(50);

        $hasTrashed = Settlement::onlyTrashed()->exists();

        return view('settlements.history', compact('settlements', 'hasTrashed'));
    }

    /**
     * Display the ledger for a specific contact.
     */
    public function showContact(Contact $contact): View
    {
        // Compute balances for this contact using the max(0, debt - payment) rule
        $debtTheyOweMe = Settlement::where('contact_id', $contact->id)->where('type', SettlementType::TheyOwe->value)->sum('amount');
        $paymentsTheyMade = Settlement::where('contact_id', $contact->id)->where('type', SettlementType::TheyPaid->value)->sum('amount');
        $toReceive = max(0, round($debtTheyOweMe - $paymentsTheyMade, 2));

        $debtIOweThem = Settlement::where('contact_id', $contact->id)->where('type', SettlementType::IOwe->value)->sum('amount');
        $paymentsIMade = Settlement::where('contact_id', $contact->id)->where('type', SettlementType::IPaid->value)->sum('amount');
        $toPay = max(0, round($debtIOweThem - $paymentsIMade, 2));

        $netBalance = round($toReceive - $toPay, 2);

        // Get settlements history for this contact (paginated)
        $settlements = Settlement::where('contact_id', $contact->id)
            ->with([
                'contact',
                'financialTransaction.account',
                'financialTransaction.invoice.creditCard',
                'group.financialTransaction.account',
                'group.financialTransaction.invoice.creditCard',
                'media',
                'group.media',
            ])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(50);

        $settleUrl = null;
        if (abs($netBalance) > 0) {
            $settleUrl = route('settlements.create', [
                'contact' => $contact->id,
                'settle' => 1,
            ]);
        }

        $pixKeys = FinancialAccount::whereNotNull('pix_keys')
            ->get()
            ->pluck('pix_keys')
            ->flatten(1)
            ->pluck('value')
            ->filter()
            ->unique()
            ->values();

        $baseMessageText = '';

        if ($netBalance > 0) {
            $baseMessageText = "Oi! Tô passando pra lembrar que você está me devendo.\n\nValor: *".formatCurrency(abs($netBalance))."*\n";
        } elseif ($netBalance < 0) {
            $baseMessageText = 'Oi! Sei que te devo *'.formatCurrency(abs($netBalance)).'*. Vou acertar o mais breve possível!';
        } else {
            $baseMessageText = 'Oi! Estamos quites, sem pendências!';
        }

        return view('settlements.contact', compact('contact', 'settlements', 'toReceive', 'toPay', 'netBalance', 'settleUrl', 'pixKeys', 'baseMessageText'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request, Contact $contact): View
    {
        $accounts = FinancialAccount::orderBy('name')->get();
        $cards = FinancialCreditCard::orderBy('name')->get();
        $tags = $this->tagOptions();

        $settlement = null;
        $isSettling = $request->boolean('settle');

        if ($isSettling) {
            $debtTheyOweMe = Settlement::where('contact_id', $contact->id)->where('type', SettlementType::TheyOwe->value)->sum('amount');
            $paymentsTheyMade = Settlement::where('contact_id', $contact->id)->where('type', SettlementType::TheyPaid->value)->sum('amount');
            $toReceive = max(0, round($debtTheyOweMe - $paymentsTheyMade, 2));

            $debtIOweThem = Settlement::where('contact_id', $contact->id)->where('type', SettlementType::IOwe->value)->sum('amount');
            $paymentsIMade = Settlement::where('contact_id', $contact->id)->where('type', SettlementType::IPaid->value)->sum('amount');
            $toPay = max(0, round($debtIOweThem - $paymentsIMade, 2));

            $netBalance = round($toReceive - $toPay, 2);

            if (abs($netBalance) > 0) {
                $settlement = new Settlement;
                $settlement->type = $netBalance > 0 ? SettlementType::TheyPaid : SettlementType::IPaid;
                $settlement->amount = abs($netBalance);
                $settlement->description = 'Quitação de saldo';
                $settlement->date = Carbon::today();
            }
        }

        return view('settlements.create', compact('contact', 'accounts', 'cards', 'tags', 'settlement', 'isSettling'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSettlementRequest $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $contact) {
            $settlement = new Settlement;
            $settlement->contact_id = $contact->id;
            $settlement->type = $validated['type'];
            $settlement->amount = $validated['amount'];
            $settlement->description = $validated['description'];
            $settlement->date = Carbon::parse($validated['date']);

            if (! empty($validated['create_transaction'])) {
                $transaction = $this->createOrUpdateTransaction(null, $validated, $contact);
                if ($transaction) {
                    $settlement->financial_transaction_id = $transaction->id;
                }
            }

            $settlement->save();

            $this->syncAttachments($settlement, $validated);
        });

        return redirect()->route('settlements.contact.show', $contact)
            ->with('success', 'Lançamento registrado com sucesso.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Settlement $settlement): View
    {
        abort_if($settlement->settlement_group_id !== null, 403, 'Este acerto faz parte de um grupo e não pode ser editado individualmente.');

        $settlement->load(['media', 'financialTransaction.tags']);
        $contact = $settlement->contact;
        $accounts = FinancialAccount::orderBy('name')->get();
        $cards = FinancialCreditCard::orderBy('name')->get();
        $tags = $this->tagOptions();
        $selectedTags = ($settlement->financialTransaction?->tags ?? collect())
            ->reject(fn (FinancialTag $tag): bool => $tag->id === FinancialTag::REEMBOLSO_ID);
        $defaultTags = $selectedTags->pluck('id')->all();
        $defaultPrimaryTag = $selectedTags->firstWhere('pivot.is_primary', true)?->id;

        return view('settlements.edit', compact('settlement', 'contact', 'accounts', 'cards', 'tags', 'defaultTags', 'defaultPrimaryTag'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSettlementRequest $request, Settlement $settlement): RedirectResponse
    {
        abort_if($settlement->settlement_group_id !== null, 403, 'Este acerto faz parte de um grupo e não pode ser editado individualmente.');

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $settlement) {
            $settlement->type = $validated['type'];
            $settlement->amount = $validated['amount'];
            $settlement->description = $validated['description'];
            $settlement->date = Carbon::parse($validated['date']);

            if (! empty($validated['create_transaction'])) {
                $transaction = $this->createOrUpdateTransaction($settlement->financialTransaction, $validated, $settlement->contact);
                $settlement->financial_transaction_id = $transaction->id;
            } elseif ($settlement->financial_transaction_id) {
                $settlement->financialTransaction()->delete();
                $settlement->financial_transaction_id = null;
            }

            $settlement->save();

            $this->syncAttachments($settlement, $validated);
        });

        return redirect()->route('settlements.contact.show', $settlement->contact_id)->with('success', 'Acerto atualizado com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Settlement $settlement): RedirectResponse
    {
        abort_if($settlement->settlement_group_id !== null, 403, 'Este acerto faz parte de um grupo e não pode ser excluído individualmente.');

        $contactId = $settlement->contact_id;

        DB::transaction(function () use ($settlement) {
            if ($settlement->financialTransaction) {
                $settlement->financialTransaction()->delete();
            }

            $settlement->delete();
        });

        return redirect()->route('settlements.contact.show', $contactId)
            ->with('success', 'Lançamento excluído com sucesso.');
    }

    /**
     * Create or update the associated financial transaction.
     */
    private function createOrUpdateTransaction(?FinancialTransaction $transaction, array $validated, Contact $contact): ?FinancialTransaction
    {
        // Type translation
        // TheyOwe (I paid) -> expense, IPaid (I paid) -> expense
        // TheyPaid (They paid me) -> income
        $type = 'expense';
        if ($validated['type'] === SettlementType::TheyPaid->value) {
            $type = 'income';
        }

        $date = Carbon::parse($validated['date']);

        $description = $validated['description'];
        $suffix = ' - '.$contact->name;
        if (! Str::endsWith($description, $suffix)) {
            $description .= $suffix;
        }

        $data = [
            'type' => $type,
            'amount' => $validated['amount'],
            'description' => $description,
            'date' => $date,
            'status' => TransactionStatus::Posted,
            'financial_account_id' => null,
            'financial_credit_card_invoice_id' => null,
        ];

        if ($validated['targetType'] === 'card') {
            $card = FinancialCreditCard::findOrFail($validated['financial_credit_card_id']);
            $invoice = FinancialCreditCardInvoice::resolveForDate($card, $date);
            $data['financial_credit_card_invoice_id'] = $invoice->id;
        } else {
            $data['financial_account_id'] = $validated['financial_account_id'];
        }

        if ($transaction) {
            $transaction->update($data);
        } else {
            $transaction = FinancialTransaction::create($data);
        }

        $this->syncTransactionTags($transaction, $validated);

        return $transaction;
    }

    /**
     * @return Collection<int, array{id: int, name: string, color_hex: string|null, svg: string}>
     */
    private function tagOptions(): Collection
    {
        return FinancialTag::query()
            ->where('id', '!=', FinancialTag::REEMBOLSO_ID)
            ->orderBy('name')
            ->get()
            ->map(fn (FinancialTag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color_hex' => $tag->color_hex,
                'svg' => Blade::render('<x-dynamic-component :component="$icon" class="size-3.5" />', ['icon' => $tag->icon]),
            ]);
    }

    /**
     * Synchronize the automatic reimbursement tag with the user-selected payment tags.
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncTransactionTags(FinancialTransaction $transaction, array $validated): void
    {
        $tagSync = [
            FinancialTag::REEMBOLSO_ID => ['is_primary' => true],
        ];

        if ($validated['type'] === SettlementType::IPaid->value && ! empty($validated['tags'])) {
            $selectedTagIds = collect($validated['tags'])
                ->map(fn ($tagId): int => (int) $tagId)
                ->reject(fn (int $tagId): bool => $tagId === FinancialTag::REEMBOLSO_ID);
            $primaryTagId = (int) ($validated['primary_tag_id'] ?? 0);

            $tagSync[FinancialTag::REEMBOLSO_ID] = ['is_primary' => false];

            foreach ($selectedTagIds as $tagId) {
                $tagSync[$tagId] = ['is_primary' => $tagId === $primaryTagId];
            }
        }

        $transaction->tags()->sync($tagSync);
    }

    public function trashed(): View
    {
        $settlements = Settlement::onlyTrashed()
            ->with(['contact', 'contact.media'])
            ->orderByDesc('deleted_at')
            ->paginate(50);

        return view('settlements.trashed', compact('settlements'));
    }

    public function restore(int $id): RedirectResponse
    {
        $settlement = Settlement::onlyTrashed()->findOrFail($id);

        abort_if($settlement->settlement_group_id !== null, 403, 'Este acerto faz parte de um grupo e não pode ser restaurado individualmente.');

        $settlement->restore();

        if ($settlement->financial_transaction_id) {
            FinancialTransaction::withTrashed()
                ->where('id', $settlement->financial_transaction_id)
                ->restore();
        }

        return redirect()->route('settlements.trashed')->with('success', 'Acerto restaurado com sucesso.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $settlement = Settlement::onlyTrashed()->findOrFail($id);

        abort_if($settlement->settlement_group_id !== null, 403, 'Este acerto faz parte de um grupo e não pode ser excluído individualmente.');

        DB::transaction(function () use ($settlement): void {
            if ($settlement->financial_transaction_id) {
                $transaction = FinancialTransaction::withTrashed()->find($settlement->financial_transaction_id);
                if ($transaction) {
                    $transaction->forceDelete();
                }
            }

            $settlement->forceDelete();
        });

        return redirect()->route('settlements.trashed')->with('success', 'Acerto excluído permanentemente.');
    }

    public function show(Settlement $settlement): View
    {
        $settlement->load(['contact', 'financialTransaction.account', 'financialTransaction.invoice.creditCard', 'media']);

        return view('settlements.show', compact('settlement'));
    }

    /**
     * Serve the settlement's attachment.
     */
    public function attachment(Settlement $settlement, int $mediaId): BinaryFileResponse
    {
        $media = $settlement->getMedia('attachments')->where('id', $mediaId)->first();

        if (! $media) {
            abort(404);
        }

        return response()->file($media->getPath(), [
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }
}
