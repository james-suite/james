<?php

use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialTransaction;
use App\Models\Settlement;
use App\Models\SettlementGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can list settlement groups', function () {
    $this->get(route('settlements.groups.index'))->assertSuccessful();
});

it('can view creation screen', function () {
    $contact = Contact::factory()->create();
    $this->get(route('settlements.groups.create', ['contacts' => $contact->id]))->assertSuccessful();
});

it('can view group details', function () {
    $group = SettlementGroup::create([
        'description' => 'Test',
        'total_amount' => 100,
        'date' => '2023-01-01',
        'mode' => 'equal',
    ]);

    $this->get(route('settlements.groups.show', $group))->assertSuccessful();
});

it('can store a settlement group', function () {
    $contact = Contact::factory()->create();

    $payload = [
        'description' => 'Pizza',
        'total_amount' => 100,
        'date' => '2023-01-01',
        'mode' => 'equal',
        'my_amount' => 50,
        'create_transaction' => false,
        'contacts' => [
            ['id' => $contact->id, 'amount' => 50],
        ],
    ];

    $this->post(route('settlements.groups.store'), $payload)
        ->assertRedirect(route('settlements.index'));

    $this->assertDatabaseHas('settlement_groups', [
        'description' => 'Pizza',
        'total_amount' => 100,
    ]);
});

it('uses only the selected account when both group transaction targets are submitted', function () {
    $contact = Contact::factory()->create();
    $account = FinancialAccount::factory()->create();
    $card = FinancialCreditCard::factory()->create();

    $this->post(route('settlements.groups.store'), [
        'description' => 'Conta na conta corrente',
        'total_amount' => 100,
        'date' => '2023-01-01',
        'mode' => 'equal',
        'my_amount' => 50,
        'create_transaction' => true,
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'financial_credit_card_id' => $card->id,
        'contacts' => [
            ['id' => $contact->id, 'amount' => 50],
        ],
    ])->assertRedirect(route('settlements.index'));

    $transaction = FinancialTransaction::query()->latest('id')->firstOrFail();

    expect($transaction->financial_account_id)->toBe($account->id)
        ->and($transaction->financial_credit_card_invoice_id)->toBeNull();
});

it('rejects duplicate contacts and inconsistent totals', function () {
    $contact = Contact::factory()->create();

    $this->post(route('settlements.groups.store'), [
        'description' => 'Pizza',
        'total_amount' => 100,
        'date' => '2023-01-01',
        'mode' => 'exact',
        'my_amount' => 30,
        'create_transaction' => false,
        'contacts' => [
            ['id' => $contact->id, 'amount' => 30],
            ['id' => $contact->id, 'amount' => 30],
        ],
    ])->assertSessionHasErrors(['contacts.1.id']);

    $this->post(route('settlements.groups.store'), [
        'description' => 'Pizza',
        'total_amount' => 100,
        'date' => '2023-01-01',
        'mode' => 'exact',
        'my_amount' => 30,
        'create_transaction' => false,
        'contacts' => [
            ['id' => $contact->id, 'amount' => 60],
        ],
    ])->assertSessionHasErrors(['total_amount']);

    expect(SettlementGroup::query()->exists())->toBeFalse();
});

it('uses the ui cent remainder policy for equal divisions', function () {
    $contacts = Contact::factory()->count(2)->create();

    $this->post(route('settlements.groups.store'), [
        'description' => 'Conta de cem reais',
        'total_amount' => '100.00',
        'date' => '2023-01-01',
        'mode' => 'equal',
        'my_amount' => '33.34',
        'create_transaction' => false,
        'contacts' => $contacts->map(fn (Contact $contact): array => [
            'id' => $contact->id,
            'amount' => '33.33',
        ])->all(),
    ])->assertRedirect(route('settlements.index'));

    $group = SettlementGroup::query()->sole();

    expect($group->settlements()->pluck('amount')->all())->toEqual([33.33, 33.33]);
});

