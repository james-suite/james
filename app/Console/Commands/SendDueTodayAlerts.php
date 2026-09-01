<?php

namespace App\Console\Commands;

use App\Enums\NotificationLevel;
use App\Enums\TransactionStatus;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialRecurrence;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Notifications\DueTodayNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SendDueTodayAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:due-today-alerts {--force : Reenvia o alerta mesmo que ele já tenha sido enviado hoje}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia alertas sobre receitas, despesas, recorrências e faturas para hoje e amanhã.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $dueTransactions = $this->getDueTransactions($today, $tomorrow);
        $dueInvoices = $this->getDueInvoices($today, $tomorrow);
        $dueRecurrences = $this->getDueRecurrences($today, $tomorrow);

        $items = $this->buildItems($dueTransactions, $dueInvoices, $dueRecurrences);

        if ($items->isEmpty()) {
            $this->info('Nenhum vencimento encontrado para hoje ou amanhã. Notificação não enviada.');

            return Command::SUCCESS;
        }

        $totalIncome = (float) $items->where('type', 'income')->sum('amount');
        $totalExpense = (float) $items->where('type', 'expense')->sum('amount');
        $netImpact = $totalIncome - $totalExpense;

        $this->info("Encontrados {$items->count()} vencimento(s). Enviando notificações.");

        $alert = $this->buildAlert($items, $today, $tomorrow, $totalIncome, $totalExpense, $netImpact);

        $notification = new DueTodayNotification(
            alert: $alert,
            level: NotificationLevel::Warning,
        );

        $sentNotifications = 0;
        $alertKey = "due-alert:{$today->toDateString()}";

        User::query()->each(function (User $user) use ($notification, $alertKey, &$sentNotifications): void {
            $cacheKey = $this->notificationCacheKey($user, $alertKey);

            if (! $this->option('force') && ! Cache::add($cacheKey, true, now()->addDays(3))) {
                return;
            }

            if ($this->option('force')) {
                Cache::put($cacheKey, true, now()->addDays(3));
            }

            try {
                $user->notify($notification);
                $sentNotifications++;
            } catch (Throwable $exception) {
                Cache::forget($cacheKey);

                throw $exception;
            }
        });

        $this->info("{$sentNotifications} alerta(s) de vencimento enviado(s).");

        return Command::SUCCESS;
    }

    /**
     * Retorna os lançamentos pendentes do período e os já efetivados com data de hoje.
     *
     * @return EloquentCollection<int, FinancialTransaction>
     */
    private function getDueTransactions(Carbon $startDate, Carbon $endDate): EloquentCollection
    {
        return FinancialTransaction::with(['account', 'recurrence'])
            ->withoutDrafts()
            ->withoutTransfers()
            ->withoutInvoice()
            ->whereBetween('date', [$startDate, $endDate])
            ->where(function ($query) use ($startDate): void {
                $query->where('status', TransactionStatus::Pending)
                    ->orWhere(function ($query) use ($startDate): void {
                        $query->where('status', TransactionStatus::Posted)
                            ->whereDate('date', $startDate);
                    });
            })
            ->orderBy('date')
            ->orderBy('description')
            ->get();
    }

    /**
     * Retorna as faturas de cartão não pagas com vencimento no período com dados estruturados.
     *
     * @return array<int, array{card_name: string, remaining: float, due_date: Carbon, transactions_count: int, recurrences_count: int}>
     */
    private function getDueInvoices(Carbon $startDate, Carbon $endDate): array
    {
        $invoices = FinancialCreditCardInvoice::with([
            'creditCard',
            'transactions' => fn ($query) => $query->withoutDrafts(),
        ])
            ->withTotalAmount()
            ->unpaid()
            ->dueBetween($startDate, $endDate)
            ->orderBy('due_date')
            ->get();

        $result = [];

        foreach ($invoices as $invoice) {
            $remaining = $invoice->total() - (float) $invoice->amount_paid;

            if ($remaining > 0) {
                $result[] = [
                    'card_name' => $invoice->creditCard?->name ?? 'Cartão',
                    'remaining' => $remaining,
                    'due_date' => $invoice->due_date,
                    'transactions_count' => $invoice->transactions->count(),
                    'recurrences_count' => $invoice->transactions
                        ->whereNotNull('financial_recurrence_id')
                        ->count(),
                ];
            }
        }

        return $result;
    }

    /**
     * Retorna recorrências de conta que ainda não foram materializadas para a próxima ocorrência.
     *
     * Recorrências de cartão são apresentadas na fatura correspondente para não duplicar o valor.
     *
     * @return EloquentCollection<int, FinancialRecurrence>
     */
    private function getDueRecurrences(Carbon $startDate, Carbon $endDate): EloquentCollection
    {
        return FinancialRecurrence::query()
            ->active()
            ->whereNotNull('financial_account_id')
            ->nextProcessingBetween($startDate, $endDate)
            ->where(function ($query): void {
                $query->whereNull('end_date')
                    ->orWhereColumn('end_date', '>=', 'next_processing_date');
            })
            ->whereDoesntHave('transactions', function ($query): void {
                $query->whereColumn('financial_transactions.date', 'financial_recurrences.next_processing_date');
            })
            ->with('financialAccount')
            ->orderBy('next_processing_date')
            ->orderBy('title')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, FinancialTransaction>  $transactions
     * @param  array<int, array{card_name: string, remaining: float, due_date: Carbon, transactions_count: int, recurrences_count: int}>  $invoices
     * @param  EloquentCollection<int, FinancialRecurrence>  $recurrences
     * @return Collection<int, array{date: Carbon, type: string, description: string, amount: float, destination: string, is_recurrence: bool, is_invoice: bool, transactions_count?: int, recurrences_count?: int}>
     */
    private function buildItems(Collection $transactions, array $invoices, Collection $recurrences): Collection
    {
        $transactionItems = $transactions->map(function (FinancialTransaction $transaction): array {
            return [
                'date' => $transaction->date,
                'type' => $transaction->type,
                'description' => $transaction->description,
                'amount' => (float) $transaction->amount,
                'destination' => $transaction->account?->name ?? 'Conta',
                'is_recurrence' => $transaction->financial_recurrence_id !== null,
                'is_invoice' => false,
            ];
        });

        $recurrenceItems = $recurrences->map(function (FinancialRecurrence $recurrence): array {
            return [
                'date' => $recurrence->next_processing_date,
                'type' => $recurrence->type,
                'description' => $recurrence->title,
                'amount' => (float) $recurrence->amount,
                'destination' => $recurrence->financialAccount?->name ?? 'Conta',
                'is_recurrence' => true,
                'is_invoice' => false,
            ];
        });

        $invoiceItems = collect($invoices)->map(function (array $invoice): array {
            return [
                'date' => $invoice['due_date'],
                'type' => 'expense',
                'description' => "Fatura {$invoice['card_name']}",
                'amount' => $invoice['remaining'],
                'destination' => $invoice['card_name'],
                'is_recurrence' => false,
                'is_invoice' => true,
                'transactions_count' => $invoice['transactions_count'],
                'recurrences_count' => $invoice['recurrences_count'],
            ];
        });

        return $transactionItems
            ->concat($recurrenceItems)
            ->concat($invoiceItems)
            ->sort(function (array $first, array $second): int {
                return [$first['date']->toDateString(), $first['description']] <=> [$second['date']->toDateString(), $second['description']];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array{date: Carbon, type: string, description: string, amount: float, destination: string, is_recurrence: bool, is_invoice: bool, transactions_count?: int, recurrences_count?: int}>  $items
     * @return array{alert_date: string, total_items: int, income: float, expense: float, net: float, days: list<array{key: string, label: string, date: string, incomes: list<array<string, mixed>>, expenses: list<array<string, mixed>>, invoices: list<array<string, mixed>>}>}
     */
    private function buildAlert(
        Collection $items,
        Carbon $today,
        Carbon $tomorrow,
        float $totalIncome,
        float $totalExpense,
        float $netImpact,
    ): array {
        $days = collect([
            ['key' => 'today', 'label' => 'Hoje', 'date' => $today],
            ['key' => 'tomorrow', 'label' => 'Amanhã', 'date' => $tomorrow],
        ])->map(function (array $day) use ($items): array {
            $dayItems = $items->filter(fn (array $item): bool => $item['date']->isSameDay($day['date']));

            return [
                'key' => $day['key'],
                'label' => $day['label'],
                'date' => formatShort($day['date']),
                'incomes' => $this->serializeItems($dayItems->where('type', 'income')->where('is_invoice', false)),
                'expenses' => $this->serializeItems($dayItems->where('type', 'expense')->where('is_invoice', false)),
                'invoices' => $this->serializeItems($dayItems->where('is_invoice', true)),
            ];
        })->filter(function (array $day): bool {
            return $day['incomes'] !== [] || $day['expenses'] !== [] || $day['invoices'] !== [];
        })->values()->all();

        return [
            'alert_date' => $today->toDateString(),
            'total_items' => $items->count(),
            'income' => $totalIncome,
            'expense' => $totalExpense,
            'net' => $netImpact,
            'days' => $days,
        ];
    }

    /**
     * @param  Collection<int, array{date: Carbon, type: string, description: string, amount: float, destination: string, is_recurrence: bool, is_invoice: bool, transactions_count?: int, recurrences_count?: int}>  $items
     * @return list<array<string, bool|float|int|string>>
     */
    private function serializeItems(Collection $items): array
    {
        return $items->map(fn (array $item): array => [
            'description' => $item['description'],
            'amount' => $item['amount'],
            'destination' => $item['destination'],
            'is_recurrence' => $item['is_recurrence'],
            'is_invoice' => $item['is_invoice'],
            'transactions_count' => $item['transactions_count'] ?? 0,
            'recurrences_count' => $item['recurrences_count'] ?? 0,
        ])->values()->all();
    }

    private function notificationCacheKey(User $user, string $alertKey): string
    {
        return "financial-notification:{$alertKey}:user:{$user->getKey()}";
    }
}
