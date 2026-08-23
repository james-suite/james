<?php

use App\Models\FinancialAccount;
use App\Models\FinancialRecurrence;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can list recurrences', function () {
    FinancialRecurrence::factory()->count(3)->create();

    $this->get(route('financial.recurrences.index'))
        ->assertSuccessful()
        ->assertViewIs('finance.recurrences.index');
});

it('can search active recurrences by title', function () {
    $matching = FinancialRecurrence::factory()->create(['title' => 'Assinatura especial']);
    FinancialRecurrence::factory()->create(['title' => 'Outra despesa']);

    $response = $this->get(route('financial.recurrences.index', ['search' => 'especial']))
        ->assertSuccessful();

    expect($response->viewData('recurrences')->pluck('id')->all())->toBe([$matching->id]);
});

it('keeps the recurrence search when paginating', function () {
    FinancialRecurrence::factory()->count(16)->create(['title' => 'Assinatura paginada']);

    $this->get(route('financial.recurrences.index', ['search' => 'paginada']))
        ->assertSuccessful();
});

it('can view create recurrence page', function () {
    $this->get(route('financial.recurrences.create'))
        ->assertSuccessful()
        ->assertViewIs('finance.recurrences.create');
});

it('can store recurrence', function () {
    $account = FinancialAccount::factory()->create();

    $data = [
        'financial_account_id' => $account->id,
        'title' => 'Assinatura Netflix',
        'type' => 'expense',
        'amount' => 55.90,
        'frequency' => 'monthly',
        'start_date' => now()->format('Y-m-d'),
        'next_processing_date' => now()->format('Y-m-d'),
    ];

    $this->post(route('financial.recurrences.store'), $data)
        ->assertRedirect(route('financial.recurrences.index'));

    $this->assertDatabaseHas('financial_recurrences', [
        'title' => 'Assinatura Netflix',
        'amount' => 55.90,
    ]);
});

it('can view edit recurrence page', function () {
    $recurrence = FinancialRecurrence::factory()->create();

    $this->get(route('financial.recurrences.edit', $recurrence))
        ->assertSuccessful()
        ->assertViewIs('finance.recurrences.edit');
});

it('can update recurrence', function () {
    $recurrence = FinancialRecurrence::factory()->create();

    $data = [
        'financial_account_id' => $recurrence->financial_account_id,
        'title' => 'Assinatura Netflix Premium',
        'type' => 'expense',
        'amount' => 59.90,
        'frequency' => 'monthly',
        'start_date' => $recurrence->start_date->format('Y-m-d'),
        'next_processing_date' => $recurrence->next_processing_date->format('Y-m-d'),
    ];

    $this->put(route('financial.recurrences.update', $recurrence), $data)
        ->assertRedirect(route('financial.recurrences.index'));

    $this->assertDatabaseHas('financial_recurrences', [
        'id' => $recurrence->id,
        'title' => 'Assinatura Netflix Premium',
        'amount' => 59.90,
    ]);
});

it('can soft delete recurrence', function () {
    $recurrence = FinancialRecurrence::factory()->create();

    $this->delete(route('financial.recurrences.destroy', $recurrence))
        ->assertRedirect(route('financial.recurrences.index'));

    $this->assertSoftDeleted($recurrence);
});

it('can list trashed recurrences', function () {
    FinancialRecurrence::factory()->count(2)->trashed()->create();

    $this->get(route('financial.recurrences.trashed'))
        ->assertSuccessful()
        ->assertViewIs('finance.recurrences.trashed');
});

it('can search trashed recurrences by title', function () {
    $matching = FinancialRecurrence::factory()->trashed()->create(['title' => 'Assinatura encerrada']);
    FinancialRecurrence::factory()->trashed()->create(['title' => 'Outra despesa']);

    $response = $this->get(route('financial.recurrences.trashed', ['search' => 'encerrada']))
        ->assertSuccessful();

    expect($response->viewData('recurrences')->pluck('id')->all())->toBe([$matching->id]);
});

it('can restore trashed recurrence', function () {
    $recurrence = FinancialRecurrence::factory()->trashed()->create();

    $this->patch(route('financial.recurrences.restore', $recurrence))
        ->assertRedirect(route('financial.recurrences.trashed'));

    $this->assertNotSoftDeleted($recurrence);
});

it('can force delete recurrence', function () {
    $recurrence = FinancialRecurrence::factory()->trashed()->create();

    $this->delete(route('financial.recurrences.forceDestroy', $recurrence))
        ->assertRedirect(route('financial.recurrences.trashed'));

    $this->assertDatabaseMissing('financial_recurrences', [
        'id' => $recurrence->id,
    ]);
});
