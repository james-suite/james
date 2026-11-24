<?php

use App\Enums\TransactionStatus;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('selectively reverts a single field while preserving later edits to other fields', function () {
    $contact = Contact::factory()->create([
        'name' => 'João Antigo',
        'notes' => 'Nota inicial',
    ]);

    $contact->update([
        'name' => 'João Novo',
        'notes' => 'Nota alterada',
    ]);

    $firstUpdateLog = Activity::query()
        ->where('subject_type', Contact::class)
        ->where('subject_id', $contact->id)
        ->where('description', 'updated')
        ->latest('id')
        ->firstOrFail();

    $contact->update(['notes' => 'Nota mais recente']);

    $this->post(route('audit.revert', $firstUpdateLog), [
        'fields' => ['name'],
    ])->assertRedirect(route('audit.show', $firstUpdateLog))
        ->assertSessionHas('success');

    $contact->refresh();

    expect($contact->name)->toBe('João Antigo')
        ->and($contact->notes)->toBe('Nota mais recente');

    expect(Activity::query()
        ->where('subject_type', Contact::class)
        ->where('subject_id', $contact->id)
        ->where('description', 'updated')
        ->count())->toBe(3);
});

it('blocks reverting non-allowlisted financial fields such as transaction amount', function () {
    $account = FinancialAccount::factory()->create();
    $transaction = FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'description' => 'Mercado',
        'amount' => 100.00,
        'status' => TransactionStatus::Posted,
    ]);

    $transaction->update([
        'description' => 'Supermercado',
        'amount' => 150.00,
    ]);

    $updateLog = Activity::query()
        ->where('subject_type', FinancialTransaction::class)
        ->where('subject_id', $transaction->id)
        ->where('description', 'updated')
        ->latest('id')
        ->firstOrFail();

    $this->post(route('audit.revert', $updateLog), [
        'fields' => ['amount'],
    ])->assertRedirect(route('audit.show', $updateLog))
        ->assertSessionHas('error');

    expect((float) $transaction->fresh()->amount)->toBe(150.0);
});

it('blocks reverting protected tags and soft-deleted subjects', function () {
    $tag = FinancialTag::factory()->create([
        'name' => 'Tag Protegida',
        'is_protected' => true,
    ]);
    $tag->update(['name' => 'Tag Alterada']);

    $tagLog = Activity::query()
        ->where('subject_type', FinancialTag::class)
        ->where('subject_id', $tag->id)
        ->where('description', 'updated')
        ->latest('id')
        ->firstOrFail();

    $this->post(route('audit.revert', $tagLog), [
        'fields' => ['name'],
    ])->assertRedirect(route('audit.show', $tagLog))
        ->assertSessionHas('error');

    expect($tag->fresh()->name)->toBe('Tag Alterada');
});
