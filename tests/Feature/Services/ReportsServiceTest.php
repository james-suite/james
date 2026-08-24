<?php

use App\Enums\TransactionStatus;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Services\ReportsService;
use Illuminate\Support\Carbon;

it('calculates net worth evolution correctly based on accrual accounting (competência)', function () {
    $account = FinancialAccount::factory()->create();
    $card = FinancialCreditCard::factory()->create(['financial_account_id' => $account->id]);

    $invoice = FinancialCreditCardInvoice::factory()->create([
        'financial_credit_card_id' => $card->id,
        'reference_month' => Carbon::now()->startOfMonth(),
        'closing_date' => Carbon::now()->addDays(20),
        'due_date' => Carbon::now()->addDays(25),
    ]);

    // An income on account today
    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 1000,
        'date' => Carbon::now()->format('Y-m-d'),
        'status' => 'posted',
    ]);

    // A credit card expense today (competência: impacts net worth TODAY, not on invoice due date)
    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'type' => 'expense',
        'amount' => 300,
        'date' => Carbon::now()->format('Y-m-d'),
        'status' => 'posted',
    ]);

    $service = new ReportsService;

    // Test evolution for this month, daily
    $startDate = Carbon::now()->startOfMonth();
    $endDate = Carbon::now()->endOfMonth();

    $data = $service->getAll($startDate, $endDate, null, 'daily')['netWorthEvolution'];

    // We can't easily assert exactly which index is today because it depends on the day of the month,
    // but the final net worth at the end of the data array should reflect 1000 - 300 = 700.

    $lastPoint = end($data);
    expect($lastPoint['value'])->toEqual(700);
});

it('orders sankey tags by descending net flow value', function () {
    $account = FinancialAccount::factory()->create();
    $lowerValueTag = FinancialTag::factory()->create(['name' => 'Categoria A']);
    $higherValueTag = FinancialTag::factory()->create(['name' => 'Categoria B']);

    $lowerValueTransaction = FinancialTransaction::factory()->posted()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 251,
        'date' => '2026-08-01',
    ]);
    $lowerValueTransaction->tags()->attach($lowerValueTag, ['is_primary' => true]);

    $higherValueTransaction = FinancialTransaction::factory()->posted()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 260,
        'date' => '2026-08-02',
    ]);
    $higherValueTransaction->tags()->attach($higherValueTag, ['is_primary' => true]);

    $data = (new ReportsService)->getAll(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
        [$account->id],
    );

    $nodeNames = collect($data['sankey']['nodes'])->pluck('name')->all();
    $links = collect($data['sankey']['links'])
        ->map(fn (array $link): array => [
            'source' => $link['source'],
            'target' => $link['target'],
            'value' => $link['value'],
        ])
        ->all();

    expect($nodeNames)->toBe(['Fluxo de Caixa', 'Categoria B', 'Categoria A', 'Saldo'])
        ->and($links)->toBe([
            ['source' => 'Categoria B', 'target' => 'Fluxo de Caixa', 'value' => 260.0],
            ['source' => 'Categoria A', 'target' => 'Fluxo de Caixa', 'value' => 251.0],
            ['source' => 'Fluxo de Caixa', 'target' => 'Saldo', 'value' => 511.0],
        ]);
});

it('keeps equal and uncategorized sankey flows when ordering tags', function () {
    $account = FinancialAccount::factory()->create();
    $firstEqualTag = FinancialTag::factory()->create(['name' => 'Categoria A']);
    $secondEqualTag = FinancialTag::factory()->create(['name' => 'Categoria B']);

    $firstEqualTransaction = FinancialTransaction::factory()->posted()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 260,
        'date' => '2026-08-01',
    ]);
    $firstEqualTransaction->tags()->attach($firstEqualTag, ['is_primary' => true]);

    FinancialTransaction::factory()->posted()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 300,
        'date' => '2026-08-02',
    ]);

    $secondEqualTransaction = FinancialTransaction::factory()->posted()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 260,
        'date' => '2026-08-03',
    ]);
    $secondEqualTransaction->tags()->attach($secondEqualTag, ['is_primary' => true]);

    $data = (new ReportsService)->getAll(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
        [$account->id],
    );

    $nodeNames = collect($data['sankey']['nodes'])->pluck('name')->all();
    $linkValues = collect($data['sankey']['links'])->pluck('value')->all();

    expect($nodeNames)->toBe(['Fluxo de Caixa', 'Sem Categoria', 'Categoria A', 'Categoria B', 'Saldo'])
        ->and($linkValues)->toBe([300.0, 260.0, 260.0, 820.0]);
});

it('excludes drafts from reports', function () {
    $account = FinancialAccount::factory()->create();
    $posted = FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 100,
        'date' => '2026-08-17',
        'status' => TransactionStatus::Posted,
    ]);
    FinancialTransaction::factory()->create([
        'type' => 'expense',
        'amount' => 900,
        'date' => '2026-08-17',
        'status' => TransactionStatus::Draft,
    ]);

    $data = (new ReportsService)->getAll(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    expect($data['transactions']->modelKeys())->toBe([$posted->id])
        ->and($data['tableTransactions'])->toHaveCount(1)
        ->and(last($data['netWorthEvolution'])['value'])->toEqual(100.0);
});
