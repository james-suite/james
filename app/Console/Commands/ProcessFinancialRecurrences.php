<?php

namespace App\Console\Commands;

use App\Enums\NotificationLevel;
use App\Enums\TransactionStatus;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialRecurrence;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

class ProcessFinancialRecurrences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:process-recurrences';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processa as recorrências financeiras e gera as transações.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();

        $this->info("Iniciando processamento de recorrências para: {$today->toDateString()}");
        $recurrenceIds = FinancialRecurrence::query()
            ->where('is_active', true)
            ->where('next_processing_date', '<=', $today)
            ->pluck('id');

        $processedCount = 0;
        $totalAmount = 0.0;
        $processedItems = [];
        $failedItems = [];

        foreach ($recurrenceIds as $recurrenceId) {
            while (true) {
                $processed = $this->processNextOccurrence($recurrenceId, $today);

                if ($processed === null) {
                    break;
                }

                if ($processed['status'] === 'failed') {
                    $failedItems[] = [
                        'title' => $processed['title'],
                        'date' => $processed['date'],
                    ];

                    break;
                }

                if ($processed['status'] !== 'created') {
                    continue;
                }

                $processedCount++;
                $totalAmount += $processed['amount'];

                $processedItems[] = [
                    'title' => $processed['title'],
                    'amount' => $processed['amount'],
                    'destination' => $processed['destination'],
                ];
            }
        }

        $this->info("Processamento concluído. Transações geradas: {$processedCount}");

        if ($processedCount > 0) {
            $this->notifyRecurrencesProcessed($processedCount, $totalAmount, $processedItems);
        }

        if ($failedItems !== []) {
            $this->notifyRecurrenceFailures($failedItems);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function calculateNextDate(FinancialRecurrence $recurrence, Carbon $lastProcessedDate): Carbon
    {
        if ($recurrence->frequency === 'monthly') {
            $monthsSinceStart = (($lastProcessedDate->year - $recurrence->start_date->year) * 12)
                + $lastProcessedDate->month
                - $recurrence->start_date->month
                + 1;
            $nextDate = $recurrence->start_date->copy()->addMonthsNoOverflow($monthsSinceStart);

            while ($nextDate->lte($lastProcessedDate)) {
                $monthsSinceStart++;
                $nextDate = $recurrence->start_date->copy()->addMonthsNoOverflow($monthsSinceStart);
            }

            return $nextDate;
        }

        if ($recurrence->frequency === 'yearly') {
            $yearsSinceStart = $lastProcessedDate->year - $recurrence->start_date->year + 1;
            $nextDate = $recurrence->start_date->copy()->addYearsNoOverflow($yearsSinceStart);

            while ($nextDate->lte($lastProcessedDate)) {
                $yearsSinceStart++;
                $nextDate = $recurrence->start_date->copy()->addYearsNoOverflow($yearsSinceStart);
            }

            return $nextDate;
        }

        throw new LogicException("Frequência de recorrência não suportada: {$recurrence->frequency}");
    }

    /**
     * @return array{status: 'created'|'already_processed'|'failed', title: string, amount: float, destination: string, date: string}|null
     */
    private function processNextOccurrence(int $recurrenceId, Carbon $today): ?array
    {
        try {
            $processed = DB::transaction(function () use ($recurrenceId, $today): ?array {
                $recurrence = FinancialRecurrence::query()
                    ->with(['financialAccount', 'financialCreditCard', 'tags'])
                    ->lockForUpdate()
                    ->find($recurrenceId);

                if (! $recurrence || ! $recurrence->is_active) {
                    return null;
                }

                $date = $recurrence->next_processing_date->copy();

                if ($date->gt($today) || ($recurrence->end_date && $date->gt($recurrence->end_date))) {
                    return null;
                }

                $nextDate = $this->calculateNextDate($recurrence, $date);
                $alreadyProcessed = FinancialTransaction::withTrashed()
                    ->where('financial_recurrence_id', $recurrence->id)
                    ->whereDate('date', $date)
                    ->exists();

                if ($alreadyProcessed) {
                    $recurrence->update(['next_processing_date' => $nextDate]);

                    return [
                        'status' => 'already_processed',
                        'title' => $recurrence->title,
                        'amount' => (float) $recurrence->amount,
                        'destination' => $this->destinationFor($recurrence),
                        'date' => $date->toDateString(),
                    ];
                }

                if ($recurrence->financial_credit_card_id) {
                    $invoice = FinancialCreditCardInvoice::resolveForDate($recurrence->financialCreditCard, $date);
                    $transaction = $invoice->transactions()->create([
                        'financial_account_id' => null,
                        'date' => $date,
                        'type' => $recurrence->type,
                        'amount' => $recurrence->amount,
                        'description' => $recurrence->title,
                        'status' => TransactionStatus::Pending,
                        'financial_recurrence_id' => $recurrence->id,
                    ]);
                } elseif ($recurrence->financial_account_id) {
                    $transaction = $recurrence->financialAccount->transactions()->create([
                        'date' => $date,
                        'type' => $recurrence->type,
                        'amount' => $recurrence->amount,
                        'description' => $recurrence->title,
                        'status' => TransactionStatus::Posted,
                        'financial_recurrence_id' => $recurrence->id,
                    ]);
                } else {
                    throw new LogicException("Recorrência {$recurrence->id} não possui destino financeiro.");
                }

                $syncData = $recurrence->tags
                    ->mapWithKeys(fn (FinancialTag $tag): array => [$tag->id => ['is_primary' => (bool) $tag->pivot->is_primary]])
                    ->all();

                if ($syncData !== []) {
                    $transaction->tags()->sync($syncData);
                }

                $recurrence->update(['next_processing_date' => $nextDate]);

                return [
                    'status' => 'created',
                    'title' => $recurrence->title,
                    'amount' => (float) $recurrence->amount,
                    'destination' => $this->destinationFor($recurrence),
                    'date' => $date->toDateString(),
                ];
            }, 3);

            if ($processed && $processed['status'] === 'created') {
                $this->info("Recorrência '{$processed['title']}' processada para {$processed['date']}");
            }

            return $processed;
        } catch (Throwable $exception) {
            $failedRecurrence = FinancialRecurrence::withTrashed()->find($recurrenceId);
            $title = $failedRecurrence?->title ?? "Recorrência #{$recurrenceId}";
            $date = $failedRecurrence?->next_processing_date?->toDateString() ?? $today->toDateString();

            Log::error("Erro ao processar recorrência ID {$recurrenceId}: {$exception->getMessage()}", [
                'exception' => $exception,
            ]);
            $this->error("Erro ao processar recorrência ID {$recurrenceId}");

            return [
                'status' => 'failed',
                'title' => $title,
                'amount' => 0.0,
                'destination' => '',
                'date' => $date,
            ];
        }
    }

    private function destinationFor(FinancialRecurrence $recurrence): string
    {
        if ($recurrence->financialCreditCard) {
            return "Cartão {$recurrence->financialCreditCard->name}";
        }

        return $recurrence->financialAccount ? "Conta {$recurrence->financialAccount->name}" : '';
    }

    /**
     * Envia notificação detalhada informando quais recorrências foram geradas no período.
     *
     * @param  array<int, array{title: string, amount: float, destination: string}>  $processedItems
     */
    private function notifyRecurrencesProcessed(int $count, float $totalAmount, array $processedItems): void
    {
        $details = [
            'Quantidade' => "{$count} ".($count !== 1 ? 'lançamentos' : 'lançamento'),
            'Valor Total' => formatCurrency($totalAmount),
        ];

        foreach ($processedItems as $index => $item) {
            $dest = $item['destination'] ? " ({$item['destination']})" : '';
            $key = ($index + 1).'. '.$item['title'];
            $details[$key] = formatCurrency($item['amount']).$dest;
        }

        $notification = new GeneralNotification(
            title: 'Recorrências Processadas',
            message: 'As transações recorrentes do período foram geradas com sucesso.',
            actionUrl: route('financial.transactions.index'),
            level: NotificationLevel::Info,
            details: $details,
        );

        User::query()->each(function (User $user) use ($notification): void {
            $user->notify($notification);
        });
    }

    /**
     * Notify users when one or more recurrences could not be processed.
     *
     * @param  array<int, array{title: string, date: string}>  $failedItems
     */
    private function notifyRecurrenceFailures(array $failedItems): void
    {
        $details = [
            'Quantidade' => count($failedItems).' '.(count($failedItems) === 1 ? 'falha' : 'falhas'),
        ];

        foreach ($failedItems as $index => $item) {
            $details[($index + 1).'. '.$item['title']] = 'Data: '.$item['date'];
        }

        $notification = new GeneralNotification(
            title: 'Falha no processamento de recorrências',
            message: 'Algumas transações recorrentes não foram geradas. O sistema tentará processá-las novamente na próxima execução.',
            actionUrl: route('financial.recurrences.index'),
            level: NotificationLevel::Danger,
            details: $details,
        );

        User::query()->each(function (User $user) use ($notification): void {
            $user->notify($notification);
        });
    }
}
