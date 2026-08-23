<?php

use App\Enums\AuditAction;
use App\Enums\TransactionStatus;
use App\Jobs\ScrapeNfceInvoiceJob;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\FinancialTransactionItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Enums\ActivityEvent;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can list transactions', function () {
    FinancialTransaction::factory()->count(3)->create();

    $this->get(route('financial.transactions.index'))
        ->assertSuccessful()
        ->assertViewIs('finance.transactions.index');
});

it('filters transactions by tags attached directly or to an item', function () {
    $tag = FinancialTag::factory()->create();
    $directlyTaggedTransaction = FinancialTransaction::factory()->create();
    $directlyTaggedTransaction->tags()->attach($tag, ['is_primary' => true]);

    $itemTaggedTransaction = FinancialTransaction::factory()->create();
    $item = $itemTaggedTransaction->items()->create([
        'description' => 'Item com tag',
        'quantity' => 1,
        'unit_price' => 20,
        'total' => 20,
    ]);
    $item->tags()->attach($tag, ['is_primary' => true]);

    FinancialTransaction::factory()->create();

    $this->get(route('financial.transactions.index', ['tag_id' => $tag->id]))
        ->assertSuccessful()
        ->assertViewHas('transactions', function ($transactions) use ($directlyTaggedTransaction, $itemTaggedTransaction): bool {
            return $transactions->pluck('id')->sort()->values()->all() === [
                $directlyTaggedTransaction->id,
                $itemTaggedTransaction->id,
            ];
        });
});

it('can store transaction', function () {
    $account = FinancialAccount::factory()->create();

    $data = [
        'mode' => 'single',
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 125.50,
        'description' => 'Compra no supermercado',
        'date' => now()->format('Y-m-d'),
        'status' => 'posted',
    ];

    $this->post(route('financial.transactions.store'), $data)
        ->assertRedirect(route('financial.transactions.index'));

    $this->assertDatabaseHas('financial_transactions', [
        'amount' => 125.50,
        'description' => 'Compra no supermercado',
    ]);
});

it('does not assign a primary tag to a transaction created with items', function () {
    $account = FinancialAccount::factory()->create();
    $transactionTag = FinancialTag::factory()->create();
    $itemTag = FinancialTag::factory()->create();

    $this->post(route('financial.transactions.store'), [
        'mode' => 'single',
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 30,
        'description' => 'Compra itemizada',
        'date' => '2026-08-20',
        'status' => TransactionStatus::Posted->value,
        'tags' => [$transactionTag->id],
        'primary_tag_id' => $transactionTag->id,
        'items' => [[
            'description' => 'Item principal',
            'quantity' => 1,
            'unit_price' => 30,
            'tags' => [$itemTag->id],
            'primary_tag_id' => $itemTag->id,
        ]],
    ])->assertRedirect(route('financial.transactions.index'));

    $transaction = FinancialTransaction::query()->latest('id')->firstOrFail();
    $storedTransactionTag = $transaction->tags()->whereKey($transactionTag)->firstOrFail();
    $storedItemTag = $transaction->items()->sole()->tags()->whereKey($itemTag)->firstOrFail();

    expect((bool) $storedTransactionTag->pivot->is_primary)->toBeFalse()
        ->and((bool) $storedItemTag->pivot->is_primary)->toBeTrue();
});

it('can view edit transaction page', function () {
    $transaction = FinancialTransaction::factory()->create();

    $this->get(route('financial.transactions.edit', $transaction))
        ->assertSuccessful()
        ->assertViewIs('finance.transactions.edit');
});

it('can update transaction', function () {
    $account = FinancialAccount::factory()->create();
    $transaction = FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
    ]);

    $data = [
        'mode' => 'single',
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'income',
        'amount' => 300.00,
        'description' => 'Venda de bicicleta',
        'date' => now()->format('Y-m-d'),
        'status' => 'pending',
    ];

    $this->put(route('financial.transactions.update', $transaction), $data)
        ->assertRedirect(route('financial.transactions.show', $transaction));

    $this->assertDatabaseHas('financial_transactions', [
        'id' => $transaction->id,
        'type' => 'income',
        'amount' => 300.00,
    ]);
});

