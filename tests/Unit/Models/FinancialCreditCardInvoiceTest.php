<?php

use App\Enums\InvoiceStatus;
use App\Enums\TransactionStatus;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('calculates total invoice amount correctly', function () {
    $card = FinancialCreditCard::factory()->create();
    $invoice = FinancialCreditCardInvoice::factory()->create(['financial_credit_card_id' => $card->id]);

    // Add 2 expenses and 1 income
    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'type' => 'expense',
        'amount' => 100.50,
        'status' => 'posted',
    ]);

    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'type' => 'expense',
        'amount' => 50.25,
        'status' => 'posted',
    ]);

    // Income inside credit card reduces the invoice total (like cashbacks)
    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'type' => 'income',
        'amount' => 10.00,
        'status' => 'posted',
    ]);

    expect($invoice->transactions)->toHaveCount(3)
        // Total should be 100.50 + 50.25 - 10.00 = 140.75
        ->and($invoice->total())->toEqual(140.75);
});

it('excludes drafts from invoice totals and the card used limit', function () {
    $card = FinancialCreditCard::factory()->create();
    $invoice = FinancialCreditCardInvoice::factory()->create([
        'financial_credit_card_id' => $card->id,
        'paid_at' => null,
    ]);

    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'type' => 'expense',
        'amount' => 75,
        'status' => TransactionStatus::Pending,
    ]);
    $draft = FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'type' => 'expense',
        'amount' => 900,
        'status' => TransactionStatus::Draft,
    ]);

    expect($invoice->total())->toEqual(75.0)
        ->and(FinancialCreditCardInvoice::withTotalAmount()->findOrFail($invoice->id)->total())->toEqual(75.0)
        ->and(FinancialCreditCard::withUsedLimit()->findOrFail($card->id)->usedLimit())->toEqual(75.0);

    $invoice->registerPayment(75, Carbon::parse('2026-08-17'));

    expect($draft->refresh()->status)->toBe(TransactionStatus::Draft);
});

it('registers and undoes partial payments without lazy loading violations', function () {
    $account = FinancialAccount::factory()->create();
    $card = FinancialCreditCard::factory()->create(['financial_account_id' => $account->id]);
    $invoice = FinancialCreditCardInvoice::factory()->create([
        'financial_credit_card_id' => $card->id,
        'reference_month' => '2026-08-01',
    ]);
    FinancialTag::factory()->create([
        'id' => FinancialTag::PAGAMENTO_PARCIAL_ID,
        'name' => 'Pagamento parcial',
    ]);
    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'financial_account_id' => null,
        'type' => 'expense',
        'amount' => 100,
        'status' => TransactionStatus::Pending,
    ]);

    $invoice->registerPayment(40, Carbon::parse('2026-08-17'));
    $paymentTransactionId = $invoice->refresh()->payment_transaction_id;

    expect($paymentTransactionId)->not->toBeNull()
        ->and((float) $invoice->amount_paid)->toBe(40.0);

    $invoice->undoPayment();

    expect($invoice->refresh())
        ->payment_transaction_id->toBeNull()
        ->amount_paid->toBe('0.00');
    $this->assertSoftDeleted('financial_transactions', ['id' => $paymentTransactionId]);
});

it('locks the invoice row while registering a payment', function () {
    $account = FinancialAccount::factory()->create();
    $card = FinancialCreditCard::factory()->create(['financial_account_id' => $account->id]);
    $invoice = FinancialCreditCardInvoice::factory()->create([
        'financial_credit_card_id' => $card->id,
    ]);
    FinancialTag::factory()->create([
        'id' => FinancialTag::PAGAMENTO_PARCIAL_ID,
        'name' => 'Pagamento parcial',
    ]);
    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'amount' => 100,
        'type' => 'expense',
        'status' => TransactionStatus::Pending,
    ]);

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    $invoice->registerPayment(40, Carbon::parse('2026-08-17'));

    expect(collect($queries)->contains(fn (string $query): bool => str_contains($query, 'for update')))->toBeTrue();
});

it('treats the entire closing day as part of the current invoice period', function () {
    $card = FinancialCreditCard::factory()->create(['closing_day' => 10, 'due_day' => 15]);

    expect($card->resolveReferenceMonth(Carbon::parse('2026-08-09'))->format('Y-m'))->toBe('2026-08')
        ->and($card->resolveReferenceMonth(Carbon::parse('2026-08-10 23:59:59'))->format('Y-m'))->toBe('2026-08')
        ->and($card->resolveReferenceMonth(Carbon::parse('2026-08-11'))->format('Y-m'))->toBe('2026-09')
        ->and($card->resolveInvoiceDueDate(Carbon::parse('2026-08-10'))->toDateString())->toBe('2026-08-15')
        ->and($card->resolveInvoiceDueDate(Carbon::parse('2026-08-11'))->toDateString())->toBe('2026-09-15');
});

it('keeps the invoice open through its edited closing date', function (string $date, InvoiceStatus $expectedStatus) {
    $this->travelTo(Carbon::parse($date));
    $card = FinancialCreditCard::factory()->create(['closing_day' => 7]);
    $invoice = FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create([
        'reference_month' => '2026-10-01',
        'closing_date' => '2026-10-12',
        'due_date' => '2026-10-20',
    ]);

    expect($invoice->status())->toBe($expectedStatus);
})->with([
    'morning of the edited closing date' => ['2026-10-12 08:00:00', InvoiceStatus::Open],
    'end of the edited closing date' => ['2026-10-12 23:59:59', InvoiceStatus::Open],
    'start of the following day' => ['2026-10-13 00:00:00', InvoiceStatus::Closed],
]);

