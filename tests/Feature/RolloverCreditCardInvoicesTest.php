<?php

use App\Enums\InvoiceStatus;
use App\Enums\NotificationLevel;
use App\Enums\TransactionStatus;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-08 08:00:00'));
    $this->user = User::factory()->create();
    Notification::fake();
});

it('notifies users the day after an invoice closing date', function () {
    $card = FinancialCreditCard::factory()->create();

    $invoice = FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create([
        'closing_date' => Carbon::today()->subDay(),
        'due_date' => Carbon::today()->addDays(15),
        'reference_month' => Carbon::today()->startOfMonth(),
    ]);

    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'type' => 'expense',
        'amount' => 250.00,
        'date' => Carbon::today()->subDay(),
        'status' => TransactionStatus::Pending,
    ]);

    $this->artisan('finance:rollover-invoices')->assertSuccessful();

    Notification::assertSentTo($this->user, GeneralNotification::class, function ($notification) {
        return $notification->title === 'Fatura de Cartão Fechada'
            && $notification->level === NotificationLevel::Warning
            && $notification->details['Lançamentos'] === '1 lançamento'
            && str_contains($notification->details['Valor Total'], '250,00');
    });
});

it('does not notify again for invoices whose closing date is more than one day ago', function () {
    $card = FinancialCreditCard::factory()->create(['closing_day' => 25]);

    FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create([
        'closing_date' => Carbon::today()->subDays(2),
        'due_date' => Carbon::today()->addDays(20),
        'reference_month' => Carbon::today()->subMonth()->startOfMonth(),
    ]);

    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $card->invoices()->firstOrFail()->id,
        'type' => 'expense',
        'amount' => 100,
        'status' => TransactionStatus::Pending,
    ]);

    $this->artisan('finance:rollover-invoices')->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not notify for invoices with zero balance', function () {
    $card = FinancialCreditCard::factory()->create();

    // Invoice with no transactions (total = 0)
    FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create([
        'closing_date' => Carbon::today()->subDay(),
        'due_date' => Carbon::today()->addDays(15),
        'reference_month' => Carbon::today()->startOfMonth(),
    ]);

    $this->artisan('finance:rollover-invoices')->assertSuccessful();

    Notification::assertNothingSent();
});

it('rolls over and notifies only after the configured or edited closing date ends', function (int $closingDay, string $closingDate) {
    $this->travelTo(Carbon::parse($closingDate)->setTime(8, 0));
    $card = FinancialCreditCard::factory()->create(['closing_day' => $closingDay, 'due_day' => 20]);
    $invoice = FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create([
        'closing_date' => $closingDate,
        'due_date' => Carbon::parse($closingDate)->addDays(15),
        'reference_month' => Carbon::parse($closingDate)->startOfMonth(),
    ]);
    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'type' => 'expense',
        'amount' => 250,
        'date' => $closingDate,
        'status' => TransactionStatus::Pending,
    ]);

    $this->artisan('finance:rollover-invoices')->assertSuccessful();

    expect($invoice->status())->toBe(InvoiceStatus::Open)
        ->and($card->invoices()->count())->toBe(1);
    Notification::assertNothingSent();

    $this->travelTo(Carbon::parse($closingDate)->endOfDay());
    expect($invoice->status())->toBe(InvoiceStatus::Open)
        ->and(FinancialCreditCardInvoice::resolveForDate($card, now())->id)->toBe($invoice->id);

    $this->travelTo(Carbon::parse($closingDate)->addDay()->startOfDay());
    $this->artisan('finance:rollover-invoices')->assertSuccessful();

    expect($invoice->status())->toBe(InvoiceStatus::Closed)
        ->and($card->invoices()->count())->toBe(2);
    Notification::assertSentToTimes($this->user, GeneralNotification::class, 1);
    Notification::assertSentTo($this->user, GeneralNotification::class, function (GeneralNotification $notification) use ($card, $invoice): bool {
        return $notification->actionUrl === route('financial.cards.invoices.show', ['card' => $card->id, 'invoice' => $invoice->id]);
    });
})->with([
    'configured day 7' => [7, '2026-10-07'],
    'configured day 18' => [18, '2026-10-18'],
    'edited to an earlier date' => [15, '2026-10-06'],
    'edited to a later date' => [7, '2026-10-12'],
    'last day of a short month' => [31, '2026-02-28'],
    'last day of the year' => [31, '2026-12-31'],
]);