it('removes the transaction primary tag when updating a transaction with items', function () {
    $account = FinancialAccount::factory()->create();
    $transaction = FinancialTransaction::factory()->create(['financial_account_id' => $account->id]);
    $transactionTag = FinancialTag::factory()->create();
    $itemTag = FinancialTag::factory()->create();
    $transaction->tags()->attach($transactionTag, ['is_primary' => true]);
    $item = $transaction->items()->create([
        'description' => 'Item existente',
        'quantity' => 1,
        'unit_price' => 20,
        'total' => 20,
    ]);

    $this->put(route('financial.transactions.update', $transaction), [
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 20,
        'description' => 'Compra atualizada',
        'date' => '2026-08-20',
        'status' => TransactionStatus::Posted->value,
        'tags' => [$transactionTag->id],
        'primary_tag_id' => $transactionTag->id,
        'items_present' => 1,
        'items' => [[
            'id' => $item->id,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tags' => [$itemTag->id],
            'primary_tag_id' => $itemTag->id,
        ]],
    ])->assertRedirect(route('financial.transactions.show', $transaction));

    $storedTransactionTag = $transaction->refresh()->tags()->whereKey($transactionTag)->firstOrFail();
    $storedItemTag = $item->refresh()->tags()->whereKey($itemTag)->firstOrFail();

    expect((bool) $storedTransactionTag->pivot->is_primary)->toBeFalse()
        ->and((bool) $storedItemTag->pivot->is_primary)->toBeTrue();
});

it('removes the transaction primary tag when an update preserves omitted items', function () {
    $account = FinancialAccount::factory()->create();
    $transaction = FinancialTransaction::factory()->create(['financial_account_id' => $account->id]);
    $tag = FinancialTag::factory()->create();
    $transaction->tags()->attach($tag, ['is_primary' => true]);
    $transaction->items()->create([
        'description' => 'Item preservado',
        'quantity' => 1,
        'unit_price' => 20,
        'total' => 20,
    ]);

    $this->put(route('financial.transactions.update', $transaction), [
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 20,
        'description' => 'Compra atualizada',
        'date' => '2026-08-20',
        'status' => TransactionStatus::Posted->value,
        'tags' => [$tag->id],
        'primary_tag_id' => $tag->id,
    ])->assertRedirect(route('financial.transactions.show', $transaction));

    $storedTag = $transaction->refresh()->tags()->whereKey($tag)->firstOrFail();

    expect((bool) $storedTag->pivot->is_primary)->toBeFalse();
});

it('preserves transaction items when items are omitted from an update', function () {
    $account = FinancialAccount::factory()->create();
    $transaction = FinancialTransaction::factory()->create(['financial_account_id' => $account->id]);
    $item = $transaction->items()->create([
        'description' => 'Item existente',
        'quantity' => 2,
        'unit_price' => 15,
        'total' => 30,
    ]);

    $this->put(route('financial.transactions.update', $transaction), [
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 30,
        'description' => 'Descrição atualizada',
        'date' => '2026-08-17',
        'status' => TransactionStatus::Posted->value,
    ])->assertRedirect(route('financial.transactions.show', $transaction));

    $this->assertModelExists($item);
});

it('removes all items when the edit form submits an empty item state', function () {
    $account = FinancialAccount::factory()->create();
    $transaction = FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'status' => TransactionStatus::Draft,
        'nfce_access_key' => str_repeat('4', 44),
    ]);
    $item = $transaction->items()->create([
        'description' => 'Desconto da NFC-e',
        'quantity' => 1,
        'unit_price' => -10,
        'total' => -10,
    ]);

    $this->put(route('financial.transactions.update', $transaction), [
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 10,
        'description' => 'Compra importada',
        'date' => '2026-08-17',
        'status' => TransactionStatus::Posted->value,
        'items_present' => 1,
    ])->assertRedirect(route('financial.transactions.show', $transaction));

    expect($transaction->items()->count())->toBe(0);
    expect($item->fresh())->toBeNull();
});