it('rejects equal divisions that do not follow the ui cent remainder policy', function () {
    $contacts = Contact::factory()->count(2)->create();

    $this->post(route('settlements.groups.store'), [
        'description' => 'Conta de cem reais',
        'total_amount' => '100.00',
        'date' => '2023-01-01',
        'mode' => 'equal',
        'my_amount' => '33.33',
        'create_transaction' => false,
        'contacts' => [
            ['id' => $contacts[0]->id, 'amount' => '33.34'],
            ['id' => $contacts[1]->id, 'amount' => '33.33'],
        ],
    ])->assertSessionHasErrors(['my_amount', 'contacts.0.amount']);
});

it('can view edit screen', function () {
    $group = SettlementGroup::create([
        'description' => 'Test',
        'total_amount' => 100,
        'date' => '2023-01-01',
        'mode' => 'equal',
    ]);

    $this->get(route('settlements.groups.edit', $group))->assertSuccessful();
});

it('can update a settlement group', function () {
    $contact = Contact::factory()->create();
    $group = SettlementGroup::create([
        'description' => 'Test',
        'total_amount' => 100,
        'date' => '2023-01-01',
        'mode' => 'equal',
    ]);

    $payload = [
        'description' => 'Updated Pizza',
        'total_amount' => 200,
        'date' => '2023-01-02',
        'mode' => 'equal',
        'my_amount' => 100,
        'create_transaction' => false,
        'contacts' => [
            ['id' => $contact->id, 'amount' => 100],
        ],
    ];

    $this->put(route('settlements.groups.update', $group), $payload)
        ->assertRedirect(route('settlements.index'));

    $this->assertDatabaseHas('settlement_groups', [
        'id' => $group->id,
        'description' => 'Updated Pizza',
        'total_amount' => 200,
    ]);
});

it('rejects an inconsistent distribution when updating a settlement group', function () {
    $contact = Contact::factory()->create();
    $group = SettlementGroup::create([
        'description' => 'Test',
        'total_amount' => 100,
        'date' => '2023-01-01',
        'mode' => 'exact',
    ]);

    $this->put(route('settlements.groups.update', $group), [
        'description' => 'Invalid update',
        'total_amount' => 200,
        'date' => '2023-01-02',
        'mode' => 'exact',
        'my_amount' => 50,
        'create_transaction' => false,
        'contacts' => [
            ['id' => $contact->id, 'amount' => 100],
        ],
    ])->assertSessionHasErrors(['total_amount']);

    expect($group->fresh()->description)->toBe('Test');
});

it('can soft delete a settlement group', function () {
    $group = SettlementGroup::create([
        'description' => 'To Delete',
        'total_amount' => 100,
        'date' => '2023-01-01',
        'mode' => 'equal',
    ]);

    $this->delete(route('settlements.groups.destroy', $group))
        ->assertRedirect();

    $this->assertSoftDeleted('settlement_groups', ['id' => $group->id]);
});

it('force deletes settlement children through their models', function () {
    $contact = Contact::factory()->create();
    $group = SettlementGroup::create([
        'description' => 'To Delete Permanently',
        'total_amount' => 100,
        'date' => '2023-01-01',
        'mode' => 'equal',
    ]);
    $settlement = Settlement::create([
        'contact_id' => $contact->id,
        'settlement_group_id' => $group->id,
        'type' => 'they_owe',
        'amount' => 100,
        'description' => 'Settlement',
        'date' => '2023-01-01',
    ]);

    $group->delete();
    $settlement->delete();

    $this->delete(route('settlements.groups.force-delete', $group->id))
        ->assertRedirect(route('settlements.groups.trashed'));

    $this->assertDatabaseMissing('settlements', ['id' => $settlement->id]);
    $this->assertDatabaseMissing('settlement_groups', ['id' => $group->id]);
    expect(Activity::query()
        ->where('subject_type', Settlement::class)
        ->where('subject_id', $settlement->id)
        ->where('event', 'forceDeleted')
        ->exists())->toBeTrue();
});
