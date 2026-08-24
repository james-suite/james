<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialRecurrence;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    /**
     * Loads all report data in a single pass, fetching unified transactions only once.
     *
     * @return array{sankey: array, evolution: array, tags: array, transactions: Collection}
     */
    public function getAll(Carbon $startDate, Carbon $endDate, ?array $accountIds = null): array
    {
        $transactions = $this->getUnifiedTransactions($startDate, $endDate, $accountIds);
        $flattenedForTags = $this->flattenTransactionsForTags($transactions);
        $tableTransactions = $this->flattenTransactionsForTable($transactions);

        $initialBalance = $this->getInitialBalance($startDate, $accountIds);
        $initialNetWorth = $this->getInitialNetWorth($startDate, $accountIds);

        return [
            'sankey' => $this->buildSankeyData($flattenedForTags, $initialBalance),
            'evolution' => $this->buildEvolutionData($transactions, $startDate, $endDate, $initialBalance, $accountIds),
            'netWorthEvolution' => $this->buildNetWorthEvolutionData($transactions, $startDate, $endDate, $initialNetWorth),
            'tags' => $this->buildTagsData($flattenedForTags),
            'transactions' => $transactions,
            'tableTransactions' => $tableTransactions,
        ];
    }

    /**
     * Gets a unified list of real and virtual transactions for the given period.
     */
    private function getUnifiedTransactions(Carbon $startDate, Carbon $endDate, ?array $accountIds = null): Collection
    {
        // 1. Real Transactions (includes future credit card installments since they are materialized)
        $query = FinancialTransaction::with(['account', 'invoice.creditCard', 'items.tags', 'media', 'tags'])
            ->withoutDrafts()
            ->whereBetween('date', [$startDate, $endDate])
            ->forAccounts($accountIds);

        if (empty($accountIds)) {
            $query->withoutTransfers();
        }

        $realTransactions = $query->get()->map(function ($t) {
            $t->is_virtual = false;

            return $t;
        });

        // 2. Virtual Transactions from Recurrences
        $recurrenceQuery = FinancialRecurrence::with(['tags', 'financialAccount', 'financialCreditCard'])
            ->where('is_active', true)
            ->forAccounts($accountIds)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
            });

        $recurrences = $recurrenceQuery->get();
        $virtualTransactions = collect();

        foreach ($recurrences as $recurrence) {
            $currentDate = $recurrence->next_processing_date ? $recurrence->next_processing_date->copy() : $recurrence->start_date->copy();

            // Fast forward to startDate if needed
            while ($currentDate->lt($startDate)) {
                $currentDate = $this->addFrequency($currentDate, $recurrence->frequency);
            }

            while ($currentDate->between($startDate, $endDate)) {
                $t = new FinancialTransaction([
                    'type' => $recurrence->type,
                    'amount' => $recurrence->amount,
                    'date' => $currentDate->copy(),
                    'description' => $recurrence->title,
                    'status' => TransactionStatus::Pending,
                ]);

                $t->id = 'v_'.$recurrence->id.'_'.$currentDate->format('Ymd');
                $t->is_virtual = true;

                // Mock relations
                if ($recurrence->relationLoaded('tags')) {
                    $t->setRelation('tags', $recurrence->tags);
                }
                if ($recurrence->relationLoaded('financialAccount') && $recurrence->financialAccount) {
                    $t->setRelation('account', $recurrence->financialAccount);
                }
                if ($recurrence->relationLoaded('financialCreditCard') && $recurrence->financialCreditCard) {
                    // Create a fake invoice so the transaction-table component can read the credit card name
                    $fakeInvoice = new FinancialCreditCardInvoice;
                    $fakeInvoice->setRelation('creditCard', $recurrence->financialCreditCard);
                    $t->setRelation('invoice', $fakeInvoice);
                }

                $virtualTransactions->push($t);

                $currentDate = $this->addFrequency($currentDate, $recurrence->frequency);
            }
        }

        return $realTransactions->concat($virtualTransactions)->sortBy('date')->values();
    }

    private function flattenTransactionsForTags(Collection $transactions): Collection
    {
        $flattened = collect();

        foreach ($transactions as $t) {
            if ($t->relationLoaded('items') && $t->items->isNotEmpty()) {
                $itemsSum = 0;
                foreach ($t->items as $item) {
                    $itemAmount = $item->unit_price * $item->quantity;
                    $itemsSum += $itemAmount;

                    // Create a fake entry
                    $entry = new \stdClass;
                    $entry->type = $t->type;
                    $entry->amount = $itemAmount;
                    $entry->tags = $item->tags;

                    $flattened->push($entry);
                }

                $remainingAmount = $t->amount - $itemsSum;
                if ($remainingAmount > 0.01) {
                    $entry = new \stdClass;
                    $entry->type = $t->type;
                    $entry->amount = $remainingAmount;
                    $entry->tags = $t->tags;
                    $flattened->push($entry);
                }
            } else {
                $entry = new \stdClass;
                $entry->type = $t->type;
                $entry->amount = $t->amount;
                $entry->tags = $t->tags;
                $flattened->push($entry);
            }
        }

        return $flattened;
    }

    private function flattenTransactionsForTable(Collection $transactions): Collection
    {
        $flattened = collect();

        foreach ($transactions as $t) {
            if ($t->relationLoaded('items') && $t->items->isNotEmpty()) {
                $itemsSum = 0;
                foreach ($t->items as $item) {
                    $itemAmount = $item->unit_price * $item->quantity;
                    $itemsSum += $itemAmount;

                    $entry = clone $t;
                    $entry->amount = $itemAmount;
                    $entry->description = $t->description.' - '.$item->description;
                    $entry->setRelation('tags', $item->tags);

                    $flattened->push($entry);
                }

                $remainingAmount = $t->amount - $itemsSum;
                if ($remainingAmount > 0.01) {
                    $entry = clone $t;
                    $entry->amount = $remainingAmount;
                    $entry->description = $t->description.' (Restante)';
                    $flattened->push($entry);
                }
            } else {
                $flattened->push($t);
            }
        }

        return $flattened;
    }

    private function addFrequency(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            'weekly' => $date->copy()->addWeek(),
            'monthly' => $date->copy()->addMonthNoOverflow(),
            'yearly' => $date->copy()->addYearNoOverflow(),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }

    private function buildSankeyData(Collection $transactions, float $initialBalance): array
    {
        $nodes = collect();
        $links = collect();

        // Central Node
        $nodes->push(['name' => 'Fluxo de Caixa', 'itemStyle' => ['color' => '#3b82f6']]);

        $getPrimaryTag = function ($t): ?FinancialTag {
            return $t->tags->where('pivot.is_primary', true)->first() ?? $t->tags->first();
        };

        $groupedByTag = $transactions
            ->groupBy(fn ($t) => optional($getPrimaryTag($t))->name ?? 'Sem Categoria')
            ->sortByDesc(function (Collection $items): float {
                $incomeSum = (float) $items->where('type', 'income')->sum('amount');
                $expenseSum = (float) $items->where('type', 'expense')->sum('amount');

                return abs($incomeSum - $expenseSum);
            });

        $totalIncome = 0;
        $totalExpense = 0;

        foreach ($groupedByTag as $tagName => $items) {
            $incomeSum = $items->where('type', 'income')->sum('amount');
            $expenseSum = $items->where('type', 'expense')->sum('amount');

            $netValue = $incomeSum - $expenseSum;

            if (abs($netValue) > 0.001) {
                $tag = $getPrimaryTag($items->first());
                $nodes->push([
                    'name' => $tagName,
                    'itemStyle' => ['color' => $tag?->color_hex ?? '#9ca3af'],
                ]);

                if ($netValue > 0) {
                    $totalIncome += $netValue;
                    $links->push([
                        'source' => $tagName,
                        'target' => 'Fluxo de Caixa',
                        'value' => round($netValue, 2),
                    ]);
                } else {
                    $totalExpense += abs($netValue);
                    $links->push([
                        'source' => 'Fluxo de Caixa',
                        'target' => $tagName,
                        'value' => round(abs($netValue), 2),
                    ]);
                }
            }
        }

        // Initial Balance (Saldo Anterior)
        if ($initialBalance > 0) {
            $nodes->push(['name' => 'Saldo Anterior', 'itemStyle' => ['color' => '#64748b']]);
            $links->push([
                'source' => 'Saldo Anterior',
                'target' => 'Fluxo de Caixa',
                'value' => round($initialBalance, 2),
            ]);
            $totalIncome += $initialBalance;
        }

        // Surplus / Deficit
        if ($totalIncome > $totalExpense) {
            $surplus = $totalIncome - $totalExpense;
            $nodes->push(['name' => 'Saldo', 'itemStyle' => ['color' => '#10b981']]);
            $links->push([
                'source' => 'Fluxo de Caixa',
                'target' => 'Saldo',
                'value' => round($surplus, 2),
            ]);
        } elseif ($totalExpense > $totalIncome) {
            $deficit = $totalExpense - $totalIncome;
            $nodes->push(['name' => 'Uso de Reservas (Déficit)', 'itemStyle' => ['color' => '#ef4444']]);
            $links->push([
                'source' => 'Uso de Reservas (Déficit)',
                'target' => 'Fluxo de Caixa',
                'value' => round($deficit, 2),
            ]);
        }

        $nodes = $nodes->unique('name')->values();

        return [
            'nodes' => $nodes->toArray(),
            'links' => $links->toArray(),
        ];
    }

    private function buildEvolutionData(Collection $transactions, Carbon $startDate, Carbon $endDate, float $initialBalance, ?array $accountIds = null): array
    {
        $periods = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $periods[$currentDate->format('Y-m-d')] = ['income' => 0, 'expense' => 0];
            $currentDate->addDay();
        }

        // Cash-basis: exclude CC invoice transactions (their cash impact is via the payment transaction)
        $query = FinancialTransaction::forAccounts($accountIds)
            ->withoutDrafts()
            ->whereNull('financial_credit_card_invoice_id')
            ->whereBetween('date', [$startDate, $endDate]);

        if (empty($accountIds)) {
            $query->withoutTransfers();
        }

        $cashFlows = $query->selectRaw('date')
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income")
            ->selectRaw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense")
            ->groupBy('date')
            ->get();

        $virtuals = $transactions->where('is_virtual', true);

        foreach ($cashFlows as $cf) {
            $key = is_string($cf->date) ? substr($cf->date, 0, 10) : $cf->date->format('Y-m-d');
            if (isset($periods[$key])) {
                $periods[$key]['income'] += (float) $cf->income;
                $periods[$key]['expense'] += (float) $cf->expense;
            }
        }

        // Add invoice cash flows for the period (cash-basis: paid on paid_at, unpaid on due_date)
        foreach ($this->buildInvoicePeriodFlows($startDate, $endDate, $accountIds) as $dateStr => $amount) {
            if (isset($periods[$dateStr])) {
                $periods[$dateStr]['expense'] += $amount;
            }
        }

        $lastPeriodKey = array_key_last($periods);

        foreach ($virtuals as $t) {
            $date = Carbon::parse($t->date);

            if ($t->relationLoaded('invoice') && $t->invoice && $t->invoice->creditCard) {
                $date = $t->invoice->creditCard->resolveInvoiceDueDate($date);
            }

            $key = $date->format('Y-m-d');

            // If the resolved invoice due date falls beyond the report period, assign it to future_expense of the last bucket
            if (! isset($periods[$key]) && $lastPeriodKey !== null && $date->gt($endDate)) {
                if ($t->type === 'expense') {
                    $periods[$lastPeriodKey]['future_expense'] = ($periods[$lastPeriodKey]['future_expense'] ?? 0) + $t->amount;
                }

                continue;
            }

            if (isset($periods[$key])) {
                if ($t->type === 'income') {
                    $periods[$key]['income'] += $t->amount;
                } else {
                    $periods[$key]['expense'] += $t->amount;
                }
            }
        }

        $chartData = [];
        $runningBalance = (float) $initialBalance;

        foreach ($periods as $key => $data) {
            $net = $data['income'] - $data['expense'];
            $runningBalance += $net;

            $chartData[] = [
                'date' => $key,
                'value' => round($runningBalance, 2),
                'income' => round($data['income'], 2),
                'expense' => round($data['expense'], 2),
                'future_expense' => round($data['future_expense'] ?? 0, 2),
            ];
        }

        return $chartData;
    }

    private function buildNetWorthEvolutionData(Collection $transactions, Carbon $startDate, Carbon $endDate, float $initialNetWorth): array
    {
        $periods = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $periods[$currentDate->format('Y-m-d')] = ['income' => 0, 'expense' => 0];
            $currentDate->addDay();
        }

        $partialPaymentTagId = FinancialTag::PAGAMENTO_PARCIAL_ID;

        foreach ($transactions as $t) {
            $hasIgnoredTag = false;
            if ($t->relationLoaded('tags') && $t->tags) {
                if ($t->tags->contains('id', $partialPaymentTagId)) {
                    $hasIgnoredTag = true;
                }
            }

            if ($hasIgnoredTag) {
                continue;
            }

            $date = Carbon::parse($t->date);
            $key = $date->format('Y-m-d');

            if (isset($periods[$key])) {
                if ($t->type === 'income') {
                    $periods[$key]['income'] += (float) $t->amount;
                } else {
                    $periods[$key]['expense'] += (float) $t->amount;
                }
            }
        }

        $chartData = [];
        $runningBalance = (float) $initialNetWorth;

        foreach ($periods as $key => $data) {
            $net = $data['income'] - $data['expense'];
            $runningBalance += $net;

            $chartData[] = [
                'date' => $key,
                'value' => round($runningBalance, 2),
                'income' => round($data['income'], 2),
                'expense' => round($data['expense'], 2),
            ];
        }

        return $chartData;
    }

    private function buildTagsData(Collection $transactions): array
    {
        $primaryExpenses = [];
        $primaryIncomes = [];
        $primaryTotalExp = 0;
        $primaryTotalInc = 0;

        $allExpenses = [];
        $allIncomes = [];

        foreach ($transactions as $t) {
            $amount = $t->amount;
            $tags = $t->tags->isEmpty() ? collect([(object) ['id' => 0, 'name' => 'Sem Categoria', 'color_hex' => '#cbd5e1', 'icon' => 'heroicon-o-tag', 'pivot' => (object) ['is_primary' => true]]]) : $t->tags;

            $primaryTag = $tags->first(fn ($tg) => ! empty($tg->pivot) && $tg->pivot->is_primary) ?? $tags->first();
            $isExpense = $t->type === 'expense';

            // primary tags logic
            $targetPrimary = &$primaryExpenses;
            if (! $isExpense) {
                $targetPrimary = &$primaryIncomes;
            }

            if (! isset($targetPrimary[$primaryTag->id])) {
                $targetPrimary[$primaryTag->id] = [
                    'id' => $primaryTag->id,
                    'name' => $primaryTag->name,
                    'color' => $primaryTag->color_hex ?? '#cbd5e1',
                    'icon' => $primaryTag->icon ?? 'heroicon-o-tag',
                    'value' => 0,
                ];
            }
            $targetPrimary[$primaryTag->id]['value'] += $amount;

            if ($isExpense) {
                $primaryTotalExp += $amount;
            } else {
                $primaryTotalInc += $amount;
            }

            // all tags logic
            foreach ($tags as $tag) {
                $id = $tag->id;

                $targetAll = &$allExpenses;
                if (! $isExpense) {
                    $targetAll = &$allIncomes;
                }

                if (! isset($targetAll[$id])) {
                    $targetAll[$id] = [
                        'id' => $id,
                        'name' => $tag->name,
                        'color' => $tag->color_hex ?? '#cbd5e1',
                        'icon' => $tag->icon ?? 'heroicon-o-tag',
                        'value' => 0,
                    ];
                }

                $targetAll[$id]['value'] += $amount;
            }
        }

        $primaryExpenses = collect(array_values($primaryExpenses))->sortByDesc('value')->map(function ($item) use ($primaryTotalExp) {
            $item['percentage'] = $primaryTotalExp > 0 ? round(($item['value'] / $primaryTotalExp) * 100, 1) : 0;

            return $item;
        })->values()->toArray();

        $primaryIncomes = collect(array_values($primaryIncomes))->sortByDesc('value')->map(function ($item) use ($primaryTotalInc) {
            $item['percentage'] = $primaryTotalInc > 0 ? round(($item['value'] / $primaryTotalInc) * 100, 1) : 0;

            return $item;
        })->values()->toArray();

        // Calculate Net Tags (Before destroying the ID keys of allExpenses and allIncomes)
        $netTags = [];
        $allTagIds = array_unique(array_merge(array_keys($allExpenses), array_keys($allIncomes)));

        foreach ($allTagIds as $id) {
            $incomeItem = $allIncomes[$id] ?? null;
            $expenseItem = $allExpenses[$id] ?? null;

            $incomeVal = $incomeItem ? $incomeItem['value'] : 0;
            $expenseVal = $expenseItem ? $expenseItem['value'] : 0;

            $baseItem = $incomeItem ?? $expenseItem;

            $netTags[] = [
                'id' => $id,
                'name' => $baseItem['name'],
                'color' => $baseItem['color'],
                'icon' => $baseItem['icon'],
                'income' => $incomeVal,
                'expense' => $expenseVal,
                'value' => $incomeVal - $expenseVal,
            ];
        }

        // Sort by value (highest profit first, highest loss last)
        $netTags = collect($netTags)->sortByDesc('value')->values()->toArray();

        $allExpenses = collect(array_values($allExpenses))->sortByDesc('value')->values()->toArray();
        $allIncomes = collect(array_values($allIncomes))->sortByDesc('value')->values()->toArray();

        return [
            'expenses' => $primaryExpenses,
            'incomes' => $primaryIncomes,
            'allExpenses' => $allExpenses,
            'allIncomes' => $allIncomes,
            'netTags' => $netTags,
            'totalExpense' => $primaryTotalExp,
            'totalIncome' => $primaryTotalInc,
        ];
    }

    private function getInitialBalance(Carbon $startDate, ?array $accountIds = null): float
    {
        $query = FinancialTransaction::forAccounts($accountIds)
            ->withoutDrafts()
            ->whereNull('financial_credit_card_invoice_id')
            ->where('financial_transactions.date', '<', $startDate);

        if (empty($accountIds)) {
            $query->withoutTransfers();
        }

        $nonCcBalance = (float) $query->sum(DB::raw("CASE WHEN financial_transactions.type = 'income' THEN amount WHEN financial_transactions.type = 'expense' THEN -amount ELSE 0 END"));

        // Add invoice totals that settled (paid_at or due_date) before startDate
        $invoiceBalance = 0.0;
        $invoiceQuery = FinancialCreditCardInvoice::withTotalAmount();

        if (! empty($accountIds)) {
            $invoiceQuery->whereHas('creditCard', fn ($q) => $q->whereIn('financial_account_id', $accountIds));
        }

        foreach ($invoiceQuery->get() as $invoice) {
            $invoiceTotal = (float) $invoice->total();
            if ($invoiceTotal <= 0) {
                continue;
            }

            if ($invoice->paid_at !== null) {
                if ($invoice->paid_at->lt($startDate)) {
                    $invoiceBalance -= $invoiceTotal;
                }
            } else {
                $remaining = $invoiceTotal - (float) $invoice->amount_paid;
                if ($remaining > 0 && $invoice->due_date->lt($startDate)) {
                    $invoiceBalance -= $remaining;
                }
            }
        }

        return $nonCcBalance + $invoiceBalance;
    }

    private function getInitialNetWorth(Carbon $startDate, ?array $accountIds = null): float
    {
        $query = FinancialTransaction::withoutPartialPayments()
            ->withoutDrafts()
            ->forAccounts($accountIds)
            ->where('financial_transactions.date', '<', $startDate);

        if (empty($accountIds)) {
            $query->withoutTransfers();
        }

        return (float) $query->sum(DB::raw("CASE WHEN financial_transactions.type = 'income' THEN amount WHEN financial_transactions.type = 'expense' THEN -amount ELSE 0 END"));
    }

    /**
     * Returns invoice cash flows (date => expense amount) for invoices settling within [$startDate, $endDate].
     * Fully paid: total on paid_at. Unpaid/partial: remaining on due_date (partial payment_transaction already captured).
     *
     * @return array<string, float>
     */
    private function buildInvoicePeriodFlows(Carbon $startDate, Carbon $endDate, ?array $accountIds = null): array
    {
        $query = FinancialCreditCardInvoice::withTotalAmount();

        if (! empty($accountIds)) {
            $query->whereHas('creditCard', fn ($q) => $q->whereIn('financial_account_id', $accountIds));
        }

        $flows = [];

        foreach ($query->get() as $invoice) {
            $invoiceTotal = (float) $invoice->total();
            if ($invoiceTotal <= 0) {
                continue;
            }

            if ($invoice->paid_at !== null) {
                // Fully paid: single outflow on paid_at
                if ($invoice->paid_at->between($startDate, $endDate)) {
                    $dateStr = $invoice->paid_at->format('Y-m-d');
                    $flows[$dateStr] = ($flows[$dateStr] ?? 0) + $invoiceTotal;
                }
            } else {
                // Unpaid or partial: remaining balance hits on due_date
                // The partial payment_transaction (no invoice_id) is already captured in the main cashFlows query
                $remaining = $invoiceTotal - (float) $invoice->amount_paid;
                if ($remaining > 0 && $invoice->due_date->between($startDate, $endDate)) {
                    $dateStr = $invoice->due_date->format('Y-m-d');
                    $flows[$dateStr] = ($flows[$dateStr] ?? 0) + $remaining;
                }
            }
        }

        return $flows;
    }
}
