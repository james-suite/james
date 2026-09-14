<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinancialRecurrenceRequest;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialRecurrence;
use App\Models\FinancialTag;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\View;

class FinancialRecurrenceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = FinancialRecurrence::query()
            ->select([
                'id', 'title', 'amount', 'type', 'frequency',
                'start_date', 'next_processing_date', 'is_active',
                'financial_account_id', 'financial_credit_card_id',
            ])
            ->with([
                'financialAccount:id,name',
                'financialCreditCard:id,name',
            ]);

        if ($request->filled('search')) {
            $query->search($request->search, ['title']);
        }

        $recurrences = $query->orderBy('start_date', 'desc')->paginate(15)->withQueryString();

        $hasTrashed = FinancialRecurrence::onlyTrashed()->exists();

        return view('finance.recurrences.index', compact('recurrences', 'hasTrashed'));
    }

    /**
     * Show the form for creating a new resource.
     */
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

        return view('finance.recurrences.create', compact('accounts', 'cards', 'tags'));
    }

    /**
     * Display the specified resource and its generated transactions.
     */
    public function show(FinancialRecurrence $recurrence): View
    {
        $recurrence->load(['financialAccount:id,name', 'financialCreditCard:id,name', 'tags:id,name,color_hex,icon']);

        $transactions = $recurrence->transactions()
            ->select(['id', 'financial_account_id', 'financial_credit_card_invoice_id', 'type', 'amount', 'description', 'date', 'status', 'installment_current', 'installment_total'])
            ->with(['account:id,name', 'invoice:id,financial_credit_card_id', 'invoice.creditCard:id,name', 'tags:id,name,color_hex,icon', 'media'])
            ->latest('date')
            ->paginate(15);

        return view('finance.recurrences.show', compact('recurrence', 'transactions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FinancialRecurrenceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['is_active'] = $request->has('is_active') ? true : false;
        $validated['next_processing_date'] = $validated['start_date'];

        $recurrence = FinancialRecurrence::create($validated);

        $this->syncTagsWithPrimary($recurrence, $validated['tags'] ?? [], $validated['primary_tag_id'] ?? null);

        return redirect()->route('financial.recurrences.index')
            ->with('success', 'Recorrência criada com sucesso.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FinancialRecurrence $recurrence): View
    {
        $recurrence->load('tags');
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

        return view('finance.recurrences.edit', compact('recurrence', 'accounts', 'cards', 'tags'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FinancialRecurrenceRequest $request, FinancialRecurrence $recurrence): RedirectResponse
    {
        $validated = $request->validated();

        $validated['is_active'] = $request->has('is_active') ? true : false;

        // Se a start_date for alterada para o futuro e for maior que a next_processing_date atual, atualiza
        if (Carbon::parse($validated['start_date'])->gt($recurrence->next_processing_date)) {
            $validated['next_processing_date'] = $validated['start_date'];
        }

        $recurrence->update($validated);

        $this->syncTagsWithPrimary($recurrence, $validated['tags'] ?? [], $validated['primary_tag_id'] ?? null);

        return redirect()->route('financial.recurrences.index')
            ->with('success', 'Recorrência atualizada com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinancialRecurrence $recurrence): RedirectResponse
    {
        $recurrence->delete();

        return redirect()->route('financial.recurrences.index')
            ->with('success', 'Recorrência enviada para a lixeira com sucesso.');
    }

    /**
     * Display a listing of the trashed resource.
     */
    public function trashed(Request $request): View
    {
        $query = FinancialRecurrence::onlyTrashed()
            ->select([
                'id', 'title', 'amount', 'type', 'frequency',
                'start_date', 'next_processing_date', 'is_active',
                'financial_account_id', 'financial_credit_card_id',
                'deleted_at',
            ])
            ->with([
                'financialAccount:id,name',
                'financialCreditCard:id,name',
            ]);

        if ($request->filled('search')) {
            $query->search($request->search, ['title']);
        }

        $recurrences = $query->orderBy('deleted_at', 'desc')->paginate(15)->withQueryString();

        return view('finance.recurrences.trashed', compact('recurrences'));
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore(FinancialRecurrence $recurrence): RedirectResponse
    {
        $recurrence->restore();

        return redirect()->route('financial.recurrences.trashed')
            ->with('success', 'Recorrência restaurada com sucesso.');
    }

    /**
     * Permanently remove the specified resource from storage.
     */
    public function forceDestroy(FinancialRecurrence $recurrence): RedirectResponse
    {
        $recurrence->tags()->detach();
        $recurrence->forceDelete();

        return redirect()->route('financial.recurrences.trashed')
            ->with('success', 'Recorrência excluída permanentemente.');
    }

    private function syncTagsWithPrimary(FinancialRecurrence $model, array $tags, ?int $primaryId): void
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
}
