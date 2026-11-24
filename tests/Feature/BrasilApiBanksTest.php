<?php

use App\Enums\FinancialAccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Cache::forget('brasilapi.banks.v1');
});

it('uses Dinheiro físico as the label for the wallet account type while keeping wallet value', function () {
    expect(FinancialAccountType::Wallet->value)->toBe('wallet')
        ->and(FinancialAccountType::Wallet->label())->toBe('Dinheiro físico');
});

it('returns filtered institutions from BrasilAPI and caches the catalog', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://brasilapi.com.br/api/banks/v1' => Http::response([
            ['code' => 260, 'name' => 'Nu Pagamentos S.A.', 'fullName' => 'Nu Pagamentos S.A. - Instituição de Pagamento'],
            ['code' => 341, 'name' => 'Itaú Unibanco', 'fullName' => 'ITAÚ UNIBANCO S.A.'],
        ]),
    ]);

    $this->getJson(route('financial.accounts.banks', ['search' => 'nu']))
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonPath('0.code', '260')
        ->assertJsonPath('0.name', 'Nu Pagamentos S.A.');

    // Second request uses cache without hitting BrasilAPI again
    $this->getJson(route('financial.accounts.banks'))
        ->assertSuccessful()
        ->assertJsonCount(2);

    Http::assertSentCount(1);
});

it('fails gracefully with an empty array when BrasilAPI is unavailable', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://brasilapi.com.br/api/banks/v1' => Http::response([], 503),
    ]);

    $this->getJson(route('financial.accounts.banks'))
        ->assertSuccessful()
        ->assertExactJson([]);
});
