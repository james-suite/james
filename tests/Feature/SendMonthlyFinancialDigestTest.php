<?php

use App\Enums\NotificationLevel;
use App\Enums\TransactionStatus;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Notifications\FinancialSummaryNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create();
    Notification::fake();
    Cache::flush();

    Carbon::setTestNow(Carbon::create(2026, 8, 1, 9, 0, 0));
});

afterEach(function () {
    Carbon::setTestNow(null);
});

it('sends a useful monthly digest with comparisons, balances and primary tags', function () {
    $account = FinancialAccount::factory()->create(['name' => 'Conta Principal']);
    $salaryTag = FinancialTag::factory()->create(['name' => 'Salário', 'icon' => 'heroicon-o-banknotes', 'color_hex' => '#16a34a']);
    $housingTag = FinancialTag::factory()->create(['name' => 'Moradia', 'icon' => 'heroicon-o-home', 'color_hex' => '#dc2626']);
    $foodTag = FinancialTag::factory()->create(['name' => 'Alimentação', 'icon' => 'heroicon-o-receipt-percent', 'color_hex' => '#f59e0b']);

    $previousIncome = FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 2000.00,
        'date' => '2026-06-15',
        'status' => TransactionStatus::Posted,
    ]);
    $previousIncome->tags()->attach($salaryTag->id, ['is_primary' => true]);
    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 500.00,
        'date' => '2026-06-20',
        'status' => TransactionStatus::Posted,
    ]);

    $income = FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 3000.00,
        'date' => '2026-07-15',
        'status' => TransactionStatus::Posted,
    ]);
    $income->tags()->attach($salaryTag->id, ['is_primary' => true]);

    $expense = FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 1200.00,
        'date' => '2026-07-20',
        'status' => TransactionStatus::Posted,
    ]);
    $expense->tags()->attach($housingTag->id, ['is_primary' => true]);
    $foodItem = $expense->items()->create([
        'description' => 'Mercado',
        'quantity' => 1,
        'unit_price' => 300,
        'total' => 300,
    ]);
    $foodItem->tags()->attach($foodTag->id, ['is_primary' => true]);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 100.00,
        'date' => '2026-07-25',
        'status' => TransactionStatus::Posted,
    ]);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 200.00,
        'date' => Carbon::today(),
        'status' => TransactionStatus::Pending,
    ]);

    $card = FinancialCreditCard::factory()->create([
        'financial_account_id' => $account->id,
    ]);
    $invoice = FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create([
        'closing_date' => Carbon::today()->subDays(5),
        'due_date' => Carbon::today()->addDays(10),
        'reference_month' => Carbon::today()->startOfMonth(),
        'paid_at' => null,
        'amount_paid' => 0,
    ]);
    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'type' => 'expense',
        'amount' => 300.00,
        'date' => Carbon::today(),
        'status' => TransactionStatus::Pending,
    ]);

    $this->artisan('finance:monthly-digest')->assertSuccessful();

    Notification::assertSentTo($this->user, FinancialSummaryNotification::class, function ($notification) {
        return $notification->summary['period'] === 'Julho de 2026'
            && $notification->level === NotificationLevel::Success
            && $notification->summary['income'] === 3000.0
            && $notification->summary['expense'] === 1300.0
            && $notification->summary['net'] === 1700.0
            && $notification->summary['income_variation'] === 1000.0
            && $notification->summary['expense_variation'] === 800.0
            && $notification->summary['net_variation'] === 200.0
            && $notification->summary['account_balance'] === 3200.0
            && $notification->summary['pending_commitments'] === 500.0
            && $notification->summary['net_balance'] === 2700.0
            && $notification->summary['income_categories'] === [[
                'id' => $notification->summary['income_categories'][0]['id'],
                'name' => 'Salário',
                'icon' => 'heroicon-o-banknotes',
                'color' => '#16a34a',
                'amount' => 3000.0,
                'percentage' => 100.0,
            ]]
            && $notification->summary['expense_categories'] === [
                [
                    'id' => $notification->summary['expense_categories'][0]['id'],
                    'name' => 'Moradia',
                    'icon' => 'heroicon-o-home',
                    'color' => '#dc2626',
                    'amount' => 900.0,
                    'percentage' => 69.2,
                ],
                [
                    'id' => $notification->summary['expense_categories'][1]['id'],
                    'name' => 'Alimentação',
                    'icon' => 'heroicon-o-receipt-percent',
                    'color' => '#f59e0b',
                    'amount' => 300.0,
                    'percentage' => 23.1,
                ],
                [
                    'id' => 0,
                    'name' => 'Sem categoria',
                    'icon' => 'heroicon-o-tag',
                    'color' => '#9ca3af',
                    'amount' => 100.0,
                    'percentage' => 7.7,
                ],
            ];
    });
});