it('allows a transaction primary tag after all items are removed', function () {
    $account = FinancialAccount::factory()->create();
    $transaction = FinancialTransaction::factory()->create(['financial_account_id' => $account->id]);
    $tag = FinancialTag::factory()->create();
    $transaction->tags()->attach($tag, ['is_primary' => false]);
    $transaction->items()->create([
        'description' => 'Item removido',
        'quantity' => 1,
        'unit_price' => 20,
        'total' => 20,
    ]);

    $this->put(route('financial.transactions.update', $transaction), [
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 20,
        'description' => 'Compra sem itens',
        'date' => '2026-08-20',
        'status' => TransactionStatus::Posted->value,
        'tags' => [$tag->id],
        'primary_tag_id' => $tag->id,
        'items_present' => 1,
    ])->assertRedirect(route('financial.transactions.show', $transaction));

    $storedTag = $transaction->refresh()->tags()->whereKey($tag)->firstOrFail();

    expect($transaction->items()->count())->toBe(0)
        ->and((bool) $storedTag->pivot->is_primary)->toBeTrue();
});

it('logs only the removed item and the transaction update when one item is removed', function () {
    $account = FinancialAccount::factory()->create();
    $tag = FinancialTag::factory()->create();
    $transaction = FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'amount' => 60,
    ]);
    $removedItem = $transaction->items()->create([
        'description' => 'Item removido',
        'quantity' => 1,
        'unit_price' => 30,
        'total' => 30,
    ]);
    $removedItem->tags()->attach($tag, ['is_primary' => true]);
    $keptItem = $transaction->items()->create([
        'description' => 'Item mantido',
        'quantity' => 1,
        'unit_price' => 30,
        'total' => 30,
    ]);
    $activityCountBeforeUpdate = Activity::query()->count();

    $this->put(route('financial.transactions.update', $transaction), [
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 30,
        'description' => 'Sem itemização',
        'date' => '2026-08-17',
        'status' => TransactionStatus::Posted->value,
        'items' => [[
            'id' => $keptItem->id,
            'description' => $keptItem->description,
            'quantity' => $keptItem->quantity,
            'unit_price' => $keptItem->unit_price,
            'tags' => [],
        ]],
    ])->assertRedirect(route('financial.transactions.show', $transaction));

    expect($transaction->items()->count())->toBe(1)
        ->and($transaction->items()->sole()->id)->toBe($keptItem->id)
        ->and(Activity::query()->count())->toBe($activityCountBeforeUpdate + 2);

    $this->assertDatabaseMissing('financial_taggables', [
        'financial_taggable_type' => FinancialTransactionItem::class,
        'financial_taggable_id' => $removedItem->id,
    ]);

    $itemActivity = Activity::query()
        ->where('subject_type', FinancialTransactionItem::class)
        ->where('subject_id', $removedItem->id)
        ->where('description', AuditAction::ForceDeleted->value)
        ->where('event', AuditAction::ForceDeleted->value)
        ->sole();

    expect($itemActivity->causer_id)->toBe($this->user->id)
        ->and($itemActivity->created_at)->not->toBeNull()
        ->and(data_get($itemActivity->attribute_changes, 'old.description'))->toBe('Item removido')
        ->and((float) data_get($itemActivity->attribute_changes, 'old.quantity'))->toBe(1.0)
        ->and((float) data_get($itemActivity->attribute_changes, 'old.unit_price'))->toBe(30.0)
        ->and((float) data_get($itemActivity->attribute_changes, 'old.total'))->toBe(30.0);

    $transactionUpdateActivity = Activity::query()
        ->where('subject_type', FinancialTransaction::class)
        ->where('subject_id', $transaction->id)
        ->where('event', ActivityEvent::Updated->value)
        ->sole();

    expect((float) data_get($transactionUpdateActivity->attribute_changes, 'old.amount'))->toBe(60.0)
        ->and((float) data_get($transactionUpdateActivity->attribute_changes, 'attributes.amount'))->toBe(30.0);
    expect(Activity::query()
        ->where('subject_type', FinancialTransactionItem::class)
        ->where('subject_id', $keptItem->id)
        ->where('description', AuditAction::ForceDeleted->value)
        ->doesntExist())->toBeTrue();
});

