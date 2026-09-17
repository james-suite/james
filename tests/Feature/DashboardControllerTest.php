<?php

use App\Enums\FinancialAccountType;
use App\Enums\SettlementType;
use App\Enums\TransactionStatus;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('shows module summaries using the effective net settlement balance', function () {
    Carbon::setTestNow('2026-09-17 10:00:00');

    $account = FinancialAccount::factory()->create([
        'type' => FinancialAccountType::Checking,
    ]);

    FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 1000,
        'date' => Carbon::today(),
        'status' => TransactionStatus::Posted,
        'description' => 'Salário',
    ]);

    $contact = Contact::factory()->create(['name' => 'Maria Silva']);
    Settlement::factory()->create([
        'contact_id' => $contact->id,
        'type' => SettlementType::TheyOwe->value,
        'amount' => 79.05,
        'description' => 'Jantar',
        'date' => Carbon::today(),
    ]);
    Settlement::factory()->create([
        'contact_id' => $contact->id,
        'type' => SettlementType::IOwe->value,
        'amount' => 78.36,
        'description' => 'Compras',
        'date' => Carbon::today(),
    ]);

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertViewIs('dashboard')
        ->assertViewHas('financialSummary', fn (array $summary): bool => $summary['currentBalance'] === 1000.0)
        ->assertViewHas('settlementSummary', fn (array $summary): bool => $summary['toReceive'] === 0.69
            && $summary['toPay'] === 0.0
            && $summary['netBalance'] === 0.69
            && $summary['pendingCount'] === 1)
        ->assertViewHas('contactCount', 1);
});

it('requires authentication', function () {
    auth()->logout();

    $this->get(route('dashboard'))->assertRedirect('/login');
});
