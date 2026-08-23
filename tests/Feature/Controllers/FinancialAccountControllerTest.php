<?php

use App\Models\FinancialAccount;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can list accounts', function () {
    FinancialAccount::factory()->count(3)->create();

    $this->get(route('financial.accounts.index'))
        ->assertSuccessful()
        ->assertViewIs('finance.accounts.index');
});

it('lists accounts by balance group and amount', function () {
    $positiveLargest = FinancialAccount::factory()->create(['name' => 'Positiva Maior']);
    $positiveSmallest = FinancialAccount::factory()->create(['name' => 'Positiva Menor']);
    $negativeLargest = FinancialAccount::factory()->create(['name' => 'Negativa Maior']);
    $negativeSmallest = FinancialAccount::factory()->create(['name' => 'Negativa Menor']);
    FinancialAccount::factory()->create(['name' => 'Conta Zero B']);
    FinancialAccount::factory()->create(['name' => 'Conta Zero A']);

    FinancialTransaction::factory()->posted()->create([
        'financial_account_id' => $positiveLargest->id,
        'type' => 'income',
        'amount' => 200,
    ]);
    FinancialTransaction::factory()->posted()->create([
        'financial_account_id' => $positiveSmallest->id,
        'type' => 'income',
        'amount' => 100,
    ]);
    FinancialTransaction::factory()->posted()->create([
        'financial_account_id' => $negativeLargest->id,
        'type' => 'expense',
        'amount' => 300,
    ]);
    FinancialTransaction::factory()->posted()->create([
        'financial_account_id' => $negativeSmallest->id,
        'type' => 'expense',
        'amount' => 50,
    ]);

    $this->get(route('financial.accounts.index'))
        ->assertViewHas('accounts', function ($accounts) {
            return $accounts->getCollection()->pluck('name')->all() === [
                'Positiva Maior',
                'Positiva Menor',
                'Negativa Maior',
                'Negativa Menor',
                'Conta Zero A',
                'Conta Zero B',
            ];
        });
});

it('can view create account page', function () {
    $this->get(route('financial.accounts.create'))
        ->assertSuccessful()
        ->assertViewIs('finance.accounts.create');
});

it('can store account', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::SALDO_INICIAL_ID,
        'name' => 'Saldo Inicial',
        'is_protected' => true,
    ]);

    $data = [
        'name' => 'Nova Conta Bancaria',
        'type' => 'checking',
        'initial_balance' => 1000.50,
        'color_hex' => '#ff0000',
    ];

    $this->post(route('financial.accounts.store'), $data)
        ->assertRedirect(route('financial.accounts.show', FinancialAccount::first()));

    $this->assertDatabaseHas('financial_accounts', [
        'name' => 'Nova Conta Bancaria',
        'type' => 'checking',
    ]);
});

it('can view edit account page', function () {
    $account = FinancialAccount::factory()->create();

    $this->get(route('financial.accounts.edit', $account))
        ->assertSuccessful()
        ->assertViewIs('finance.accounts.edit');
});

it('can update account', function () {
    $account = FinancialAccount::factory()->create();

    $data = [
        'name' => 'Conta Atualizada',
        'type' => 'wallet',
        'color_hex' => '#00ff00',
    ];

    $this->put(route('financial.accounts.update', $account), $data)
        ->assertRedirect(route('financial.accounts.show', $account));

    $this->assertDatabaseHas('financial_accounts', [
        'id' => $account->id,
        'name' => 'Conta Atualizada',
        'type' => 'wallet',
    ]);
});

it('can soft delete account', function () {
    $account = FinancialAccount::factory()->create();

    $this->delete(route('financial.accounts.destroy', $account))
        ->assertRedirect(route('financial.accounts.index'));

    $this->assertSoftDeleted($account);
});

it('can list trashed accounts', function () {
    FinancialAccount::factory()->count(2)->trashed()->create();

    $this->get(route('financial.accounts.trashed'))
        ->assertSuccessful()
        ->assertViewIs('finance.accounts.trashed');
});

it('can restore trashed account', function () {
    $account = FinancialAccount::factory()->trashed()->create();

    $this->patch(route('financial.accounts.restore', $account))
        ->assertRedirect(route('financial.accounts.show', $account));

    $this->assertNotSoftDeleted($account);
});

it('can force delete account', function () {
    $account = FinancialAccount::factory()->trashed()->create();

    $this->delete(route('financial.accounts.forceDestroy', $account))
        ->assertRedirect(route('financial.accounts.trashed'));

    $this->assertDatabaseMissing('financial_accounts', [
        'id' => $account->id,
    ]);
});

it('can adjust balance', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::AJUSTE_SALDO_ID,
        'name' => 'Ajuste de Saldo',
        'is_protected' => true,
    ]);

    $account = FinancialAccount::factory()->create();

    $this->post(route('financial.accounts.adjust-balance', $account), ['real_balance' => 1500])
        ->assertRedirect(route('financial.accounts.show', $account));

    $this->assertDatabaseHas('financial_transactions', [
        'financial_account_id' => $account->id,
        'description' => 'Ajuste de Saldo',
        'status' => 'posted',
    ]);
});

it('adjust balance creates no transaction when difference is zero', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::AJUSTE_SALDO_ID,
        'name' => 'Ajuste de Saldo',
        'is_protected' => true,
    ]);

    $account = FinancialAccount::factory()->create();

    // Balance is 0, sending 0 — no transaction should be created
    $this->post(route('financial.accounts.adjust-balance', $account), ['real_balance' => 0])
        ->assertRedirect(route('financial.accounts.show', $account));

    $this->assertDatabaseMissing('financial_transactions', [
        'financial_account_id' => $account->id,
        'description' => 'Ajuste de Saldo',
    ]);
});
