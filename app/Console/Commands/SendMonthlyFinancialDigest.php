<?php

namespace App\Console\Commands;

use App\Enums\NotificationLevel;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Notifications\FinancialSummaryNotification;
use App\Services\FinanceDashboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class SendMonthlyFinancialDigest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:monthly-digest {--force : Reenvia o resumo mesmo que ele já tenha sido enviado para o período}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia o resumo financeiro consolidado do mês anterior.';

    /**
     * Execute the console command.
     */
    public function handle(FinanceDashboardService $financeDashboardService): int
    {
        $lastMonth = Carbon::today()->subMonth();
        $startOfLastMonth = $lastMonth->copy()->startOfMonth();
        $endOfLastMonth = $lastMonth->copy()->endOfMonth();
        $previousMonth = $lastMonth->copy()->subMonth();
        $startOfPreviousMonth = $previousMonth->copy()->startOfMonth();
        $endOfPreviousMonth = $previousMonth->copy()->endOfMonth();

        $transactions = $this->getTransactionsForPeriod($startOfLastMonth, $endOfLastMonth);
        $previousTransactions = $this->getTransactionsForPeriod($startOfPreviousMonth, $endOfPreviousMonth);
        $totals = $this->getTotals($transactions);
        $previousTotals = $this->getTotals($previousTransactions);

        $netResult = $totals['net'];

        $monthLabel = $this->formatMonthLabel($lastMonth);
        $previousMonthLabel = $this->formatMonthLabel($previousMonth);
        $kpis = $financeDashboardService->getKpiNumbers(includeInvestments: true);
        $accountBalance = $kpis['currentBalance'];
        $netBalance = $kpis['netBalance'];
        $pendingCommitments = $accountBalance - $netBalance;

        $level = $netResult >= 0 ? NotificationLevel::Success : NotificationLevel::Warning;

        $notification = new FinancialSummaryNotification(
            summary: $this->buildSummary(
                $monthLabel,
                $previousMonthLabel,
                $totals,
                $previousTotals,
                $accountBalance,
                $pendingCommitments,
                $netBalance,
                $transactions,
            ),
            level: $level,
        );

        $sentNotifications = 0;
        $digestKey = "monthly-digest:{$lastMonth->format('Y-m')}";

        User::query()->each(function (User $user) use ($notification, $digestKey, &$sentNotifications): void {
            $cacheKey = $this->notificationCacheKey($user, $digestKey);

            if (! $this->option('force') && ! Cache::add($cacheKey, true, now()->addMonths(3))) {
                return;
            }

            if ($this->option('force')) {
                Cache::put($cacheKey, true, now()->addMonths(3));
            }

            try {
                $user->notify($notification);
                $sentNotifications++;
            } catch (Throwable $exception) {
                Cache::forget($cacheKey);

                throw $exception;
            }
        });

        $this->info("{$sentNotifications} resumo(s) financeiro(s) de {$monthLabel} enviado(s).");

        return Command::SUCCESS;
    }

    /**
     * @return Collection<int, FinancialTransaction>
     */
    private function getTransactionsForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return FinancialTransaction::query()
            ->with([
                'tags' => fn ($query) => $query->wherePivot('is_primary', true),
                'items.tags' => fn ($query) => $query->wherePivot('is_primary', true),
            ])
            ->withoutTransfers()
            ->withoutPartialPayments()
            ->withoutDrafts()
            ->whereBetween('date', [$startDate, $endDate])
            ->get();
    }

    /**
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return array{income: float, expense: float, net: float}
     */
    private function getTotals(Collection $transactions): array
    {
        $income = (float) $transactions->where('type', 'income')->sum('amount');
        $expense = (float) $transactions->where('type', 'expense')->sum('amount');

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
        ];
    }

    /**
     * @param  array{income: float, expense: float, net: float}  $totals
     * @param  array{income: float, expense: float, net: float}  $previousTotals
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return array{period: string, previous_period: string, income: float, income_variation: float, expense: float, expense_variation: float, net: float, net_variation: float, account_balance: float, pending_commitments: float, net_balance: float, income_categories: list<array{id: int, name: string, icon: string, color: string, amount: float, percentage: float}>, expense_categories: list<array{id: int, name: string, icon: string, color: string, amount: float, percentage: float}>}
     */
    private function buildSummary(
        string $monthLabel,
        string $previousMonthLabel,
        array $totals,
        array $previousTotals,
        float $accountBalance,
        float $pendingCommitments,
        float $netBalance,
        Collection $transactions,
    ): array {
        return [
            'period' => $monthLabel,
            'previous_period' => $previousMonthLabel,
            'income' => $totals['income'],
            'income_variation' => $totals['income'] - $previousTotals['income'],
            'expense' => $totals['expense'],
            'expense_variation' => $totals['expense'] - $previousTotals['expense'],
            'net' => $totals['net'],
            'net_variation' => $totals['net'] - $previousTotals['net'],
            'account_balance' => $accountBalance,
            'pending_commitments' => $pendingCommitments,
            'net_balance' => $netBalance,
            'income_categories' => $this->getTagBreakdown($transactions, 'income')->all(),
            'expense_categories' => $this->getTagBreakdown($transactions, 'expense')->all(),
        ];
    }

    /**
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return Collection<int, array{id: int, name: string, icon: string, color: string, amount: float, percentage: float}>
     */
    private function getTagBreakdown(Collection $transactions, string $type): Collection
    {
        $entries = collect();

        foreach ($transactions->where('type', $type) as $transaction) {
            $itemsTotal = 0.0;

            if ($transaction->items->isNotEmpty()) {
                foreach ($transaction->items as $item) {
                    $amount = (float) $item->unit_price * $item->quantity;
                    $itemsTotal += $amount;
                    $entries->push($this->tagEntry($item->tags->first(), $amount));
                }
            }

            $remainingAmount = (float) $transaction->amount - $itemsTotal;

            if ($transaction->items->isEmpty() || $remainingAmount > 0.01) {
                $entries->push($this->tagEntry($transaction->tags->first(), max(0, $remainingAmount)));
            }
        }

        $total = (float) $entries->sum('amount');

        return $entries
            ->groupBy('id')
            ->map(function (Collection $tagEntries) use ($total): array {
                $firstEntry = $tagEntries->first();
                $amount = (float) $tagEntries->sum('amount');

                return [
                    'id' => $firstEntry['id'],
                    'name' => $firstEntry['name'],
                    'icon' => $firstEntry['icon'],
                    'color' => $firstEntry['color'],
                    'amount' => $amount,
                    'percentage' => $total > 0 ? round(($amount / $total) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * @return array{id: int, name: string, icon: string, color: string, amount: float}
     */
    private function tagEntry(?object $tag, float $amount): array
    {
        return [
            'id' => $tag?->id ?? 0,
            'name' => $tag?->name ?? 'Sem categoria',
            'icon' => $tag?->icon ?? 'heroicon-o-tag',
            'color' => $tag?->color_hex ?? '#9ca3af',
            'amount' => $amount,
        ];
    }

    private function formatMonthLabel(Carbon $date): string
    {
        return Str::ucfirst($date->copy()->locale('pt_BR')->isoFormat('MMMM [de] YYYY'));
    }

    private function notificationCacheKey(User $user, string $digestKey): string
    {
        return "financial-notification:{$digestKey}:user:{$user->getKey()}";
    }
}
