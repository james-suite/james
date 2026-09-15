<?php

use App\Support\SafeBackUrl;

it('keeps a safe same-origin contextual url', function () {
    $previousUrl = 'http://localhost/financial/transactions?account_id=4';

    expect(app(SafeBackUrl::class)->resolve(
        $previousUrl,
        'http://localhost/financial/transactions',
        'http://localhost/financial/transactions/42',
        'http://localhost',
    ))->toBe($previousUrl);
});

it('falls back for external, form and technical urls', function (string $previousUrl) {
    expect(app(SafeBackUrl::class)->resolve(
        $previousUrl,
        'http://localhost/financial/transactions',
        'http://localhost/financial/transactions/42',
        'http://localhost',
    ))->toBe('http://localhost/financial/transactions');
})->with([
    'external' => 'https://example.com/account',
    'create form' => 'http://localhost/financial/transactions/create',
    'edit form' => 'http://localhost/financial/transactions/42/edit',
    'chart endpoint' => 'http://localhost/financial/dashboard/chart-data',
]);