it('rejects invalid item values', function (string $field, int|string $value) {
    $account = FinancialAccount::factory()->create();

    $item = [
        'description' => 'Item inválido',
        'quantity' => 1,
        'unit_price' => 10,
    ];
    $item[$field] = $value;

    $this->post(route('financial.transactions.store'), [
        'mode' => 'single',
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 10,
        'description' => 'Compra inválida',
        'date' => '2026-08-17',
        'items' => [$item],
    ])->assertSessionHasErrors("items.0.{$field}");
})->with([
    'negative quantity' => ['quantity', -1],
    'zero quantity' => ['quantity', 0],
    'zero unit price' => ['unit_price', 0],
    'formatted zero unit price' => ['unit_price', '0.00'],
]);

it('persists negative item prices', function () {
    $account = FinancialAccount::factory()->create();
    $transaction = FinancialTransaction::factory()->create([
        'financial_account_id' => $account->id,
        'amount' => 10,
    ]);
    $item = $transaction->items()->create([
        'description' => 'Desconto',
        'quantity' => 1,
        'unit_price' => 10,
        'total' => 10,
    ]);

    $this->put(route('financial.transactions.update', $transaction), [
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 10,
        'description' => 'Compra com desconto',
        'date' => '2026-08-17',
        'status' => TransactionStatus::Posted->value,
        'items_present' => 1,
        'items' => [[
            'id' => $item->id,
            'description' => 'Desconto',
            'quantity' => 1,
            'unit_price' => '-10.00',
            'tags' => [],
        ]],
    ])->assertRedirect(route('financial.transactions.show', $transaction));

    expect($item->refresh()->unit_price)->toBe('-10.00')
        ->and($item->total)->toBe('-10.00');

});

it('accepts negative item prices when creating a transaction', function () {
    $account = FinancialAccount::factory()->create();

    $this->post(route('financial.transactions.store'), [
        'mode' => 'single',
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 20,
        'description' => 'Compra com desconto',
        'date' => '2026-08-17',
        'items' => [[
            'description' => 'Desconto',
            'quantity' => 1,
            'unit_price' => '-10.00',
        ]],
    ])->assertRedirect(route('financial.transactions.index'));

    $transaction = FinancialTransaction::query()->latest('id')->firstOrFail();

    expect($transaction->items()->sole()->unit_price)->toBe('-10.00');
});

it('finalizes an imported draft as posted on an account', function () {
    $account = FinancialAccount::factory()->create();
    $transaction = FinancialTransaction::factory()->create([
        'status' => TransactionStatus::Draft,
        'nfce_access_key' => str_repeat('1', 44),
    ]);

    $this->put(route('financial.transactions.update', $transaction), [
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 125.69,
        'description' => 'Compra importada',
        'date' => '2026-08-17',
        'status' => TransactionStatus::Posted->value,
    ])->assertRedirect(route('financial.transactions.show', $transaction));

    expect($transaction->refresh())
        ->financial_account_id->toBe($account->id)
        ->financial_credit_card_invoice_id->toBeNull()
        ->status->toBe(TransactionStatus::Posted);
});

it('finalizes an imported draft as pending on an account', function () {
    $account = FinancialAccount::factory()->create();
    $transaction = FinancialTransaction::factory()->create([
        'status' => TransactionStatus::Draft,
        'nfce_access_key' => str_repeat('2', 44),
    ]);

    $this->put(route('financial.transactions.update', $transaction), [
        'targetType' => 'account',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 125.69,
        'description' => 'Compra importada',
        'date' => '2026-08-17',
        'status' => TransactionStatus::Pending->value,
    ])->assertRedirect(route('financial.transactions.show', $transaction));

    expect($transaction->refresh()->status)->toBe(TransactionStatus::Pending);
});

