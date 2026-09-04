<?php

use App\Enums\SettlementType;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can list settlements index (dashboard)', function () {
    $this->get(route('settlements.index'))->assertSuccessful();
});

it('can list global settlement history', function () {
    $this->get(route('settlements.history'))->assertSuccessful();
});

it('can view contact settlement ledger', function () {
    $contact = Contact::factory()->create();
    $this->get(route('settlements.contact.show', $contact))->assertSuccessful();
});

it('treats a settled contact balance as zero despite floating-point precision', function () {
    $contact = Contact::factory()->create();

    Settlement::create(['contact_id' => $contact->id, 'type' => SettlementType::TheyOwe->value, 'amount' => 650, 'description' => 'Empréstimos', 'date' => '2026-09-04']);
    Settlement::create(['contact_id' => $contact->id, 'type' => SettlementType::IOwe->value, 'amount' => 14.86, 'description' => 'Janta', 'date' => '2026-09-04']);
    Settlement::create(['contact_id' => $contact->id, 'type' => SettlementType::TheyPaid->value, 'amount' => 635.14, 'description' => 'Quitação de saldo', 'date' => '2026-09-04']);

    $response = $this->get(route('settlements.contact.show', $contact));

    $response->assertSuccessful()
        ->assertViewHas('netBalance', 0.0)
        ->assertViewHas('settleUrl', null);
});

it('can view settlement details', function () {
    $contact = Contact::factory()->create();
    $settlement = Settlement::create([
        'contact_id' => $contact->id,
        'type' => SettlementType::TheyOwe->value,
        'amount' => 100,
        'description' => 'Test',
        'date' => '2023-01-01',
    ]);

    $this->get(route('settlements.show_item', $settlement))->assertSuccessful();
});

it('can view creation screen', function () {
    $contact = Contact::factory()->create();
    $this->get(route('settlements.create', $contact))->assertSuccessful();
});

it('can store a settlement', function () {
    $contact = Contact::factory()->create();

    $payload = [
        'type' => SettlementType::TheyOwe->value,
        'amount' => 150,
        'description' => 'Lunch',
        'date' => '2023-01-01',
        'create_transaction' => false,
    ];

    $this->post(route('settlements.store', $contact->id), $payload)
        ->assertRedirect(route('settlements.contact.show', $contact->id));

    $this->assertDatabaseHas('settlements', [
        'contact_id' => $contact->id,
        'amount' => 150,
        'description' => 'Lunch',
    ]);
});

it('can update a settlement', function () {
    $contact = Contact::factory()->create();
    $settlement = Settlement::create([
        'contact_id' => $contact->id,
        'type' => SettlementType::TheyOwe->value,
        'amount' => 100,
        'description' => 'Test',
        'date' => '2023-01-01',
    ]);

    $payload = [
        'type' => SettlementType::TheyOwe->value,
        'amount' => 200,
        'description' => 'Updated Test',
        'date' => '2023-01-02',
        'create_transaction' => false,
    ];

    $this->put(route('settlements.update', $settlement->id), $payload)
        ->assertRedirect(route('settlements.contact.show', $contact->id));

    $this->assertDatabaseHas('settlements', [
        'id' => $settlement->id,
        'amount' => 200,
        'description' => 'Updated Test',
    ]);
});

it('assigns selected tags to a payment made to a contact', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::REEMBOLSO_ID,
        'name' => 'Reembolso',
    ]);
    $primaryTag = FinancialTag::factory()->create();
    $secondaryTag = FinancialTag::factory()->create();
    $contact = Contact::factory()->create();
    $account = FinancialAccount::factory()->create();

    $this->post(route('settlements.store', $contact), [
        'type' => SettlementType::IPaid->value,
        'amount' => 150,
        'description' => 'Jantar',
        'date' => '2026-08-30',
        'create_transaction' => true,
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'tags' => [$primaryTag->id, $secondaryTag->id],
        'primary_tag_id' => $primaryTag->id,
    ])->assertRedirect(route('settlements.contact.show', $contact));

    $transaction = Settlement::firstOrFail()->financialTransaction;

    $this->assertDatabaseHas('financial_taggables', [
        'financial_tag_id' => $primaryTag->id,
        'financial_taggable_id' => $transaction->id,
        'financial_taggable_type' => FinancialTransaction::class,
        'is_primary' => true,
    ]);
    $this->assertDatabaseHas('financial_taggables', [
        'financial_tag_id' => $secondaryTag->id,
        'financial_taggable_id' => $transaction->id,
        'financial_taggable_type' => FinancialTransaction::class,
        'is_primary' => false,
    ]);
    $this->assertDatabaseHas('financial_taggables', [
        'financial_tag_id' => FinancialTag::REEMBOLSO_ID,
        'financial_taggable_id' => $transaction->id,
        'financial_taggable_type' => FinancialTransaction::class,
        'is_primary' => false,
    ]);
});

