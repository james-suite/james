<?php

use App\Enums\NotificationLevel;
use App\Enums\TransactionStatus;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialRecurrence;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Notifications\DueTodayNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create();
    Notification::fake();
    Cache::flush();
});

it('details pending income and expenses due today and tomorrow', function () {
    $account = FinancialAccount::factory()->create(['name' => 'Conta Principal']);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 2500.00,
        'description' => 'Pró-labore',
        'date' => Carbon::today(),
        'status' => TransactionStatus::Pending,
    ]);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 900.00,
        'description' => 'Aluguel',
        'date' => Carbon::tomorrow(),
        'status' => TransactionStatus::Pending,
    ]);

    $this->artisan('finance:due-today-alerts')->assertSuccessful();

    Notification::assertSentTo($this->user, DueTodayNotification::class, function ($notification) {
        return $notification->level === NotificationLevel::Warning
            && $notification->alert['total_items'] === 2
            && $notification->alert['income'] === 2500.0
            && $notification->alert['expense'] === 900.0
            && $notification->alert['net'] === 1600.0
            && $notification->alert['days'][0]['key'] === 'today'
            && $notification->alert['days'][0]['incomes'][0]['description'] === 'Pró-labore'
            && $notification->alert['days'][0]['incomes'][0]['destination'] === 'Conta Principal'
            && $notification->alert['days'][1]['key'] === 'tomorrow'
            && $notification->alert['days'][1]['expenses'][0]['description'] === 'Aluguel'
            && $notification->alert['days'][1]['expenses'][0]['destination'] === 'Conta Principal';
    });
});

it('includes transactions already posted today', function () {
    $account = FinancialAccount::factory()->create(['name' => 'Conta Corrente']);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 180.00,
        'description' => 'Energia',
        'date' => Carbon::today(),
        'status' => TransactionStatus::Posted,
    ]);

    $this->artisan('finance:due-today-alerts')->assertSuccessful();

    Notification::assertSentTo($this->user, DueTodayNotification::class, function ($notification) {
        return $notification->alert['days'][0]['expenses'][0]['description'] === 'Energia'
            && $notification->alert['days'][0]['expenses'][0]['destination'] === 'Conta Corrente';
    });
});

it('details account recurrences that have not been materialized', function () {
    $account = FinancialAccount::factory()->create(['name' => 'Conta Digital']);

    FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'financial_credit_card_id' => null,
        'title' => 'Assinatura de software',
        'type' => 'expense',
        'amount' => 79.90,
        'next_processing_date' => Carbon::tomorrow(),
        'end_date' => null,
        'is_active' => true,
    ]);

    $this->artisan('finance:due-today-alerts')->assertSuccessful();

    Notification::assertSentTo($this->user, DueTodayNotification::class, function ($notification) {
        return $notification->alert['days'][0]['key'] === 'tomorrow'
            && $notification->alert['days'][0]['expenses'][0]['description'] === 'Assinatura de software'
            && $notification->alert['days'][0]['expenses'][0]['is_recurrence'] === true;
    });
});

it('shows materialized recurrences once through their transaction', function () {
    $account = FinancialAccount::factory()->create(['name' => 'Conta Digital']);
    $recurrence = FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'financial_credit_card_id' => null,
        'title' => 'Internet',
        'type' => 'expense',
        'amount' => 120.00,
        'next_processing_date' => Carbon::today(),
        'is_active' => true,
    ]);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'financial_recurrence_id' => $recurrence->id,
        'type' => 'expense',
        'amount' => 120.00,
        'description' => 'Internet',
        'date' => Carbon::today(),
        'status' => TransactionStatus::Posted,
    ]);

    $this->artisan('finance:due-today-alerts')->assertSuccessful();

    Notification::assertSentTo($this->user, DueTodayNotification::class, function ($notification) {
        return $notification->alert['total_items'] === 1
            && $notification->alert['days'][0]['expenses'][0]['description'] === 'Internet'
            && $notification->alert['days'][0]['expenses'][0]['is_recurrence'] === true;
    });
});

it('consolidates card invoices and states how many recurrences they include', function () {
    $card = FinancialCreditCard::factory()->create(['name' => 'Cartão Principal']);
    $recurrence = FinancialRecurrence::factory()->create([
        'financial_account_id' => null,
        'financial_credit_card_id' => $card->id,
        'is_active' => true,
    ]);
    $invoice = FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create([
        'closing_date' => Carbon::today()->subDays(5),
        'due_date' => Carbon::today(),
        'reference_month' => Carbon::today()->subDays(5)->startOfMonth(),
        'paid_at' => null,
        'amount_paid' => 0,
    ]);

    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'type' => 'expense',
        'amount' => 500.00,
        'date' => Carbon::today()->subDays(5),
        'status' => TransactionStatus::Pending,
    ]);
    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'financial_recurrence_id' => $recurrence->id,
        'type' => 'expense',
        'amount' => 40.00,
        'date' => Carbon::today()->subDays(5),
        'status' => TransactionStatus::Pending,
    ]);

    $this->artisan('finance:due-today-alerts')->assertSuccessful();

    Notification::assertSentTo($this->user, DueTodayNotification::class, function ($notification) {
        return $notification->alert['total_items'] === 1
            && $notification->alert['expense'] === 540.0
            && $notification->alert['days'][0]['invoices'][0]['description'] === 'Fatura Cartão Principal'
            && $notification->alert['days'][0]['invoices'][0]['transactions_count'] === 2
            && $notification->alert['days'][0]['invoices'][0]['recurrences_count'] === 1;
    });
});

it('does not notify when all items are outside the period or excluded', function () {
    $account = FinancialAccount::factory()->create();
    $transferTag = FinancialTag::factory()->create([
        'id' => FinancialTag::TRANSFERENCIA_ID,
        'name' => 'Transferência',
    ]);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'date' => Carbon::today()->addDays(5),
        'status' => TransactionStatus::Pending,
    ]);
    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'date' => Carbon::today(),
        'status' => TransactionStatus::Draft,
    ]);
    $transfer = FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'date' => Carbon::today(),
        'status' => TransactionStatus::Pending,
    ]);
    $transfer->tags()->attach($transferTag->id, ['is_primary' => true]);
    FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'next_processing_date' => Carbon::tomorrow(),
        'is_active' => false,
    ]);

    $this->artisan('finance:due-today-alerts')->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not count paid invoices as due', function () {
    $card = FinancialCreditCard::factory()->create();
    $invoice = FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create([
        'closing_date' => Carbon::today()->subDays(5),
        'due_date' => Carbon::today(),
        'reference_month' => Carbon::today()->subDays(5)->startOfMonth(),
        'paid_at' => Carbon::yesterday(),
    ]);

    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'type' => 'expense',
        'amount' => 500.00,
        'date' => Carbon::today()->subDays(5),
        'status' => TransactionStatus::Posted,
    ]);

    $this->artisan('finance:due-today-alerts')->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not resend the same due alert unless forced', function () {
    FinancialTransaction::factory()->create([
        'type' => 'expense',
        'amount' => 90.00,
        'date' => Carbon::today(),
        'status' => TransactionStatus::Pending,
    ]);

    $this->artisan('finance:due-today-alerts')->assertSuccessful();
    $this->artisan('finance:due-today-alerts')->assertSuccessful();

    expect(Notification::sent($this->user, DueTodayNotification::class))->toHaveCount(1);

    $this->artisan('finance:due-today-alerts', ['--force' => true])->assertSuccessful();

    expect(Notification::sent($this->user, DueTodayNotification::class))->toHaveCount(2);
});