it('assigns an imported draft to the correct invoice as pending', function () {
    $account = FinancialAccount::factory()->create();
    $card = FinancialCreditCard::factory()->create([
        'financial_account_id' => $account->id,
        'closing_day' => 10,
        'due_day' => 15,
    ]);
    $transaction = FinancialTransaction::factory()->create([
        'status' => TransactionStatus::Draft,
        'nfce_access_key' => str_repeat('3', 44),
    ]);

    $this->put(route('financial.transactions.update', $transaction), [
        'targetType' => 'card',
        'financial_credit_card_id' => $card->id,
        'type' => 'expense',
        'amount' => 125.69,
        'description' => 'Compra importada',
        'date' => '2026-08-11',
    ])->assertRedirect(route('financial.transactions.show', $transaction));

    $transaction->refresh()->load('invoice');

    expect($transaction)
        ->financial_account_id->toBeNull()
        ->status->toBe(TransactionStatus::Pending)
        ->and($transaction->invoice->financial_credit_card_id)->toBe($card->id)
        ->and($transaction->invoice->reference_month->format('Y-m'))->toBe('2026-09');
});

it('can soft delete transaction', function () {
    $transaction = FinancialTransaction::factory()->create();

    $this->delete(route('financial.transactions.destroy', $transaction))
        ->assertRedirect(route('financial.transactions.index'));

    $this->assertSoftDeleted($transaction);
});

it('can list trashed transactions', function () {
    FinancialTransaction::factory()->count(2)->trashed()->create();

    $this->get(route('financial.transactions.trashed'))
        ->assertSuccessful()
        ->assertViewIs('finance.transactions.trashed');
});

it('can restore trashed transaction', function () {
    $transaction = FinancialTransaction::factory()->trashed()->create();

    $this->from(route('financial.transactions.trashed'))
        ->patch(route('financial.transactions.restore', $transaction))
        ->assertRedirect(route('financial.transactions.trashed'));

    $this->assertNotSoftDeleted($transaction);
});

it('can force delete transaction', function () {
    $transaction = FinancialTransaction::factory()->trashed()->create();

    $this->from(route('financial.transactions.trashed'))
        ->delete(route('financial.transactions.forceDestroy', $transaction))
        ->assertRedirect(route('financial.transactions.trashed'));

    $this->assertDatabaseMissing('financial_transactions', [
        'id' => $transaction->id,
    ]);
});

it('cleans transaction and item tag pivots when force deleting', function () {
    $tag = FinancialTag::factory()->create();
    $transaction = FinancialTransaction::factory()->trashed()->create();
    $item = $transaction->items()->create([
        'description' => 'Item com tag',
        'quantity' => 1,
        'unit_price' => 10,
        'total' => 10,
    ]);
    $transaction->tags()->attach($tag, ['is_primary' => true]);
    $item->tags()->attach($tag, ['is_primary' => true]);

    $this->delete(route('financial.transactions.forceDestroy', $transaction))
        ->assertRedirect();

    $this->assertDatabaseMissing('financial_taggables', [
        'financial_taggable_type' => FinancialTransaction::class,
        'financial_taggable_id' => $transaction->id,
    ]);
    $this->assertDatabaseMissing('financial_taggables', [
        'financial_taggable_type' => FinancialTransactionItem::class,
        'financial_taggable_id' => $item->id,
    ]);
});

it('can store a transfer between accounts', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::TRANSFERENCIA_ID,
        'name' => 'Transferência',
        'is_protected' => true,
    ]);

    $accountFrom = FinancialAccount::factory()->create();
    $accountTo = FinancialAccount::factory()->create();

    $data = [
        'from_account_id' => $accountFrom->id,
        'to_account_id' => $accountTo->id,
        'amount' => 500.00,
        'date' => now()->format('Y-m-d'),
        'description' => 'Transferência para poupança',
    ];

    $this->post(route('financial.transactions.transfer.store'), $data)
        ->assertRedirect();

    $this->assertDatabaseHas('financial_transactions', [
        'financial_account_id' => $accountFrom->id,
        'type' => 'expense',
        'amount' => 500.00,
    ]);

    $this->assertDatabaseHas('financial_transactions', [
        'financial_account_id' => $accountTo->id,
        'type' => 'income',
        'amount' => 500.00,
    ]);
});