it('replaces a payment made tag selection when updating a settlement', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::REEMBOLSO_ID,
        'name' => 'Reembolso',
    ]);
    $oldTag = FinancialTag::factory()->create();
    $newTag = FinancialTag::factory()->create();
    $contact = Contact::factory()->create();
    $account = FinancialAccount::factory()->create();

    $this->post(route('settlements.store', $contact), [
        'type' => SettlementType::IPaid->value,
        'amount' => 150,
        'description' => 'Jantar',
        'date' => '2026-08-30',
        'create_transaction' => true,
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'tags' => [$oldTag->id],
        'primary_tag_id' => $oldTag->id,
    ]);

    $settlement = Settlement::firstOrFail();

    $this->put(route('settlements.update', $settlement), [
        'type' => SettlementType::IPaid->value,
        'amount' => 175,
        'description' => 'Jantar atualizado',
        'date' => '2026-08-31',
        'create_transaction' => true,
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'tags' => [$newTag->id],
        'primary_tag_id' => $newTag->id,
    ])->assertRedirect(route('settlements.contact.show', $contact));

    $transaction = $settlement->refresh()->financialTransaction;

    $this->assertDatabaseMissing('financial_taggables', [
        'financial_tag_id' => $oldTag->id,
        'financial_taggable_id' => $transaction->id,
        'financial_taggable_type' => FinancialTransaction::class,
    ]);
    $this->assertDatabaseHas('financial_taggables', [
        'financial_tag_id' => $newTag->id,
        'financial_taggable_id' => $transaction->id,
        'financial_taggable_type' => FinancialTransaction::class,
        'is_primary' => true,
    ]);
    $this->assertDatabaseHas('financial_taggables', [
        'financial_tag_id' => FinancialTag::REEMBOLSO_ID,
        'financial_taggable_id' => $transaction->id,
        'financial_taggable_type' => FinancialTransaction::class,
        'is_primary' => false,
    ]);
});

it('preselects custom payment tags when editing a settlement', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::REEMBOLSO_ID,
        'name' => 'Reembolso',
    ]);
    $customTag = FinancialTag::factory()->create();
    $contact = Contact::factory()->create();
    $transaction = FinancialTransaction::factory()->create();
    $transaction->tags()->attach([
        FinancialTag::REEMBOLSO_ID => ['is_primary' => false],
        $customTag->id => ['is_primary' => true],
    ]);
    $settlement = Settlement::create([
        'contact_id' => $contact->id,
        'financial_transaction_id' => $transaction->id,
        'type' => SettlementType::IPaid->value,
        'amount' => 150,
        'description' => 'Jantar',
        'date' => '2026-08-30',
    ]);

    $this->get(route('settlements.edit', $settlement))
        ->assertSuccessful()
        ->assertViewHas('defaultTags', fn (array $tags): bool => $tags === [$customTag->id])
        ->assertViewHas('defaultPrimaryTag', $customTag->id);
});

it('keeps reimbursement as the primary tag when no payment tags are selected', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::REEMBOLSO_ID,
        'name' => 'Reembolso',
    ]);
    $contact = Contact::factory()->create();
    $account = FinancialAccount::factory()->create();

    $this->post(route('settlements.store', $contact), [
        'type' => SettlementType::IPaid->value,
        'amount' => 150,
        'description' => 'Jantar',
        'date' => '2026-08-30',
        'create_transaction' => true,
        'targetType' => 'account',
        'financial_account_id' => $account->id,
    ])->assertRedirect(route('settlements.contact.show', $contact));

    $transaction = Settlement::firstOrFail()->financialTransaction;

    $this->assertDatabaseHas('financial_taggables', [
        'financial_tag_id' => FinancialTag::REEMBOLSO_ID,
        'financial_taggable_id' => $transaction->id,
        'financial_taggable_type' => FinancialTransaction::class,
        'is_primary' => true,
    ]);
});

it('rejects a payment tag that does not exist', function () {
    $contact = Contact::factory()->create();
    $account = FinancialAccount::factory()->create();

    $this->from(route('settlements.create', $contact))
        ->post(route('settlements.store', $contact), [
            'type' => SettlementType::IPaid->value,
            'amount' => 150,
            'description' => 'Jantar',
            'date' => '2026-08-30',
            'create_transaction' => true,
            'targetType' => 'account',
            'financial_account_id' => $account->id,
            'tags' => [999],
            'primary_tag_id' => 999,
        ])
        ->assertRedirect(route('settlements.create', $contact))
        ->assertSessionHasErrors(['tags.0', 'primary_tag_id']);

    $this->assertDatabaseCount('settlements', 0);
    $this->assertDatabaseCount('financial_transactions', 0);
});

