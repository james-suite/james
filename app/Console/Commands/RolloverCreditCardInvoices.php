<?php

namespace App\Console\Commands;

use App\Enums\NotificationLevel;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RolloverCreditCardInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:rollover-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollover credit card invoices for the current day';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cards = FinancialCreditCard::all();
        $today = Carbon::today();

        foreach ($cards as $card) {
            FinancialCreditCardInvoice::resolveForDate($card, $today);
        }

        $this->notifyClosedInvoices($today);

        $this->info('Credit card invoices rolled over successfully.');

        return Command::SUCCESS;
    }

    /**
     * Envia notificações após o último dia de compras da fatura terminar.
     */
    private function notifyClosedInvoices(Carbon $today): void
    {
        $closedInvoices = FinancialCreditCardInvoice::with([
            'creditCard',
            'transactions' => fn ($query) => $query->withoutDrafts(),
        ])
            ->withTotalAmount()
            ->whereDate('closing_date', $today->copy()->subDay())
            ->get();

        if ($closedInvoices->isEmpty()) {
            return;
        }

        $users = User::all();

        foreach ($closedInvoices as $invoice) {
            $totalAmount = $invoice->total();

            if ($totalAmount <= 0) {
                continue;
            }

            $transactionsCount = $invoice->transactions->count();

            $notification = new GeneralNotification(
                title: 'Fatura de Cartão Fechada',
                message: "A fatura do cartão {$invoice->creditCard->name} foi fechada e já está disponível para conferência.",
                actionUrl: route('financial.cards.invoices.show', [
                    'card' => $invoice->financial_credit_card_id,
                    'invoice' => $invoice->id,
                ]),
                level: NotificationLevel::Warning,
                details: [
                    'Cartão' => $invoice->creditCard->name,
                    'Valor Total' => formatCurrency($totalAmount),
                    'Lançamentos' => "{$transactionsCount} ".($transactionsCount === 1 ? 'lançamento' : 'lançamentos'),
                    'Vencimento' => formatShort($invoice->due_date),
                ],
            );

            foreach ($users as $user) {
                $user->notify($notification);
            }

            $this->info("Notificação enviada para fatura do cartão: {$invoice->creditCard->name}");
        }
    }
}