it('keeps purchases on the last day of short months in the current invoice', function (string $date) {
    $card = FinancialCreditCard::factory()->create(['closing_day' => 31, 'due_day' => 15]);
    $closingDate = Carbon::parse($date);
    $nextDay = $closingDate->copy()->addDay()->startOfDay();

    $invoice = FinancialCreditCardInvoice::resolveForDate($card, $closingDate);
    $nextInvoice = FinancialCreditCardInvoice::resolveForDate($card, $nextDay);

    expect($invoice->reference_month->toDateString())->toBe($closingDate->copy()->startOfMonth()->toDateString())
        ->and($invoice->closing_date->toDateString())->toBe($closingDate->toDateString())
        ->and($card->resolveReferenceMonth($closingDate)->toDateString())->toBe($invoice->reference_month->toDateString())
        ->and($nextInvoice->reference_month->toDateString())->toBe($nextDay->copy()->startOfMonth()->toDateString());
})->with(['2026-02-28 23:59:59', '2028-02-29 23:59:59', '2026-04-30 23:59:59', '2026-12-31 23:59:59']);

it('selects the current invoice using its edited closing date', function (int $closingDay, string $date, float $expectedTotal) {
    $this->travelTo(Carbon::parse($date));
    $card = FinancialCreditCard::factory()->create(['closing_day' => $closingDay]);

    foreach (['2026-10-01' => 100, '2026-11-01' => 200] as $referenceMonth => $amount) {
        $invoice = FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create([
            'reference_month' => $referenceMonth,
            'closing_date' => Carbon::parse($referenceMonth)->day(12),
            'due_date' => Carbon::parse($referenceMonth)->day(20),
        ]);
        FinancialTransaction::factory()->create([
            'financial_credit_card_invoice_id' => $invoice->id,
            'amount' => $amount,
            'type' => 'expense',
            'status' => TransactionStatus::Pending,
        ]);
    }

    $card->load(['invoices' => fn ($query) => $query->withTotalAmount()]);
    $card->setCurrentInvoice(now());

    expect($card->current_invoice_total)->toBe($expectedTotal)
        ->and($card->current_invoice_status)->toBe(InvoiceStatus::Open);
})->with([
    'edited later than the card default' => [7, '2026-10-12 23:59:59', 100.0],
    'day after the later edited date' => [7, '2026-10-13 00:00:00', 200.0],
    'edited earlier than the card default' => [18, '2026-10-12 23:59:59', 100.0],
    'day after the earlier edited date' => [18, '2026-10-13 00:00:00', 200.0],
]);

describe('resolveForDate', function () {
    it('assigns a transaction to the current month when the date is before the closing day', function () {
        // Card closes on day 10; a purchase on day 5 belongs to the current month's invoice.
        $card = FinancialCreditCard::factory()->create(['closing_day' => 10, 'due_day' => 15]);

        $invoice = FinancialCreditCardInvoice::resolveForDate($card, Carbon::parse('2026-08-05'));

        expect($invoice->reference_month->format('Y-m'))->toBe('2026-08');
    });

    it('assigns a transaction to the current month when the date equals the closing day', function () {
        $card = FinancialCreditCard::factory()->create(['closing_day' => 10, 'due_day' => 15]);

        $invoice = FinancialCreditCardInvoice::resolveForDate($card, Carbon::parse('2026-08-10 23:59:59'));

        expect($invoice->reference_month->format('Y-m'))->toBe('2026-08');
    });

    it('assigns a transaction to the next month when the date is after the closing day', function () {
        // Card closes on day 10; a purchase on day 11 belongs to the next month's invoice.
        $card = FinancialCreditCard::factory()->create(['closing_day' => 10, 'due_day' => 15]);

        $invoice = FinancialCreditCardInvoice::resolveForDate($card, Carbon::parse('2026-08-11'));

        expect($invoice->reference_month->format('Y-m'))->toBe('2026-09');
    });

    it('uses the existing invoice custom closing_date instead of recalculating from card closing_day', function () {
        // Card default closing day is 7, but this invoice was manually set to close on day 6.
        // A transaction on day 7 should therefore go to the NEXT month's invoice.
        $card = FinancialCreditCard::factory()->create(['closing_day' => 7, 'due_day' => 15]);

        FinancialCreditCardInvoice::factory()->create([
            'financial_credit_card_id' => $card->id,
            'reference_month' => '2026-08-01',
            'closing_date' => '2026-08-06',
            'due_date' => '2026-09-15',
        ]);

        // The custom closing day still belongs to August.
        $invoiceOnClosingDay = FinancialCreditCardInvoice::resolveForDate($card, Carbon::parse('2026-08-06'));
        expect($invoiceOnClosingDay->reference_month->format('Y-m'))->toBe('2026-08');

        // Date is after the custom closing day (07), so it should also go to September.
        $invoiceAfterClosingDay = FinancialCreditCardInvoice::resolveForDate($card, Carbon::parse('2026-08-07'));
        expect($invoiceAfterClosingDay->reference_month->format('Y-m'))->toBe('2026-09');

        // Date before the custom closing day (05) belongs to August.
        $invoiceBeforeClosingDay = FinancialCreditCardInvoice::resolveForDate($card, Carbon::parse('2026-08-05'));
        expect($invoiceBeforeClosingDay->reference_month->format('Y-m'))->toBe('2026-08');
    });
});
