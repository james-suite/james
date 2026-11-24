<?php

use App\Http\Middleware\RememberListingUrl;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Support\SafeBackUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('remembers listing filters and pagination in session and uses them as back-button fallback after editing', function () {
    $account = FinancialAccount::factory()->create(['name' => 'Conta Principal']);

    $filteredUrl = route('financial.accounts.index', ['archived' => 1, 'page' => 2]);

    $this->get($filteredUrl)->assertSuccessful();

    $remembered = session(RememberListingUrl::SESSION_KEY, []);
    $indexPath = parse_url(route('financial.accounts.index'), PHP_URL_PATH);

    expect($remembered[$indexPath] ?? null)->toBe($filteredUrl);

    $safeBackUrl = app(SafeBackUrl::class);
    $effectiveFallback = $safeBackUrl->resolveFallback(
        route('financial.accounts.index'),
        $remembered,
        url('/'),
    );

    $resolvedAfterEdit = $safeBackUrl->resolve(
        route('financial.accounts.edit', $account),
        $effectiveFallback,
        route('financial.accounts.show', $account),
        url('/'),
    );

    expect($resolvedAfterEdit)->toBe($filteredUrl);
});

it('overwrites remembered listing url when filters are cleared', function () {
    $this->get(route('financial.accounts.index', ['archived' => 1, 'page' => 2]))->assertSuccessful();
    $this->get(route('financial.accounts.index'))->assertSuccessful();

    $remembered = session(RememberListingUrl::SESSION_KEY, []);
    $indexPath = parse_url(route('financial.accounts.index'), PHP_URL_PATH);

    expect($remembered[$indexPath] ?? null)->toBe(route('financial.accounts.index'));
});
