<?php

use App\Enums\FinancialAccountType;
use App\Enums\TransactionStatus;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can view the finance dashboard', function () {
    $this->get(route('financial.dashboard'))
        ->assertSuccessful()
        ->assertViewIs('finance.dashboard')
        ->assertSee('x-data="evolutionChartBase(', false)
        ->assertDontSee('filterByTag(', false);
});

it('applies the investment filter consistently to every dashboard widget', function () {
    Carbon::setTestNow('2026-08-17 12:00:00');

    $checkingAccount = FinancialAccount::factory()->create([
        'type' => FinancialAccountType::Checking,
    ]);
    $investmentAccount = FinancialAccount::factory()->create([
        'type' => FinancialAccountType::Investment,
    ]);

    $checkingCard = FinancialCreditCard::factory()->create([
        'financial_account_id' => $checkingAccount->id,
    ]);
    $investmentCard = FinancialCreditCard::factory()->create([
        'financial_account_id' => $investmentAccount->id,
    ]);

    $checkingPosted = FinancialTransaction::factory()->create([
        'financial_account_id' => $checkingAccount->id,
        'type' => 'expense',
        'amount' => 100,
        'date' => Carbon::today(),
        'status' => TransactionStatus::Posted,
    ]);
    $investmentPosted = FinancialTransaction::factory()->create([
        'financial_account_id' => $investmentAccount->id,
        'type' => 'expense',
        'amount' => 200,
        'date' => Carbon::today(),
        'status' => TransactionStatus::Posted,
    ]);
    $checkingPending = FinancialTransaction::factory()->create([
        'financial_account_id' => $checkingAccount->id,
        'type' => 'expense',
        'amount' => 50,
        'date' => Carbon::today(),
        'status' => TransactionStatus::Pending,
    ]);
    $investmentPending = FinancialTransaction::factory()->create([
        'financial_account_id' => $investmentAccount->id,
        'type' => 'expense',
        'amount' => 75,
        'date' => Carbon::today(),
        'status' => TransactionStatus::Pending,
    ]);

    $this->get(route('financial.dashboard'))
        ->assertSuccessful()
        ->assertViewHas('cardsWidget', fn ($cards) => $cards->modelKeys() === [$checkingCard->id])
        ->assertViewHas('radar', fn ($transactions) => $transactions->modelKeys() === [$checkingPending->id])
        ->assertViewHas('topExpenseTags', fn ($tags) => $tags[0]['value'] === 100.0)
        ->assertViewHas('recentTransactions', function ($transactions) use ($checkingPosted, $checkingPending, $investmentPosted, $investmentPending) {
            return $transactions->contains($checkingPosted)
                && $transactions->contains($checkingPending)
                && ! $transactions->contains($investmentPosted)
                && ! $transactions->contains($investmentPending);
        });

    $this->get(route('financial.dashboard', ['include_investments' => 1]))
        ->assertSuccessful()
        ->assertViewHas('cardsWidget', fn ($cards) => $cards->modelKeys() === [$checkingCard->id, $investmentCard->id])
        ->assertViewHas('radar', fn ($transactions) => $transactions->modelKeys() === [$checkingPending->id, $investmentPending->id])
        ->assertViewHas('topExpenseTags', fn ($tags) => $tags[0]['value'] === 300.0)
        ->assertViewHas('recentTransactions', fn ($transactions) => $transactions->contains($investmentPosted) && $transactions->contains($investmentPending));

    Carbon::setTestNow();
});
