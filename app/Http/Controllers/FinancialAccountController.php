<?php

namespace App\Http\Controllers;

use App\Enums\FinancialAccountType;
use App\Enums\TransactionStatus;
use App\Http\Requests\StoreFinancialAccountRequest;
use App\Http\Requests\UpdateFinancialAccountRequest;
use App\Models\FinancialAccount;
use App\Models\FinancialTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $balanceSql = "(SELECT COALESCE(SUM(CASE WHEN type = 'income' THEN amount WHEN type = 'expense' THEN -amount ELSE 0 END), 0) FROM financial_transactions WHERE financial_account_id = financial_accounts.id AND status = '".TransactionStatus::Posted->value."' AND deleted_at IS NULL)";

        $accounts = FinancialAccount::query()
            ->when(request('search'), fn ($query, $search) => $query->search($search, ['name']))
            ->withBalance()
            ->orderByRaw("CASE WHEN $balanceSql > 0 THEN 0 WHEN $balanceSql < 0 THEN 1 ELSE 2 END")
            ->orderByRaw("CASE WHEN $balanceSql > 0 THEN $balanceSql WHEN $balanceSql < 0 THEN ABS($balanceSql) ELSE 0 END DESC")
            ->orderBy('name')
            ->paginate(18)
            ->withQueryString();

        $hasTrashed = FinancialAccount::onlyTrashed()->exists();

        return view('finance.accounts.index', compact('accounts', 'hasTrashed'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $types = FinancialAccountType::cases();

        $pixKeys = old('pix_keys', []);

        return view('finance.accounts.create', compact('types', 'pixKeys'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFinancialAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $financialAccount = FinancialAccount::create($validated);

        if (! empty($validated['initial_balance']) && $validated['initial_balance'] != 0) {
            $isPositive = $validated['initial_balance'] > 0;
            $transaction = $financialAccount->transactions()->create([
                'type' => $isPositive ? 'income' : 'expense',
                'amount' => abs($validated['initial_balance']),
                'date' => now(),
                'description' => 'Saldo Inicial',
                'status' => TransactionStatus::Posted,
            ]);

            $transaction->tags()->attach(FinancialTag::SALDO_INICIAL_ID, ['is_primary' => true]);
        }

        return redirect()
            ->route('financial.accounts.show', $financialAccount)
            ->with('success', 'Conta financeira criada com sucesso.');
    }

    /**
     * Display the specified resource.
     */
    public function show(FinancialAccount $financialAccount): View
    {
        $account = FinancialAccount::withBalance()->findOrFail($financialAccount->id);

        $globalIncome = $account->transactions()
            ->where('type', 'income')
            ->posted()
            ->sum('amount');

        $globalExpense = $account->transactions()
            ->where('type', 'expense')
            ->posted()
            ->sum('amount');

        $creditCards = $account->creditCards()
            ->withUsedLimit()
            ->with(['invoices' => function ($query) {
                $query->withTotalAmount()->whereNull('paid_at')->orderBy('due_date', 'asc');
            }])
            ->get();

        return view('finance.accounts.show', compact(
            'account',
            'globalIncome',
            'globalExpense',
            'creditCards'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FinancialAccount $financialAccount): View
    {
        $types = FinancialAccountType::cases();
        $pixKeys = old('pix_keys', $financialAccount->pix_keys ?? []);

        return view('finance.accounts.edit', compact('financialAccount', 'types', 'pixKeys'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFinancialAccountRequest $request, FinancialAccount $financialAccount): RedirectResponse
    {
        $financialAccount->update($request->validated());

        return redirect()
            ->route('financial.accounts.show', $financialAccount)
            ->with('success', 'Conta financeira atualizada com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinancialAccount $financialAccount): RedirectResponse
    {
        $financialAccount->delete();

        return redirect()
            ->route('financial.accounts.index')
            ->with('success', 'Conta financeira movida para a lixeira.');
    }

    /**
     * Adjust the balance of the account by creating a compensating transaction.
     */
    public function adjustBalance(Request $request, FinancialAccount $financialAccount): RedirectResponse
    {
        $request->validate([
            'real_balance' => ['required', 'numeric'],
        ]);

        $account = FinancialAccount::withBalance()->findOrFail($financialAccount->id);
        $difference = round((float) $request->real_balance - (float) $account->balance, 2);

        if ($difference === 0.0) {
            return redirect()
                ->route('financial.accounts.show', $financialAccount)
                ->with('info', 'Nenhuma diferença encontrada. O saldo já está correto.');
        }

        $isIncome = $difference > 0;

        $transaction = $financialAccount->transactions()->create([
            'type' => $isIncome ? 'income' : 'expense',
            'amount' => abs($difference),
            'description' => 'Ajuste de Saldo',
            'date' => now(),
            'status' => TransactionStatus::Posted,
        ]);

        $transaction->tags()->attach(FinancialTag::AJUSTE_SALDO_ID, ['is_primary' => true]);

        return redirect()
            ->route('financial.accounts.show', $financialAccount)
            ->with('success', 'Saldo ajustado com sucesso.');
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(): View
    {
        $accounts = FinancialAccount::onlyTrashed()
            ->when(request('search'), fn ($query, $search) => $query->search($search, ['name']))
            ->latest('deleted_at')
            ->paginate(50)
            ->withQueryString();

        return view('finance.accounts.trashed', compact('accounts'));
    }

    /**
     * Restore a trashed resource.
     */
    public function restore(FinancialAccount $financialAccount): RedirectResponse
    {
        $financialAccount->restore();

        return redirect()
            ->route('financial.accounts.show', $financialAccount)
            ->with('success', 'Conta financeira restaurada com sucesso.');
    }

    /**
     * Permanently delete a trashed resource.
     */
    public function forceDestroy(FinancialAccount $financialAccount): RedirectResponse
    {
        if ($financialAccount->creditCards()->exists() ||
            $financialAccount->transactions()->exists() ||
            $financialAccount->recurrences()->exists()) {
            return redirect()
                ->route('financial.accounts.trashed')
                ->with('error', 'Não é possível excluir permanentemente esta conta pois ela possui cartões de crédito, transações ou recorrências vinculadas. Remova os vínculos primeiro.');
        }

        $financialAccount->forceDelete();

        return redirect()
            ->route('financial.accounts.trashed')
            ->with('success', 'Conta financeira excluída permanentemente.');
    }
}
