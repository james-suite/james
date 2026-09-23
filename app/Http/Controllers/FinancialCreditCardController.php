<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinancialCreditCardRequest;
use App\Http\Requests\UpdateFinancialCreditCardRequest;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinancialCreditCardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $today = Carbon::today();

        $cards = FinancialCreditCard::query()
            ->when(request('search'), fn ($query, $search) => $query->search($search, ['name']))
            ->withUsedLimit()
            ->with(['financialAccount', 'invoices' => fn ($q) => $q->withTotalAmount()])
            ->latest()
            ->paginate(18)
            ->withQueryString();

        $cards->each(fn (FinancialCreditCard $card) => $card->setCurrentInvoice($today));

        $hasTrashed = FinancialCreditCard::onlyTrashed()->exists();

        return view('finance.cards.index', compact('cards', 'hasTrashed'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $accounts = FinancialAccount::orderBy('name')->get();

        return view('finance.cards.create', compact('accounts'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFinancialCreditCardRequest $request): RedirectResponse
    {
        $card = FinancialCreditCard::create($request->validated());

        // Resolve invoice for the current date upon creation
        FinancialCreditCardInvoice::resolveForDate($card, now());

        return redirect()
            ->route('financial.cards.show', $card)
            ->with('success', 'Cartão de crédito criado com sucesso.');
    }

    /**
     * Display the specified resource.
     */
    public function show(FinancialCreditCard $card): View
    {
        $invoices = $card->invoices()
            ->orderBy('reference_month', 'desc')
            ->get();

        return view('finance.cards.show', compact('card', 'invoices'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FinancialCreditCard $card): View
    {
        $accounts = FinancialAccount::orderBy('name')->get();

        return view('finance.cards.edit', compact('card', 'accounts'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFinancialCreditCardRequest $request, FinancialCreditCard $card): RedirectResponse
    {
        $validated = $request->validated();

        $closingDayChanged = $card->closing_day !== (int) $validated['closing_day'];
        $dueDayChanged = $card->due_day !== (int) $validated['due_day'];

        DB::transaction(function () use ($card, $validated, $closingDayChanged, $dueDayChanged): void {
            if ($closingDayChanged || $dueDayChanged) {
                $card->updateClosingSchedule((int) $validated['closing_day'], (int) $validated['due_day']);
                $card->update([
                    'name' => $validated['name'],
                    'financial_account_id' => $validated['financial_account_id'],
                    'credit_limit' => $validated['credit_limit'],
                ]);
            } else {
                $card->update($validated);
            }
        });

        return redirect()
            ->route('financial.cards.show', $card)
            ->with('success', 'Cartão de crédito atualizado com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinancialCreditCard $card): RedirectResponse
    {
        if ($this->hasLinkedRecords($card)) {
            return redirect()
                ->route('financial.cards.show', $card)
                ->with('error', 'Não é possível excluir este cartão enquanto ele possuir faturas ou recorrências vinculadas. Remova os vínculos primeiro.');
        }

        $card->delete();

        return redirect()
            ->route('financial.cards.index')
            ->with('success', 'Cartão de crédito removido com sucesso.');
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(): View
    {
        $cards = FinancialCreditCard::onlyTrashed()
            ->when(request('search'), fn ($query, $search) => $query->search($search, ['name']))
            ->with('financialAccount')
            ->latest('deleted_at')
            ->paginate(18)
            ->withQueryString();

        return view('finance.cards.trashed', compact('cards'));
    }

    /**
     * Restore a trashed resource.
     */
    public function restore(int $id): RedirectResponse
    {
        $card = FinancialCreditCard::withTrashed()->findOrFail($id);
        $card->restore();

        return redirect()
            ->route('financial.cards.show', $card)
            ->with('success', 'Cartão de crédito restaurado com sucesso.');
    }

    /**
     * Permanently delete a trashed resource.
     */
    public function forceDestroy(int $id): RedirectResponse
    {
        $card = FinancialCreditCard::withTrashed()->findOrFail($id);

        if ($this->hasLinkedRecords($card)) {
            return redirect()
                ->route('financial.cards.trashed')
                ->with('error', 'Não é possível excluir permanentemente este cartão pois ele possui faturas ou recorrências vinculadas. Remova os vínculos primeiro.');
        }

        $card->forceDelete();

        return redirect()
            ->route('financial.cards.trashed')
            ->with('success', 'Cartão de crédito excluído permanentemente.');
    }

    private function hasLinkedRecords(FinancialCreditCard $card): bool
    {
        return $card->invoices()->exists() || $card->recurrences()->withTrashed()->exists();
    }
}