it('does not assign custom tags to other settlement types', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::REEMBOLSO_ID,
        'name' => 'Reembolso',
    ]);
    $customTag = FinancialTag::factory()->create();
    $contact = Contact::factory()->create();
    $account = FinancialAccount::factory()->create();

    $this->post(route('settlements.store', $contact), [
        'type' => SettlementType::TheyPaid->value,
        'amount' => 150,
        'description' => 'Reembolso recebido',
        'date' => '2026-08-30',
        'create_transaction' => true,
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'tags' => [$customTag->id],
        'primary_tag_id' => $customTag->id,
    ])->assertRedirect(route('settlements.contact.show', $contact));

    $transaction = Settlement::firstOrFail()->financialTransaction;

    $this->assertDatabaseMissing('financial_taggables', [
        'financial_tag_id' => $customTag->id,
        'financial_taggable_id' => $transaction->id,
        'financial_taggable_type' => FinancialTransaction::class,
    ]);
    $this->assertDatabaseHas('financial_taggables', [
        'financial_tag_id' => FinancialTag::REEMBOLSO_ID,
        'financial_taggable_id' => $transaction->id,
        'financial_taggable_type' => FinancialTransaction::class,
        'is_primary' => true,
    ]);
});

it('can soft delete a settlement', function () {
    $contact = Contact::factory()->create();
    $settlement = Settlement::create([
        'contact_id' => $contact->id,
        'type' => SettlementType::TheyOwe->value,
        'amount' => 100,
        'description' => 'Test',
        'date' => '2023-01-01',
    ]);

    $this->delete(route('settlements.destroy', $settlement))
        ->assertRedirect();

    $this->assertSoftDeleted('settlements', ['id' => $settlement->id]);
});

it('rolls back the related transaction when deleting a settlement fails', function () {
    $contact = Contact::factory()->create();
    $transaction = FinancialTransaction::factory()->create();
    $settlement = Settlement::create([
        'contact_id' => $contact->id,
        'financial_transaction_id' => $transaction->id,
        'type' => SettlementType::TheyOwe->value,
        'amount' => 100,
        'description' => 'Test',
        'date' => '2023-01-01',
    ]);

    $eventName = 'eloquent.deleting: '.Settlement::class;
    Event::listen($eventName, fn () => throw new RuntimeException('Simulated failure'));

    try {
        $this->delete(route('settlements.destroy', $settlement))->assertServerError();
    } finally {
        Event::forget($eventName);
    }

    $this->assertNotSoftDeleted($settlement);
    $this->assertNotSoftDeleted($transaction);
});

it('calculates global totals based on net balance per contact, not gross amounts', function () {
    // Lucas: ele me deve R$60 (TheyOwe), eu devo R$56,50 (IOwe), ele pagou R$3,50 (TheyPaid)
    // Net balance do Lucas: toReceive = max(0, 60 - 3.50) = 56.50, toPay = max(0, 56.50) = 56.50 → net = 0
    // O bug: o totalizador somava to_receive e to_pay brutos separadamente
    // O correto: como net = 0, Lucas não deve contribuir para nenhum dos totais globais
    $lucas = Contact::factory()->create();
    Settlement::create(['contact_id' => $lucas->id, 'type' => SettlementType::TheyOwe->value, 'amount' => 60, 'description' => 'Fernet', 'date' => '2026-09-08']);
    Settlement::create(['contact_id' => $lucas->id, 'type' => SettlementType::IOwe->value, 'amount' => 56.50, 'description' => 'Janta', 'date' => '2026-09-08']);
    Settlement::create(['contact_id' => $lucas->id, 'type' => SettlementType::TheyPaid->value, 'amount' => 3.50, 'description' => 'Quitação', 'date' => '2026-09-08']);

    // Mateus: me deve R$50 sem contrapartida → deve aparecer em A Receber
    $mateus = Contact::factory()->create();
    Settlement::create(['contact_id' => $mateus->id, 'type' => SettlementType::TheyOwe->value, 'amount' => 50, 'description' => 'Almoço', 'date' => '2026-09-08']);

    $response = $this->get(route('settlements.index'));
    $response->assertSuccessful();

    $toReceive = $response->viewData('toReceive');
    $toPay = $response->viewData('toPay');
    $netBalance = $response->viewData('netBalance');

    // Lucas net = 0, não deve contribuir para nenhum lado
    // Mateus net = +50, deve aparecer em A Receber
    expect($toReceive)->toBe(50.0)
        ->and($toPay)->toBe(0.0)
        ->and($netBalance)->toBe(50.0);
});
