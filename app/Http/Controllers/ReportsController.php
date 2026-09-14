<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialTransaction;
use App\Services\FinanceDashboardService;
use App\Services\ReportsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ReportsController extends Controller
{
    public function __construct(private ReportsService $reportsService) {}

    public function index(Request $request)
    {
        $period = $request->input('period', 'all_time');
        $accountId = $request->input('account');

        $accountIds = null;
        if ($accountId) {
            if (str_starts_with($accountId, 'type:')) {
                $type = substr($accountId, 5);
                $accountIds = FinancialAccount::where('type', $type)->pluck('id')->toArray();
            } else {
                $accountIds = [$accountId];
            }
        }

        $now = Carbon::today();

        if ($period === 'custom' && $request->filled('startDate') && $request->filled('endDate')) {
            $startDate = Carbon::parse($request->input('startDate'))->startOfDay();
            $endDate = Carbon::parse($request->input('endDate'))->endOfDay();
        } elseif ($period === 'all_time') {
            $minDate = FinancialTransaction::withoutDrafts()->min('date');
            $startDate = $minDate ? Carbon::parse($minDate)->startOfDay() : $now->copy()->subYears(5)->startOfDay();
            $maxInvoiceDate = FinancialCreditCardInvoice::max('due_date');
            $maxTransactionDate = FinancialTransaction::withoutDrafts()->max('date');
            $maxDate = max($maxInvoiceDate, $maxTransactionDate, $now->format('Y-m-d'));
            $endDate = Carbon::parse($maxDate)->endOfDay();
        } elseif ($period === 'until_today') {
            $minDate = FinancialTransaction::withoutDrafts()->min('date');
            $startDate = $minDate ? Carbon::parse($minDate)->startOfDay() : $now->copy()->subYears(5)->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } elseif ($period === 'this_month') {
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        } elseif ($period === 'last_month') {
            $startDate = $now->copy()->subMonth()->startOfMonth();
            $endDate = $now->copy()->subMonth()->endOfMonth();
        } elseif ($period === 'last_3m') {
            $startDate = $now->copy()->subMonths(2)->startOfMonth();
            $endDate = $now->copy()->endOfDay();
        } elseif ($period === 'last_6m') {
            $startDate = $now->copy()->subMonths(5)->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        } elseif ($period === 'this_year') {
            $startDate = $now->copy()->startOfYear();
            $endDate = $now->copy()->endOfYear();
        } elseif ($period === 'next_month') {
            $startDate = $now->copy()->addMonth()->startOfMonth();
            $endDate = $now->copy()->addMonth()->endOfMonth();
        } elseif ($period === 'next_6m') {
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->addMonths(5)->endOfMonth();
        } elseif ($period === 'next_12m') {
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->addMonths(11)->endOfMonth();
        } else {
            // Default to this month
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        }

        $reportData = $this->reportsService->getAll($startDate, $endDate, $accountIds);

        $accounts = FinancialAccount::orderBy('name')->get();

        $allTransactions = $reportData['tableTransactions'];
        $selectedTagId = $request->filled('tag_id') ? $request->integer('tag_id') : null;

        if ($selectedTagId !== null) {
            $allTransactions = $allTransactions->filter(function (FinancialTransaction $transaction) use ($selectedTagId): bool {
                if ($selectedTagId === 0) {
                    return $transaction->tags->isEmpty();
                }

                return $transaction->tags->contains('id', $selectedTagId);
            });
        }

        $realTransactions = $allTransactions->reject(fn ($t) => isset($t->is_virtual) && $t->is_virtual);
        $virtualTransactions = $allTransactions->filter(fn ($t) => isset($t->is_virtual) && $t->is_virtual);

        $page = request()->get('page', 1);
        $virtualPage = request()->get('virtual_page', 1);
        $perPage = 50;

        $paginatedTransactions = new LengthAwarePaginator(
            $realTransactions->forPage($page, $perPage),
            $realTransactions->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query(), 'pageName' => 'page']
        );
        $paginatedTransactions->fragment('transactions-table');

        $paginatedVirtual = new LengthAwarePaginator(
            $virtualTransactions->forPage($virtualPage, $perPage),
            $virtualTransactions->count(),
            $perPage,
            $virtualPage,
            ['path' => request()->url(), 'query' => request()->query(), 'pageName' => 'virtual_page']
        );
        $paginatedVirtual->fragment('transactions-table');

        $accountBalancesChart = [];
        if (empty($accountId) || str_starts_with($accountId, 'type:')) {
            $accountBalancesChart = app(FinanceDashboardService::class)->getAccountBalancesChart($accountIds, true);
        }

        $mobileCategories = collect([
            ...array_map(fn (array $item): array => [...$item, 'type' => 'expense'], $reportData['tags']['expenses']),
            ...array_map(fn (array $item): array => [...$item, 'type' => 'income'], $reportData['tags']['incomes']),
        ])->sortByDesc('value')->take(5)->values();

        return view('finance.reports', [
            'accounts' => $accounts,
            'sankey' => $reportData['sankey'],
            'evolution' => $reportData['evolution'],
            'netWorthEvolution' => $reportData['netWorthEvolution'],
            'expenses' => $reportData['tags']['expenses'],
            'incomes' => $reportData['tags']['incomes'],
            'allExpenses' => $reportData['tags']['allExpenses'],
            'allIncomes' => $reportData['tags']['allIncomes'],
            'netTags' => $reportData['tags']['netTags'],
            'mobileCategories' => $mobileCategories,
            'summary' => [
                'income' => $reportData['tags']['totalIncome'],
                'expense' => $reportData['tags']['totalExpense'],
                'balance' => $reportData['tags']['totalIncome'] - $reportData['tags']['totalExpense'],
            ],
            'transactions' => $paginatedTransactions,
            'virtualTransactions' => $paginatedVirtual,
            'period' => $period,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'accountId' => $accountId,
            'accountBalancesChart' => $accountBalancesChart,
            'selectedTagId' => $selectedTagId,
        ]);
    }
}
