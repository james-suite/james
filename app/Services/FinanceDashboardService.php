<?php

namespace App\Services;

use App\Enums\FinancialAccountType;
use App\Enums\TransactionStatus;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialRecurrence;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinanceDashboardService
{
    private ?Collection $accountsCache = null;

    private ?Collection $activeRecurrencesCache = null;

    private ?Collection $creditCardsCache = null;

    private function getAccounts(): Collection
    {
        if ($this->accountsCache === null) {
            $this->accountsCache = FinancialAccount::withBalance()->get();
        }

        return $this->accountsCache;
    }

    private function getActiveRecurrences(): Collection
    {
        if ($this->activeRecurrencesCache === null) {
            $this->activeRecurrencesCache = FinancialRecurrence::active()
                ->with(['tags'])
                ->get()
                ->each(function ($r) {
                    if ($r->financial_account_id) {
                        $r->setRelation('financialAccount', $this->getAccounts()->firstWhere('id', $r->financial_account_id));
                    } else {
                        $r->setRelation('financialAccount', null);
                    }

                    if ($r->financial_credit_card_id) {
                        $r->setRelation('financialCreditCard', $this->getCreditCards()->firstWhere('id', $r->financial_credit_card_id));
                    } else {
                        $r->setRelation('financialCreditCard', null);
                    }
                });
        }

        return $this->activeRecurrencesCache;
    }

    private function getCreditCards(): Collection
    {
        if ($this->creditCardsCache === null) {
            $this->creditCardsCache = FinancialCreditCard::withUsedLimit()
                ->with(['invoices' => fn ($q) => $q->withTotalAmount()])
                ->get()
                ->each(function ($card) {
                    if ($card->financial_account_id) {
                        $card->setRelation('financialAccount', $this->getAccounts()->firstWhere('id', $card->financial_account_id));
                    } else {
                        $card->setRelation('financialAccount', null);
                    }
                });
        }

        return $this->creditCardsCache;
    }

    private function getAccountBalance(bool $includeInvestments = false): float
    {
        $accounts = $this->getAccounts();

        if (! $includeInvestments) {
            $accounts = $accounts->where('type', '!=', FinancialAccountType::Investment);
        }

        return (float) $accounts->sum('balance');
    }

    public function getKpiNumbers(bool $includeInvestments = false): array
    {
        $accountBalance = $this->getAccountBalance($includeInvestments);

        $creditCardDebt = $this->getOpenInvoicesTotalForPeriod(null, null, $includeInvestments);

        $otherDebtsQuery = FinancialTransaction::pending()
            ->expenses()
            ->withoutInvoice();

        $this->applyTransferScope($otherDebtsQuery, $includeInvestments);

        $otherDebts = $otherDebtsQuery->sum('amount');

        $finiteRecurrencesDebt = $this->getFiniteExpenseRecurrencesTotal($includeInvestments);

        $netBalance = $accountBalance - $creditCardDebt - $otherDebts - $finiteRecurrencesDebt;

        $totalsQuery = FinancialTransaction::posted();

        $this->applyTransferScope($totalsQuery, $includeInvestments);

        $totals = $totalsQuery->toBase()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense
            ")
            ->first();

        $income = (float) $totals->income;
        $expense = (float) $totals->expense;
        $currentBalance = $accountBalance;

        return [
            'netBalance' => $netBalance,
            'income' => $income,
            'expense' => $expense,
            'currentBalance' => $currentBalance,
        ];
    }

    private function getFiniteExpenseRecurrencesTotal(bool $includeInvestments = false): float
    {
        $recurrences = $this->getActiveRecurrences()
            ->filter(fn (FinancialRecurrence $r): bool => $r->type === 'expense' && $r->end_date !== null);

        if (! $includeInvestments) {
            $recurrences = $recurrences->filter(function (FinancialRecurrence $r): bool {
                if ($r->financialAccount && $r->financialAccount->type === FinancialAccountType::Investment) {
                    return false;
                }

                if ($r->financialCreditCard && $r->financialCreditCard->financialAccount && $r->financialCreditCard->financialAccount->type === FinancialAccountType::Investment) {
                    return false;
                }

                return true;
            });
        }

        return (float) $recurrences->sum(fn (FinancialRecurrence $r): float => $r->remainingAmount());
    }

    public function getCashFlowProjections(Carbon $referenceDate, bool $includeInvestments = false): array
    {
        $startOfMonth = $referenceDate->copy()->startOfMonth();
        $endOfMonth = $referenceDate->copy()->endOfMonth();

        $nextMonthStart = $referenceDate->copy()->addMonth()->startOfMonth();
        $nextMonthEnd = $referenceDate->copy()->addMonth()->endOfMonth();

        $afterNextMonthStart = $referenceDate->copy()->addMonths(2)->startOfMonth();
        $afterNextMonthEnd = $referenceDate->copy()->addMonths(2)->endOfMonth();

        $accountBalance = $this->getAccountBalance($includeInvestments);

        // --- Mês Atual (Projeção) ---
        $pendingCurrent = $this->getPendingTotalsForPeriod($startOfMonth, $endOfMonth, $includeInvestments);
        $openInvoicesCurrent = $this->getOpenInvoicesTotalForPeriod($startOfMonth, $endOfMonth, $includeInvestments);

        // Recorrências do mês atual (ainda não materializadas)
        $recurrencesCurrent = $this->getActiveRecurrences()
            ->filter(function ($r) use ($referenceDate, $endOfMonth) {
                $effectiveDate = $r->financial_credit_card_id && $r->financialCreditCard
                    ? $r->financialCreditCard->resolveInvoiceDueDate($r->next_processing_date)
                    : $r->next_processing_date->copy();

                return $effectiveDate->between($referenceDate, $endOfMonth);
            });

        if (! $includeInvestments) {
            $recurrencesCurrent = $recurrencesCurrent->filter(function ($r) {
                if ($r->financialAccount && $r->financialAccount->type === FinancialAccountType::Investment) {
                    return false;
                }
                if ($r->financialCreditCard && $r->financialCreditCard->financialAccount && $r->financialCreditCard->financialAccount->type === FinancialAccountType::Investment) {
                    return false;
                }

                return true;
            });
        }

        $recurrencesIncomeCurrent = $recurrencesCurrent->where('type', 'income')->sum('amount');
        $recurrencesExpenseCurrent = $recurrencesCurrent->where('type', 'expense')->sum('amount');

        $projectionCurrentMonth = $accountBalance
            + (float) $pendingCurrent->income + $recurrencesIncomeCurrent
            - (float) $pendingCurrent->expense - $openInvoicesCurrent - $recurrencesExpenseCurrent;

        // --- Próximo Mês (Projeção) ---
        $pendingNext = $this->getPendingTotalsForPeriod($nextMonthStart, $nextMonthEnd, $includeInvestments);
        $openInvoicesNext = $this->getOpenInvoicesTotalForPeriod($nextMonthStart, $nextMonthEnd, $includeInvestments);

        // Recorrências do próximo mês (filtradas do cache)
        $recurrencesNext = $this->getActiveRecurrences()
            ->filter(function ($r) use ($nextMonthStart, $nextMonthEnd) {
                $effectiveDate = $r->financial_credit_card_id && $r->financialCreditCard
                    ? $r->financialCreditCard->resolveInvoiceDueDate($r->next_processing_date)
                    : $r->next_processing_date->copy();

                return $effectiveDate->between($nextMonthStart, $nextMonthEnd)
                    || ($effectiveDate->lt($nextMonthStart)
                        && ($r->end_date === null || $r->end_date->gte($nextMonthEnd)));
            });

        if (! $includeInvestments) {
            $recurrencesNext = $recurrencesNext->filter(function ($r) {
                if ($r->financialAccount && $r->financialAccount->type === FinancialAccountType::Investment) {
                    return false;
                }
                if ($r->financialCreditCard && $r->financialCreditCard->financialAccount && $r->financialCreditCard->financialAccount->type === FinancialAccountType::Investment) {
                    return false;
                }

                return true;
            });
        }

        $recurrencesIncomeNext = $recurrencesNext->where('type', 'income')->sum('amount');
        $recurrencesExpenseNext = $recurrencesNext->where('type', 'expense')->sum('amount');

        $projectionNextMonth = $projectionCurrentMonth
            + (float) $pendingNext->income + $recurrencesIncomeNext
            - (float) $pendingNext->expense - $openInvoicesNext - $recurrencesExpenseNext;

        // --- Dois Meses à Frente (Projeção) ---
        $pendingAfterNext = $this->getPendingTotalsForPeriod($afterNextMonthStart, $afterNextMonthEnd, $includeInvestments);
        $openInvoicesAfterNext = $this->getOpenInvoicesTotalForPeriod($afterNextMonthStart, $afterNextMonthEnd, $includeInvestments);

        $recurrencesAfterNext = $this->getActiveRecurrences()
            ->filter(function ($r) use ($afterNextMonthStart, $afterNextMonthEnd) {
                $effectiveDate = $r->financial_credit_card_id && $r->financialCreditCard
                    ? $r->financialCreditCard->resolveInvoiceDueDate($r->next_processing_date)
                    : $r->next_processing_date->copy();

                return $effectiveDate->between($afterNextMonthStart, $afterNextMonthEnd)
                    || ($effectiveDate->lt($afterNextMonthStart)
                        && ($r->end_date === null || $r->end_date->gte($afterNextMonthEnd)));
            });

        if (! $includeInvestments) {
            $recurrencesAfterNext = $recurrencesAfterNext->filter(function ($r) {
                if ($r->financialAccount && $r->financialAccount->type === FinancialAccountType::Investment) {
                    return false;
                }
                if ($r->financialCreditCard && $r->financialCreditCard->financialAccount && $r->financialCreditCard->financialAccount->type === FinancialAccountType::Investment) {
                    return false;
                }

                return true;
            });
        }

        $recurrencesIncomeAfterNext = $recurrencesAfterNext->where('type', 'income')->sum('amount');
        $recurrencesExpenseAfterNext = $recurrencesAfterNext->where('type', 'expense')->sum('amount');

        $projectionAfterNextMonth = $projectionNextMonth
            + (float) $pendingAfterNext->income + $recurrencesIncomeAfterNext
            - (float) $pendingAfterNext->expense - $openInvoicesAfterNext - $recurrencesExpenseAfterNext;

        return [
            'currentMonth' => $projectionCurrentMonth,
            'nextMonth' => $projectionNextMonth,
            'afterNextMonth' => $projectionAfterNextMonth,
        ];
    }

    private function getPendingTotalsForPeriod(Carbon $start, Carbon $end, bool $includeInvestments): object
    {
        $query = FinancialTransaction::forPeriod($start, $end)
            ->pending()
            ->withoutInvoice();

        $this->applyTransferScope($query, $includeInvestments);

        return $query->toBase()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense
            ")
            ->first();
    }

    private function getOpenInvoicesTotalForPeriod(?Carbon $start = null, ?Carbon $end = null, bool $includeInvestments = false): float
    {
        $query = FinancialCreditCardInvoice::unpaid()->withTotalAmount();

        if (! $includeInvestments) {
            $query->withoutInvestments();
        }

        if ($start && $end) {
            $query->dueBetween($start, $end);
        }

        return (float) $query->get()->sum(fn ($invoice) => max(0, $invoice->total() - $invoice->amount_paid));
    }

    public function getAccountBalancesChart(?array $accountIds = null, bool $includeInvestments = false): array
    {
        $accounts = $this->getAccounts();

        if ($accountIds) {
            $accounts = $accounts->whereIn('id', $accountIds);
        }

        if (! $includeInvestments) {
            $accounts = $accounts->where('type', '!=', FinancialAccountType::Investment);
        }

        return $accounts
            ->filter(fn ($account) => $account->balance > 0)
            ->map(fn ($account) => [
                'value' => round($account->balance, 2),
                'name' => $account->name,
            ])
            ->values()
            ->toArray();
    }

    public function getCreditCardsWidget(Carbon $referenceDate, bool $includeInvestments = false): Collection
    {
        $creditCards = $this->getCreditCards();

        if (! $includeInvestments) {
            $creditCards = $creditCards->filter(
                fn ($card) => $card->financialAccount?->type !== FinancialAccountType::Investment
            );
        }

        return $creditCards
            ->values()
            ->each(fn ($card) => $card->setCurrentInvoice($referenceDate));
    }

    public function getJamesRadar(Carbon $referenceDate, bool $includeInvestments = false): Collection
    {
        $endDate = $referenceDate->copy()->addMonthNoOverflow();

        $pendingTransactionsQuery = FinancialTransaction::pending()
            ->forPeriod($referenceDate, $endDate)
            ->withoutInvoice()
            ->expenses()
            ->withoutTransfers()
            ->with(['invoice.creditCard', 'media', 'tags']);

        if (! $includeInvestments) {
            $pendingTransactionsQuery->withoutInvestments();
        }

        $pendingTransactions = $pendingTransactionsQuery
            ->get()
            ->each(function ($t) {
                if ($t->financial_account_id) {
                    $t->setRelation('account', $this->getAccounts()->firstWhere('id', $t->financial_account_id));
                } else {
                    $t->setRelation('account', null);
                }

                if (! $t->financial_credit_card_invoice_id) {
                    $t->setRelation('invoice', null);
                }
            });

        $recurrences = $this->getActiveRecurrences()
            ->filter(fn ($r) => $r->next_processing_date->between($referenceDate, $endDate))
            ->when(! $includeInvestments, fn (Collection $recurrences) => $recurrences->filter(function ($recurrence) {
                return $recurrence->financialAccount?->type !== FinancialAccountType::Investment
                    && $recurrence->financialCreditCard?->financialAccount?->type !== FinancialAccountType::Investment;
            }))
            ->map(function ($r) {
                $t = new FinancialTransaction([
                    'description' => $r->title,
                    'amount' => $r->amount,
                    'type' => $r->type,
                    'date' => $r->next_processing_date,
                    'status' => TransactionStatus::Pending,
                ]);
                $t->is_recurrence = true;
                $t->recurrence_id = $r->id;
                $t->setRelation('tags', $r->tags);

                if ($r->financial_credit_card_id) {
                    $fakeInvoice = new FinancialCreditCardInvoice;
                    $fakeInvoice->setRelation('creditCard', $r->relationLoaded('financialCreditCard') ? $r->financialCreditCard : null);
                    $t->setRelation('invoice', $fakeInvoice);
                    $t->setRelation('account', null);
                } else {
                    $t->setRelation('invoice', null);
                    $t->setRelation('account', $r->relationLoaded('financialAccount') ? $r->financialAccount : null);
                }

                return $t;
            });

        $openInvoicesQuery = FinancialCreditCardInvoice::withTotalAmount()
            ->dueBetween($referenceDate, $endDate)
            ->unpaid();

        if (! $includeInvestments) {
            $openInvoicesQuery->withoutInvestments();
        }

        $openInvoices = $openInvoicesQuery
            ->get()
            ->map(function ($inv) {
                $inv->setRelation('creditCard', $this->getCreditCards()->firstWhere('id', $inv->financial_credit_card_id));
                $t = new FinancialTransaction([
                    'description' => 'Fatura '.($inv->creditCard?->name ?? 'Cartão'),
                    'amount' => max(0, $inv->total() - $inv->amount_paid),
                    'type' => 'expense',
                    'date' => $inv->due_date,
                    'status' => TransactionStatus::Pending,
                ]);
                $t->is_invoice = true;
                $t->setRelation('tags', collect());
                $t->setRelation('invoice', $inv);
                $t->setRelation('account', null);

                return $t;
            });

        return $pendingTransactions->concat($recurrences)->concat($openInvoices)->sortBy('date')->values();
    }

    public function getTopExpenseTags(Carbon $referenceDate, bool $includeInvestments = false): array
    {
        $startDate = $referenceDate->copy()->subDays(30);
        $endDate = $referenceDate->copy();

        $expensesQuery = FinancialTransaction::forPeriod($startDate, $endDate)
            ->withoutDrafts()
            ->where(function ($q) {
                $q->where('status', TransactionStatus::Posted)
                    ->orWhereNotNull('financial_credit_card_invoice_id');
            })
            ->expenses()
            ->withoutTransfers()
            ->with([
                'tags' => fn ($q) => $q->wherePivot('is_primary', true),
                'items.tags' => fn ($q) => $q->wherePivot('is_primary', true),
            ]);

        if (! $includeInvestments) {
            $expensesQuery->withoutInvestments();
        }

        $expenses = $expensesQuery->get();

        $flattened = collect();

        foreach ($expenses as $t) {
            if ($t->relationLoaded('items') && $t->items->isNotEmpty()) {
                $itemsSum = 0;
                foreach ($t->items as $item) {
                    $itemAmount = $item->unit_price * $item->quantity;
                    $itemsSum += $itemAmount;

                    $flattened->push((object) [
                        'amount' => $itemAmount,
                        'tags' => $item->tags,
                    ]);
                }

                $remainingAmount = $t->amount - $itemsSum;
                if ($remainingAmount > 0.01) {
                    $flattened->push((object) [
                        'amount' => $remainingAmount,
                        'tags' => $t->tags,
                    ]);
                }
            } else {
                $flattened->push((object) [
                    'amount' => $t->amount,
                    'tags' => $t->tags,
                ]);
            }
        }

        $totalExpenses = $flattened->sum('amount');

        $grouped = $flattened->groupBy(function ($transaction) {
            $primaryTag = $transaction->tags->first();

            return $primaryTag ? $primaryTag->id : 0;
        });

        return $grouped->map(function ($transactions) use ($totalExpenses) {
            $tag = $transactions->first()->tags->first();
            $value = (float) $transactions->sum('amount');

            return [
                'name' => $tag ? $tag->name : 'Sem Categoria',
                'value' => $value,
                'percentage' => $totalExpenses > 0 ? round(($value / $totalExpenses) * 100, 1) : 0,
                'color' => $tag ? $tag->color_hex : '#9ca3af',
                'icon' => $tag ? $tag->icon : 'heroicon-o-tag',
            ];
        })->sortByDesc('value')->take(5)->values()->toArray();
    }

    public function getRecentTransactions(bool $includeInvestments = false): Collection
    {
        $transactionsQuery = FinancialTransaction::with(['invoice', 'media', 'recurrence', 'tags'])
            ->withoutDrafts()
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(10);

        if (! $includeInvestments) {
            $transactionsQuery->withoutInvestments();
        }

        $transactions = $transactionsQuery->get();

        $transactions->each(function ($t) {
            $t->setRelation('account', $t->financial_account_id ? $this->getAccounts()->firstWhere('id', $t->financial_account_id) : null);

            if ($t->relationLoaded('invoice') && $t->invoice) {
                $t->invoice->setRelation('creditCard', $t->invoice->financial_credit_card_id ? $this->getCreditCards()->firstWhere('id', $t->invoice->financial_credit_card_id) : null);
            }
        });

        return $transactions;
    }

    public function getNetWorthChartData(string $period, bool $includeInvestments = false): array
    {
        $today = Carbon::today();
        $endDate = $today->copy()->addMonthNoOverflow();

        $startDate = match ($period) {
            '1m' => $today->copy()->subMonth(),
            '3m' => $today->copy()->subMonths(3),
            '6m' => $today->copy()->subMonths(6),
            '1y' => $today->copy()->subYear(),
            'all' => Carbon::parse(FinancialTransaction::withoutDrafts()->min('date') ?? $today->copy()->subYears(5)),
            default => $today->copy()->subMonth(),
        };

        $initialBalanceQuery = FinancialTransaction::posted()
            ->whereNull('financial_credit_card_invoice_id')
            ->where('date', '<', $startDate);

        $this->applyTransferScope($initialBalanceQuery, $includeInvestments);

        // Get initial balance before start date (non-CC transactions)
        $initialBalance = $initialBalanceQuery
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount WHEN type = 'expense' THEN -amount ELSE 0 END), 0) as balance")
            ->value('balance') ?? 0;

        // Add invoice cash flows that settled before startDate
        $initialBalance += $this->getInvoiceInitialBalance($startDate, $includeInvestments);

        $flowsQuery = FinancialTransaction::posted()
            ->whereNull('financial_credit_card_invoice_id')
            ->forPeriod($startDate, $today);

        $this->applyTransferScope($flowsQuery, $includeInvestments);

        // Get flows during the period
        $flows = $flowsQuery
            ->selectRaw("
                date, 
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount WHEN type = 'expense' THEN -amount ELSE 0 END), 0) as net_flow,
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense
            ")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $flowsByDate = $flows->mapWithKeys(function ($item) {
            $dateStr = is_string($item->date) ? substr($item->date, 0, 10) : $item->date->format('Y-m-d');

            return [$dateStr => [
                'net_flow' => (float) $item->net_flow,
                'income' => (float) $item->income,
                'expense' => (float) $item->expense,
            ]];
        })->toArray();

        // Add invoice cash flows for the historical period
        foreach ($this->getInvoicePeriodFlows($startDate, $today, $includeInvestments) as $dateStr => $amount) {
            if (! isset($flowsByDate[$dateStr])) {
                $flowsByDate[$dateStr] = ['net_flow' => 0, 'income' => 0, 'expense' => 0];
            }
            $flowsByDate[$dateStr]['expense'] += $amount;
            $flowsByDate[$dateStr]['net_flow'] -= $amount;
        }

        // Add future projections (pending, recurrences, open invoices)
        $futureTransactions = $this->getJamesRadar($today, $includeInvestments);

        foreach ($futureTransactions as $t) {
            $transactionDate = is_string($t->date) ? Carbon::parse($t->date) : $t->date->copy();

            // For credit card recurrences, shift to the invoice due date
            if (empty($t->is_invoice) && $t->relationLoaded('invoice') && $t->invoice && $t->invoice->relationLoaded('creditCard') && $t->invoice->creditCard) {
                $transactionDate = $t->invoice->creditCard->resolveInvoiceDueDate($transactionDate);
            }

            $dateStr = $transactionDate->format('Y-m-d');

            if ($transactionDate->gt($endDate)) {
                continue;
            }

            if (! isset($flowsByDate[$dateStr])) {
                $flowsByDate[$dateStr] = ['net_flow' => 0, 'income' => 0, 'expense' => 0];
            }

            $amount = (float) $t->amount;
            if ($t->type === 'income') {
                $flowsByDate[$dateStr]['income'] += $amount;
                $flowsByDate[$dateStr]['net_flow'] += $amount;
            } else {
                $flowsByDate[$dateStr]['expense'] += $amount;
                $flowsByDate[$dateStr]['net_flow'] -= $amount;
            }
        }

        $flowsByDate = collect($flowsByDate);

        $chartData = [];
        $runningBalance = (float) $initialBalance;

        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $flowData = $flowsByDate->get($dateStr, ['net_flow' => 0, 'income' => 0, 'expense' => 0]);

            $runningBalance += $flowData['net_flow'];

            $chartData[] = [
                'date' => $dateStr,
                'value' => round($runningBalance, 2),
                'income' => round($flowData['income'], 2),
                'expense' => round($flowData['expense'], 2),
            ];

            $currentDate->addDay();
        }

        return $chartData;
    }

    private function applyTransferScope($query, bool $includeInvestments): void
    {
        if (! $includeInvestments) {
            $query->withoutInvestments();

            $query->where(function ($q) {
                // Not a transfer
                $q->withoutTransfers()
                // OR is a transfer from/to an Investment account (which is currently external to our scope)
                    ->orWhere(function ($transferQuery) {
                        $transferQuery->whereHas('tags', function ($tagQuery) {
                            $tagQuery->where('financial_tags.id', FinancialTag::TRANSFERENCIA_ID);
                        })
                            ->whereIn('transfer_pair_id', function ($sub) {
                                $sub->select('transfer_pair_id')
                                    ->from('financial_transactions')
                                    ->join('financial_accounts', 'financial_transactions.financial_account_id', '=', 'financial_accounts.id')
                                    ->where('financial_accounts.type', FinancialAccountType::Investment->value)
                                    ->whereNotNull('transfer_pair_id');
                            });
                    });
            });
        } else {
            $query->withoutTransfers();
        }
    }

    /**
     * Returns the net invoice cash flow (as negative, i.e. expense) that settled before $startDate.
     * Used to build the initial balance for the chart.
     */
    private function getInvoiceInitialBalance(Carbon $startDate, bool $includeInvestments): float
    {
        $query = FinancialCreditCardInvoice::withTotalAmount();

        if (! $includeInvestments) {
            $query->withoutInvestments();
        }

        $total = 0.0;

        foreach ($query->get() as $invoice) {
            $invoiceTotal = (float) $invoice->total();

            if ($invoiceTotal <= 0) {
                continue;
            }

            if ($invoice->paid_at !== null) {
                // Fully paid: cash out on paid_at
                if ($invoice->paid_at->lt($startDate)) {
                    $total -= $invoiceTotal;
                }
            } else {
                // Unpaid or partial: cash out (remaining) on due_date
                $remaining = $invoiceTotal - (float) $invoice->amount_paid;
                if ($remaining > 0 && $invoice->due_date->lt($startDate)) {
                    $total -= $remaining;
                }
            }
        }

        return $total;
    }

    /**
     * Returns invoice cash flows (date => amount) for invoices that settle within [$startDate, $endDate].
     * Amount is positive (expense to be subtracted from balance).
     */
    private function getInvoicePeriodFlows(Carbon $startDate, Carbon $endDate, bool $includeInvestments): array
    {
        $query = FinancialCreditCardInvoice::withTotalAmount();

        if (! $includeInvestments) {
            $query->withoutInvestments();
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
                // The partial payment itself is already captured as a payment_transaction (no invoice_id)
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
