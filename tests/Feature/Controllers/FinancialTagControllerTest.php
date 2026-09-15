<?php

use App\Models\FinancialTag;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can list tags', function () {
    FinancialTag::factory()->count(3)->create();

    $this->get(route('financial.tags.index'))
        ->assertSuccessful()
        ->assertViewIs('finance.tags.index');
});

it('can view create tag page', function () {
    $this->get(route('financial.tags.create'))
        ->assertSuccessful()
        ->assertViewIs('finance.tags.create');
});

it('can store tag', function () {
    $data = [
        'name' => 'Nova Tag',
        'color_hex' => '#00ff00',
        'icon' => 'heroicon-o-tag',
    ];

    $this->post(route('financial.tags.store'), $data)
        ->assertRedirect(route('financial.tags.index'));

    $this->assertDatabaseHas('financial_tags', [
        'name' => 'Nova Tag',
        'color_hex' => '#00ff00',
    ]);
});

it('can add selected suggested tags', function () {
    $tag = config('finance.default_tags.0');

    $this->post(route('financial.tags.defaults'), ['tags' => [$tag['name']]])
        ->assertRedirect(route('financial.tags.index'));

    $this->assertDatabaseHas('financial_tags', [
        'name' => $tag['name'],
        'icon' => $tag['icon'],
        'color_hex' => $tag['color_hex'],
    ]);
});

it('can view edit tag page', function () {
    $tag = FinancialTag::factory()->create();

    $this->get(route('financial.tags.edit', $tag))
        ->assertSuccessful()
        ->assertViewIs('finance.tags.edit');
});

it('can update tag', function () {
    $tag = FinancialTag::factory()->create();

    $data = [
        'name' => 'Tag Editada',
        'color_hex' => '#ff0000',
        'icon' => 'heroicon-s-star',
    ];

    $this->put(route('financial.tags.update', $tag), $data)
        ->assertRedirect(route('financial.tags.show', $tag));

    $this->assertDatabaseHas('financial_tags', [
        'id' => $tag->id,
        'name' => 'Tag Editada',
        'icon' => 'heroicon-s-star',
    ]);
});

it('can delete tag', function () {
    $tag = FinancialTag::factory()->create();

    $this->delete(route('financial.tags.destroy', $tag))
        ->assertRedirect(route('financial.tags.index'));

    // The tag model is not soft deleted based on its migrations
    $this->assertDatabaseMissing('financial_tags', [
        'id' => $tag->id,
    ]);
});
