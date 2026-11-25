<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSettlementGroupRequest;
use App\Http\Requests\UpdateSettlementGroupRequest;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\SettlementGroup;
use App\Services\SettlementGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettlementGroupController extends Controller
{
    public function __construct(
        public SettlementGroupService $service
    ) {}

    /**
     * Display a listing of settlement groups.
     */
    public function index(Request $request): View
    {
        $query = SettlementGroup::with(['media', 'financialTransaction.account', 'financialTransaction.invoice.creditCard', 'settlements.contact']);

        if ($request->has('trashed')) {
            $query->onlyTrashed();
        }

        $groups = $query->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(50);

        $hasTrashed = SettlementGroup::onlyTrashed()->exists();

        return view('settlements.groups.index', compact('groups', 'hasTrashed'));
    }

    /**
     * Show the form for creating a new split bill.
     */
    public function create(Request $request): View
    {
        $contactIds = array_filter(explode(',', $request->query('contacts', '')));

        abort_if(empty($contactIds), 404);

        $contacts = Contact::with('media')->whereIn('id', $contactIds)->get();

        abort_if($contacts->isEmpty(), 404);

        $allContacts = Contact::with('media')->orderBy('name')->get();
        $accounts = FinancialAccount::active()->orderBy('name')->get();
        $cards = FinancialCreditCard::active()->orderBy('name')->get();
        $tags = FinancialTag::orderBy('name')->get()->map(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'color_hex' => $tag->color_hex,
                'svg' => Blade::render('<x-dynamic-component :component="$icon" class="size-3.5" />', ['icon' => $tag->icon]),
            ];
        });

        return view('settlements.groups.create', compact('contacts', 'allContacts', 'accounts', 'cards', 'tags'));
    }

    /**
     * Store a newly created split bill.
     */
    public function store(StoreSettlementGroupRequest $request): RedirectResponse
    {
        $this->service->storeGroup($request->validated());

        return redirect()->route('settlements.index')
            ->with('success', 'Divisão de conta registrada com sucesso.');
    }

    /**
     * Display the specified split bill.
     */
    public function show(SettlementGroup $settlementGroup): View
    {
        $settlementGroup->load(['media', 'settlements.contact.media', 'financialTransaction.items.tags', 'financialTransaction.tags']);

        return view('settlements.groups.show', compact('settlementGroup'));
    }

    /**
     * Show the form for editing the specified split bill.
     */
    public function edit(SettlementGroup $settlementGroup): View
    {
        $settlementGroup->load(['media', 'settlements.contact.media', 'financialTransaction.items.tags', 'financialTransaction.tags']);

        $contacts = $settlementGroup->settlements->map(fn ($s) => $s->contact)->filter()->unique('id');
        $allContacts = Contact::with('media')->orderBy('name')->get();

        $existingAccountId = $settlementGroup->financialTransaction?->financial_account_id;
        $existingCardId = $settlementGroup->financialTransaction?->invoice?->financial_credit_card_id;

        $accounts = FinancialAccount::query()
            ->where(fn ($query) => $query->active()->when($existingAccountId, fn ($q) => $q->orWhere('id', $existingAccountId)))
            ->orderBy('name')
            ->get();
        $cards = FinancialCreditCard::query()
            ->where(fn ($query) => $query->active()->when($existingCardId, fn ($q) => $q->orWhere('id', $existingCardId)))
            ->orderBy('name')
            ->get();
        $tags = FinancialTag::orderBy('name')->get()->map(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'color_hex' => $tag->color_hex,
                'svg' => Blade::render('<x-dynamic-component :component="$icon" class="size-3.5" />', ['icon' => $tag->icon]),
            ];
        });

        return view('settlements.groups.edit', compact('settlementGroup', 'contacts', 'allContacts', 'accounts', 'cards', 'tags'));
    }

    /**
     * Update the specified split bill.
     */
    public function update(UpdateSettlementGroupRequest $request, SettlementGroup $settlementGroup): RedirectResponse
    {
        $this->service->updateGroup($settlementGroup, $request->validated());

        return redirect()->route('settlements.index')
            ->with('success', 'Divisão de conta atualizada com sucesso.');
    }

    /**
     * Remove the specified split bill.
     */
    public function destroy(SettlementGroup $settlementGroup): RedirectResponse
    {
        $this->service->destroyGroup($settlementGroup);

        return redirect()->route('settlements.index')
            ->with('success', 'Divisão de conta excluída com sucesso.');
    }

    public function trashed(): View
    {
        $settlementGroups = SettlementGroup::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->paginate(50);

        return view('settlements.groups.trashed', compact('settlementGroups'));
    }

    public function restore(int $id): RedirectResponse
    {
        $group = SettlementGroup::onlyTrashed()->findOrFail($id);

        $group->restore();

        $group->settlements()->withTrashed()->restore();

        if ($group->financial_transaction_id) {
            FinancialTransaction::withTrashed()
                ->where('id', $group->financial_transaction_id)
                ->restore();
        }

        return redirect()->route('settlements.groups.trashed')->with('success', 'Divisão de conta restaurada com sucesso.');
    }

    public function attachment(SettlementGroup $settlementGroup, Media $media, ?string $filename = null): BinaryFileResponse
    {
        abort_if($media->model_type !== SettlementGroup::class || $media->model_id !== $settlementGroup->id, 404);

        return response()->file($media->getPath(), [
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $group = SettlementGroup::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($group) {
            if ($group->financial_transaction_id) {
                $transaction = FinancialTransaction::withTrashed()->find($group->financial_transaction_id);
                if ($transaction) {
                    $transaction->forceDelete();
                }
            }

            $group->forceDeleteSettlements();
            $group->forceDelete();
        });

        return redirect()->route('settlements.groups.trashed')->with('success', 'Divisão de conta excluída permanentemente.');
    }
}
