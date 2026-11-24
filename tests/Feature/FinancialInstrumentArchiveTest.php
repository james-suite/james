<?php

use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    if (DB::connection()->getDriverName() === 'sqlite') {
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (float|int $a, float|int $b): float|int => max($a, $b), 2);
    }
});

it('archives and unarchives a financial account and filters it from active listings and selectors', function () {
    $activeAccount = FinancialAccount::factory()->create(['name' => 'Conta Ativa']);
    $accountToArchive = FinancialAccount::factory()->create(['name' => 'Conta Antiga']);

    $this->patch(route('financial.accounts.archive', $accountToArchive))
        ->assertRedirect(route('financial.accounts.show', $accountToArchive));

    expect($accountToArchive->fresh()->isArchived())->toBeTrue();

    $this->get(route('financial.accounts.index'))
        ->assertSuccessful()
        ->assertViewHas('accounts', fn ($accounts) => $accounts->pluck('id')->all() === [$activeAccount->id]);

    $this->get(route('financial.accounts.index', ['archived' => 1]))
        ->assertSuccessful()
        ->assertViewHas('accounts', fn ($accounts) => $accounts->pluck('id')->all() === [$accountToArchive->id]);

    $this->get(route('financial.transactions.create'))
        ->assertSuccessful()
        ->assertViewHas('accounts', fn ($accounts) => $accounts->pluck('id')->all() === [$activeAccount->id]);

    $this->patch(route('financial.accounts.unarchive', $accountToArchive))
        ->assertRedirect(route('financial.accounts.show', $accountToArchive));

    expect($accountToArchive->fresh()->isArchived())->toBeFalse();
});

it('archives and unarchives a credit card and filters it from active listings and selectors', function () {
    $activeCard = FinancialCreditCard::factory()->create(['name' => 'Cartão Ativo']);
    $cardToArchive = FinancialCreditCard::factory()->create(['name' => 'Cartão Encerrado']);

    $this->patch(route('financial.cards.archive', $cardToArchive))
        ->assertRedirect(route('financial.cards.show', $cardToArchive));

    expect($cardToArchive->fresh()->isArchived())->toBeTrue();

    $this->get(route('financial.cards.index'))
        ->assertSuccessful()
        ->assertViewHas('cards', fn ($cards) => $cards->pluck('id')->all() === [$activeCard->id]);

    $this->get(route('financial.cards.index', ['archived' => 1]))
        ->assertSuccessful()
        ->assertViewHas('cards', fn ($cards) => $cards->pluck('id')->all() === [$cardToArchive->id]);

    $this->get(route('financial.transactions.create'))
        ->assertSuccessful()
        ->assertViewHas('cards', fn ($cards) => $cards->pluck('id')->all() === [$activeCard->id]);

    $this->patch(route('financial.cards.unarchive', $cardToArchive))
        ->assertRedirect(route('financial.cards.show', $cardToArchive));

    expect($cardToArchive->fresh()->isArchived())->toBeFalse();
});
