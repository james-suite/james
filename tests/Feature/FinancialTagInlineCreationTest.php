<?php

use App\Models\FinancialTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates a financial tag via json and returns selector payload', function () {
    $response = $this->postJson(route('financial.tags.store'), [
        'name' => 'Café Especial',
        'icon' => 'heroicon-o-tag',
        'color_hex' => '#3b82f6',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['id', 'name', 'color_hex', 'svg'])
        ->assertJsonPath('name', 'Café Especial')
        ->assertJsonPath('color_hex', '#3b82f6');

    $this->assertDatabaseHas('financial_tags', [
        'name' => 'Café Especial',
        'icon' => 'heroicon-o-tag',
        'color_hex' => '#3b82f6',
    ]);
});

it('validates duplicate tag names on json creation', function () {
    FinancialTag::factory()->create(['name' => 'Mercado']);

    $this->postJson(route('financial.tags.store'), [
        'name' => 'Mercado',
        'icon' => 'heroicon-o-tag',
        'color_hex' => '#3b82f6',
    ])->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['name']]);
});