it('uses the selected fee tag when storing a transfer', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::TRANSFERENCIA_ID,
        'name' => 'Transferência',
    ]);
    $feeTag = FinancialTag::factory()->create();
    $accountFrom = FinancialAccount::factory()->create();
    $accountTo = FinancialAccount::factory()->create();

    $this->post(route('financial.transactions.transfer.store'), [
        'from_account_id' => $accountFrom->id,
        'to_account_id' => $accountTo->id,
        'amount' => 500,
        'date' => '2026-08-17',
        'description' => 'Transferência com taxa',
        'fee_amount' => 5,
        'fee_tag_id' => $feeTag->id,
    ])->assertRedirect();

    $fee = FinancialTransaction::where('description', 'Taxa/imposto — Transferência com taxa')->firstOrFail();

    expect($fee->tags()->whereKey($feeTag->id)->exists())->toBeTrue();
});

it('uses the interest tag as the transfer fee fallback', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::JUROS_ID,
        'name' => 'Juros',
    ]);
    FinancialTag::factory()->create([
        'id' => FinancialTag::TRANSFERENCIA_ID,
        'name' => 'Transferência',
    ]);
    $accountFrom = FinancialAccount::factory()->create();
    $accountTo = FinancialAccount::factory()->create();

    $this->post(route('financial.transactions.transfer.store'), [
        'from_account_id' => $accountFrom->id,
        'to_account_id' => $accountTo->id,
        'amount' => 500,
        'date' => '2026-08-17',
        'description' => 'Transferência com taxa padrão',
        'fee_amount' => 5,
    ])->assertRedirect();

    $fee = FinancialTransaction::where('description', 'Taxa/imposto — Transferência com taxa padrão')->firstOrFail();

    expect($fee->tags()->whereKey(FinancialTag::JUROS_ID)->exists())->toBeTrue();
});

it('rolls back every transfer entry when a related tag cannot be attached', function () {
    FinancialTag::factory()->create([
        'id' => FinancialTag::TRANSFERENCIA_ID,
        'name' => 'Transferência',
    ]);
    $accountFrom = FinancialAccount::factory()->create();
    $accountTo = FinancialAccount::factory()->create();

    expect(fn () => FinancialTransaction::createTransfer(
        $accountFrom,
        $accountTo,
        500,
        Carbon::parse('2026-08-17'),
        'Transferência inválida',
        5,
        999999
    ))->toThrow(QueryException::class);

    expect(FinancialTransaction::query()->count())->toBe(0);
});

it('creates account installments without overflowing month ends', function () {
    $account = FinancialAccount::factory()->create();

    $transactions = FinancialTransaction::createInstallmentsOnAccount(
        $account,
        Carbon::parse('2025-01-31'),
        300,
        3,
        'Compra parcelada'
    );

    expect($transactions->pluck('date')->map->format('Y-m-d')->all())->toBe([
        '2025-01-31',
        '2025-02-28',
        '2025-03-31',
    ]);
});

it('requires authentication to request an NFC-e import', function () {
    auth()->logout();

    $this->post(route('financial.transactions.nfce.import'), ['url' => nfceImportUrl()])
        ->assertRedirect(route('login'));
});

it('validates the NFC-e import URL', function () {
    Bus::fake();

    $this->post(route('financial.transactions.nfce.import'), [])
        ->assertSessionHasErrors('url');

    Bus::assertNothingDispatched();
});

it('rejects unsupported NFC-e URLs', function () {
    Bus::fake();

    $this->from(route('financial.transactions.create'))
        ->post(route('financial.transactions.nfce.import'), ['url' => 'https://untrusted.example.test/nfce?p=43111111111111111111111111111111111111111111'])
        ->assertRedirect(route('financial.transactions.create'))
        ->assertSessionHasErrors('url');

    Bus::assertNothingDispatched();
});

