<?php

use App\Enums\FinancialAccountType;
use App\Enums\InvoiceStatus;
use App\Enums\TransactionStatus;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialRecurrence;
use App\Models\FinancialTransaction;
use App\Services\FinanceDashboardService;
use Illuminate\Support\Carbon;

it('calculates KPIs correctly', function () {
    $account = FinancialAccount::factory()->create(['type' => FinancialAccountType::Checking]);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 500,
        'date' => Carbon::now()->format('Y-m-d'),
        'status' => 'posted',
    ]);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 200,
        'date' => Carbon::now()->format('Y-m-d'),
        'status' => 'posted',
    ]);

    $service = new FinanceDashboardService;
    $kpis = $service->getKpiNumbers(false);

    expect($kpis['income'])->toEqual(500)
        ->and($kpis['expense'])->toEqual(200)
        ->and($kpis['currentBalance'])->toEqual(300)
        ->and($kpis['netBalance'])->toEqual(300); // Because account balance is also 300
});

it('subtracts remaining occurrences of active expense recurrences with end_date from netBalance only', function () {
    $account = FinancialAccount::factory()->create(['type' => FinancialAccountType::Checking]);
    $investmentAccount = FinancialAccount::factory()->create(['type' => FinancialAccountType::Investment]);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 1000,
        'date' => '2026-01-01',
        'status' => TransactionStatus::Posted,
    ]);

    // Finite monthly expense recurrence: 3 remaining occurrences (Jan, Feb, Mar) = 3 * 100 = 300
    FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 100,
        'frequency' => 'monthly',
        'start_date' => '2026-01-10',
        'next_processing_date' => '2026-01-10',
        'end_date' => '2026-03-10',
        'is_active' => true,
    ]);

    // Open-ended expense recurrence (no end_date): must NOT be subtracted
    FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 50,
        'frequency' => 'monthly',
        'start_date' => '2026-01-10',
        'next_processing_date' => '2026-01-10',
        'end_date' => null,
        'is_active' => true,
    ]);

    // Inactive finite expense recurrence: must NOT be subtracted
    FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 80,
        'frequency' => 'monthly',
        'start_date' => '2026-01-10',
        'next_processing_date' => '2026-01-10',
        'end_date' => '2026-03-10',
        'is_active' => false,
    ]);

    // Finite income recurrence: must NOT be added to netBalance
    FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 200,
        'frequency' => 'monthly',
        'start_date' => '2026-01-10',
        'next_processing_date' => '2026-01-10',
        'end_date' => '2026-03-10',
        'is_active' => true,
    ]);

    // Investment account finite expense recurrence: excluded when includeInvestments is false
    FinancialRecurrence::factory()->create([
        'financial_account_id' => $investmentAccount->id,
        'type' => 'expense',
        'amount' => 75,
        'frequency' => 'monthly',
        'start_date' => '2026-01-10',
        'next_processing_date' => '2026-01-10',
        'end_date' => '2026-02-10',
        'is_active' => true,
    ]);

    $service = new FinanceDashboardService;

    expect($service->getKpiNumbers(false))
        ->currentBalance->toEqual(1000.0)
        ->netBalance->toEqual(700.0);

    expect($service->getKpiNumbers(true))
        ->currentBalance->toEqual(1000.0)
        ->netBalance->toEqual(550.0);
});

it('does not expose drafts in dashboard totals or recent transactions', function () {
    $account = FinancialAccount::factory()->create(['type' => FinancialAccountType::Checking]);
    $posted = FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 100,
        'date' => Carbon::today(),
        'status' => TransactionStatus::Posted,
    ]);
    FinancialTransaction::factory()->create([
        'type' => 'expense',
        'amount' => 900,
        'date' => Carbon::today()->addDay(),
        'status' => TransactionStatus::Draft,
    ]);

    $service = new FinanceDashboardService;

    expect($service->getKpiNumbers(false))
        ->income->toEqual(100.0)
        ->expense->toEqual(0.0)
        ->and($service->getRecentTransactions()->modelKeys())->toBe([$posted->id]);
});

it('calculates cash flow projections', function () {
    $account = FinancialAccount::factory()->create(['type' => FinancialAccountType::Checking]);

    // Add transaction to give initial balance
    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 1000,
        'date' => Carbon::now()->format('Y-m-d'),
        'status' => 'posted',
    ]);

    $service = new FinanceDashboardService;
    $projections = $service->getCashFlowProjections(Carbon::now());

    expect($projections)
        ->toHaveKeys(['currentMonth', 'nextMonth', 'afterNextMonth'])
        ->and($projections['currentMonth'])->toEqual(1000)
        ->and($projections['nextMonth'])->toEqual(1000)
        ->and($projections['afterNextMonth'])->toEqual(1000);
});

it('returns account balances chart data', function () {
    $account = FinancialAccount::factory()->create([
        'name' => 'Main Account',
        'type' => FinancialAccountType::Checking,
    ]);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 500,
        'date' => Carbon::now()->format('Y-m-d'),
        'status' => 'posted',
    ]);

    $service = new FinanceDashboardService;
    $data = $service->getAccountBalancesChart();

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toEqual('Main Account')
        ->and($data[0]['value'])->toEqual(500);
});

it('getCreditCardsWidget returns the invoice matching the current reference month', function () {
    Carbon::setTestNow('2026-07-17'); // After closing day 7 → referenceMonth = agosto

    $account = FinancialAccount::factory()->create(['type' => FinancialAccountType::Checking]);
    $card = FinancialCreditCard::factory()->create([
        'financial_account_id' => $account->id,
        'closing_day' => 7,
        'due_day' => 1,
    ]);

    // Julho invoice (closed — before closing day)
    FinancialCreditCardInvoice::factory()->create([
        'financial_credit_card_id' => $card->id,
        'reference_month' => '2026-07-01',
        'closing_date' => '2026-07-07',
        'due_date' => '2026-08-01',
        'paid_at' => null,
    ]);

    // Agosto invoice (open — current reference month)
    FinancialCreditCardInvoice::factory()->create([
        'financial_credit_card_id' => $card->id,
        'reference_month' => '2026-08-01',
        'closing_date' => '2026-08-07',
        'due_date' => '2026-09-01',
        'paid_at' => null,
    ]);

    $service = new FinanceDashboardService;
    $cards = $service->getCreditCardsWidget(Carbon::now());

    // Should pick agosto (reference_month matches resolved month)
    expect($cards->first()->current_invoice_status)->toBeIn([InvoiceStatus::Open, InvoiceStatus::Closed]);

    Carbon::setTestNow();
});

it('getCreditCardsWidget falls back to most recent unpaid invoice when reference month has no match', function () {
    Carbon::setTestNow('2026-07-17');

    $account = FinancialAccount::factory()->create(['type' => FinancialAccountType::Checking]);
    $card = FinancialCreditCard::factory()->create([
        'financial_account_id' => $account->id,
        'closing_day' => 7,
        'due_day' => 1,
    ]);

    // Only one invoice — does not match resolved reference month (agosto)
    $invoice = FinancialCreditCardInvoice::factory()->create([
        'financial_credit_card_id' => $card->id,
        'reference_month' => '2026-06-01',
        'closing_date' => '2026-06-07',
        'due_date' => '2026-07-01',
        'paid_at' => null,
    ]);

    $service = new FinanceDashboardService;
    $cards = $service->getCreditCardsWidget(Carbon::now());

    // Fallback should return the only unpaid invoice
    expect($cards->first()->current_invoice_total)->toEqual($invoice->total());

    Carbon::setTestNow();
});