it('ignores drafts, transfers and partial payments in the monthly digest', function () {
    $transferTag = FinancialTag::factory()->create([
        'id' => FinancialTag::TRANSFERENCIA_ID,
        'name' => 'Transferência',
    ]);
    $partialPaymentTag = FinancialTag::factory()->create([
        'id' => FinancialTag::PAGAMENTO_PARCIAL_ID,
        'name' => 'Pagamento parcial',
    ]);

    FinancialTransaction::factory()->create([
        'type' => 'expense',
        'amount' => 9000,
        'date' => '2026-07-20',
        'status' => TransactionStatus::Draft,
    ]);
    $transfer = FinancialTransaction::factory()->create([
        'type' => 'expense',
        'amount' => 8000,
        'date' => '2026-07-20',
        'status' => TransactionStatus::Posted,
    ]);
    $transfer->tags()->attach($transferTag->id, ['is_primary' => true]);
    $partialPayment = FinancialTransaction::factory()->create([
        'type' => 'expense',
        'amount' => 7000,
        'date' => '2026-07-20',
        'status' => TransactionStatus::Posted,
    ]);
    $partialPayment->tags()->attach($partialPaymentTag->id, ['is_primary' => true]);

    $this->artisan('finance:monthly-digest')->assertSuccessful();

    Notification::assertSentTo($this->user, FinancialSummaryNotification::class, function ($notification) {
        return $notification->summary['expense'] === 0.0
            && $notification->summary['net'] === 0.0
            && $notification->summary['expense_variation'] === 0.0
            && $notification->summary['expense_categories'] === [];
    });
});

it('uses a warning level when the net result is negative', function () {
    $account = FinancialAccount::factory()->create();

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 1000.00,
        'date' => '2026-07-10',
        'status' => TransactionStatus::Posted,
    ]);
    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 3000.00,
        'date' => '2026-07-10',
        'status' => TransactionStatus::Posted,
    ]);

    $this->artisan('finance:monthly-digest')->assertSuccessful();

    Notification::assertSentTo($this->user, FinancialSummaryNotification::class, function ($notification) {
        return $notification->level === NotificationLevel::Warning
            && $notification->summary['net'] === -2000.0;
    });
});

it('sends a zero-balance digest when no transactions exist in either period', function () {
    $this->artisan('finance:monthly-digest')->assertSuccessful();

    Notification::assertSentTo($this->user, FinancialSummaryNotification::class, function ($notification) {
        return $notification->level === NotificationLevel::Success
            && $notification->summary['income'] === 0.0
            && $notification->summary['expense'] === 0.0
            && $notification->summary['net_variation'] === 0.0;
    });
});

it('does not resend the same monthly digest unless forced', function () {
    $this->artisan('finance:monthly-digest')->assertSuccessful();
    $this->artisan('finance:monthly-digest')->assertSuccessful();

    expect(Notification::sent($this->user, FinancialSummaryNotification::class))->toHaveCount(1);

    $this->artisan('finance:monthly-digest', ['--force' => true])->assertSuccessful();

    expect(Notification::sent($this->user, FinancialSummaryNotification::class))->toHaveCount(2);
});
