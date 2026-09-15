<?php

use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can view reports page', function () {
    $this->get(route('financial.reports'))
        ->assertSuccessful()
        ->assertViewIs('finance.reports')
        ->assertSee('type="button"', false)
        ->assertSee('filterByTag(', false)
        ->assertSee('Sem movimentações para exibir', false)
        ->assertSee('Sem fluxo para os filtros escolhidos', false);
});

it('defaults to all_time period when no period is specified', function () {
    $this->get(route('financial.reports'))
        ->assertSuccessful()
        ->assertViewHas('period', 'all_time');
});

it('respects the period query parameter', function () {
    $this->get(route('financial.reports', ['period' => 'this_month']))
        ->assertSuccessful()
        ->assertViewHas('period', 'this_month');
});

it('provides a period summary for compact report views', function () {
    FinancialTransaction::factory()->posted()->create([
        'type' => 'income',
        'amount' => 120,
        'date' => '2026-08-18',
    ]);
    FinancialTransaction::factory()->posted()->create([
        'type' => 'expense',
        'amount' => 50,
        'date' => '2026-08-18',
    ]);

    $this->get(route('financial.reports', [
        'period' => 'custom',
        'startDate' => '2026-08-18',
        'endDate' => '2026-08-18',
    ]))
        ->assertSuccessful()
        ->assertViewHas('summary', [
            'income' => 120.0,
            'expense' => 50.0,
            'balance' => 70.0,
        ]);
});

it('filters report rows by transaction and item tags before paginating', function () {
    $tag = FinancialTag::factory()->create();
    $startDate = '2026-08-18';
    $endDate = '2026-08-19';

    FinancialTransaction::factory()->posted()->count(50)->create([
        'date' => $startDate,
    ])->each(function (FinancialTransaction $transaction) use ($tag): void {
        $transaction->tags()->attach($tag, ['is_primary' => true]);
    });

    $itemTaggedTransaction = FinancialTransaction::factory()->posted()->create([
        'amount' => 10,
        'date' => $endDate,
    ]);
    $item = $itemTaggedTransaction->items()->create([
        'description' => 'Item com tag',
        'quantity' => 1,
        'unit_price' => 10,
        'total' => 10,
    ]);
    $item->tags()->attach($tag, ['is_primary' => true]);

    $response = $this->get(route('financial.reports', [
        'period' => 'custom',
        'startDate' => $startDate,
        'endDate' => $endDate,
        'tag_id' => $tag->id,
        'page' => 2,
    ]));

    $response->assertSuccessful()
        ->assertViewHas('selectedTagId', $tag->id);

    $transactions = $response->viewData('transactions');

    expect($transactions->total())->toBe(51)
        ->and($transactions->count())->toBe(1)
        ->and($transactions->currentPage())->toBe(2)
        ->and($transactions->lastPage())->toBe(2)
        ->and($transactions->previousPageUrl())->toContain('tag_id='.$tag->id)
        ->and($transactions->previousPageUrl())->toEndWith('#transactions-table')
        ->and($transactions->first()->id)->toBe($itemTaggedTransaction->id)
        ->and($transactions->first()->description)->toBe($itemTaggedTransaction->description.' - '.$item->description)
        ->and((float) $transactions->first()->amount)->toBe(10.0)
        ->and($transactions->first()->tags->modelKeys())->toBe([$tag->id]);
});

it('applies a tag filter to report summaries and chart data', function () {
    $tag = FinancialTag::factory()->create();
    $tagged = FinancialTransaction::factory()->posted()->create([
        'type' => 'income',
        'amount' => 100,
        'date' => '2026-08-18',
    ]);
    $tagged->tags()->attach($tag, ['is_primary' => true]);

    FinancialTransaction::factory()->posted()->create([
        'type' => 'income',
        'amount' => 250,
        'date' => '2026-08-18',
    ]);

    $response = $this->get(route('financial.reports', [
        'period' => 'custom',
        'startDate' => '2026-08-18',
        'endDate' => '2026-08-18',
        'tag_id' => $tag->id,
    ]));

    $response->assertSuccessful()
        ->assertViewHas('summary', [
            'income' => 100.0,
            'expense' => 0,
            'balance' => 100.0,
        ])
        ->assertViewHas('evolution', fn (array $evolution): bool => end($evolution)['income'] === 100.0);
});

it('offers a clear action when a report tag filter has no transactions', function () {
    $response = $this->get(route('financial.reports', [
        'period' => 'custom',
        'startDate' => '2026-08-18',
        'endDate' => '2026-08-18',
        'tag_id' => 999999,
    ]));

    $response->assertSuccessful()
        ->assertSee('Nenhuma transação corresponde a esta tag', false)
        ->assertSee('Remover filtro', false);
});