it('rejects NFC-e access keys that already exist, including trashed transactions', function () {
    Bus::fake();
    FinancialTransaction::factory()
        ->nfce('43111111111111111111111111111111111111111111')
        ->trashed()
        ->create();

    $this->from(route('financial.transactions.create'))
        ->post(route('financial.transactions.nfce.import'), ['url' => nfceImportUrl()])
        ->assertRedirect(route('financial.transactions.create'))
        ->assertSessionHasErrors('url');

    Bus::assertNothingDispatched();
});

it('dispatches the NFC-e import job and redirects immediately', function () {
    Bus::fake();

    $this->post(route('financial.transactions.nfce.import'), ['url' => nfceImportUrl()])
        ->assertRedirect(route('financial.transactions.index'))
        ->assertSessionHas('success', 'Importação enviada para processamento. Você será notificado quando terminar.');

    Bus::assertDispatched(ScrapeNfceInvoiceJob::class, function (ScrapeNfceInvoiceJob $job): bool {
        return $job->requesterId === $this->user->id
            && $job->provider === 'svrs'
            && $job->accessKey === '43111111111111111111111111111111111111111111'
            && $job->uf === 'RS'
            && $job->sourceEndpoint === 'https://dfe-portal.svrs.rs.gov.br/Dfe/QrCodeNFce'
            && $job->requestParameterSuffix === '|3|1'
            && ! str_contains(serialize($job), nfceImportUrl());
    });
});

it('retries an NFC-e import from a signed notification action', function () {
    Bus::fake();

    $this->get(nfceRetryUrl($this->user))
        ->assertRedirect(route('notifications.index'))
        ->assertSessionHas('success', 'Importação reenviada para processamento. Você será notificado quando terminar.');

    Bus::assertDispatched(ScrapeNfceInvoiceJob::class, function (ScrapeNfceInvoiceJob $job): bool {
        return $job->requesterId === $this->user->id
            && $job->provider === 'svrs'
            && $job->accessKey === '43111111111111111111111111111111111111111111'
            && $job->uf === 'RS'
            && $job->sourceEndpoint === 'https://dfe-portal.svrs.rs.gov.br/Dfe/QrCodeNFce'
            && $job->requestParameterSuffix === '|3|1';
    });
});

it('rejects NFC-e retries with an invalid signature', function () {
    $this->get(route('financial.transactions.nfce.retry', [
        'payload' => Crypt::encrypt([]),
    ]))->assertForbidden();
});

it('rejects NFC-e retries with an invalid encrypted payload', function () {
    $url = URL::signedRoute('financial.transactions.nfce.retry', [
        'payload' => 'invalid',
    ]);

    $this->get($url)->assertNotFound();
});

it('rejects NFC-e retries requested by another user', function () {
    Bus::fake();
    $otherUser = User::factory()->create();

    $this->get(nfceRetryUrl($otherUser))->assertForbidden();

    Bus::assertNothingDispatched();
});

it('does not retry an NFC-e that is already imported', function () {
    Bus::fake();
    FinancialTransaction::factory()
        ->nfce('43111111111111111111111111111111111111111111')
        ->create();

    $this->get(nfceRetryUrl($this->user))
        ->assertRedirect(route('notifications.index'))
        ->assertSessionHas('success', 'Esta NFC-e já foi importada.');

    Bus::assertNothingDispatched();
});

function nfceImportUrl(): string
{
    return 'https://dfe-portal.svrs.rs.gov.br/Dfe/QrCodeNFce?p=43111111111111111111111111111111111111111111%7C3%7C1';
}

function nfceRetryUrl(User $requester): string
{
    return URL::signedRoute('financial.transactions.nfce.retry', [
        'payload' => Crypt::encrypt([
            'requester_id' => $requester->id,
            'provider' => 'svrs',
            'access_key' => '43111111111111111111111111111111111111111111',
            'uf' => 'RS',
            'source_endpoint' => 'https://dfe-portal.svrs.rs.gov.br/Dfe/QrCodeNFce',
            'request_parameter_suffix' => '|3|1',
        ]),
    ]);
}
