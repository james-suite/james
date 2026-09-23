<?php

use App\Enums\InvoiceStatus;
use App\Enums\TransactionStatus;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialRecurrence;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can list credit cards', function () {
    FinancialCreditCard::factory()->count(3)->create();

    $this->get(route('financial.cards.index'))
        ->assertSuccessful()
        ->assertViewIs('finance.cards.index');
});

it('can view create credit card page', function () {
    $this->get(route('financial.cards.create'))
        ->assertSuccessful()
        ->assertViewIs('finance.cards.create');
});

it('can store credit card', function () {
    $account = FinancialAccount::factory()->create();

    $data = [
        'name' => 'Meu Cartão Black',
        'financial_account_id' => $account->id,
        'credit_limit' => 15000.00,
        'closing_day' => 10,
        'due_day' => 15,
    ];

    $this->post(route('financial.cards.store'), $data)
        ->assertRedirect(route('financial.cards.show', FinancialCreditCard::latest('id')->first()));

    $this->assertDatabaseHas('financial_credit_cards', [
        'name' => 'Meu Cartão Black',
        'closing_day' => 10,
    ]);
});

it('can view edit credit card page', function () {
    $card = FinancialCreditCard::factory()->create();

    $this->get(route('financial.cards.edit', $card))
        ->assertSuccessful()
        ->assertViewIs('finance.cards.edit');
});

it('can update credit card', function () {
    $card = FinancialCreditCard::factory()->create();

    $data = [
        'name' => 'Cartão Platinum',
        'financial_account_id' => $card->financial_account_id,
        'credit_limit' => 20000.00,
        'closing_day' => 5,
        'due_day' => 12,
    ];

    $this->put(route('financial.cards.update', $card), $data)
        ->assertRedirect(route('financial.cards.show', $card));

    $this->assertDatabaseHas('financial_credit_cards', [
        'id' => $card->id,
        'name' => 'Cartão Platinum',
        'due_day' => 12,
    ]);
});

it('can soft delete credit card', function () {
    $card = FinancialCreditCard::factory()->create();

    $this->delete(route('financial.cards.destroy', $card))
        ->assertRedirect(route('financial.cards.index'));

    $this->assertSoftDeleted($card);
});

it('cannot delete a credit card with an invoice and transactions', function () {
    $card = FinancialCreditCard::factory()->create();
    $invoice = FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create();
    $transaction = FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'financial_account_id' => null,
    ]);

    $this->delete(route('financial.cards.destroy', $card))
        ->assertRedirect(route('financial.cards.show', $card))
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($card);
    $this->assertModelExists($invoice);
    $this->assertModelExists($transaction);
});

it('cannot delete a credit card with an empty invoice', function () {
    $card = FinancialCreditCard::factory()->create();
    $invoice = FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create();

    $this->delete(route('financial.cards.destroy', $card))
        ->assertRedirect(route('financial.cards.show', $card))
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($card);
    $this->assertModelExists($invoice);
});

it('cannot delete a credit card with a recurrence, including a trashed recurrence', function () {
    $card = FinancialCreditCard::factory()->create();
    FinancialRecurrence::factory()->create([
        'financial_account_id' => null,
        'financial_credit_card_id' => $card->id,
    ])->delete();

    $this->delete(route('financial.cards.destroy', $card))
        ->assertRedirect(route('financial.cards.show', $card))
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($card);
});

it('can list trashed credit cards', function () {
    FinancialCreditCard::factory()->count(2)->trashed()->create();

    $this->get(route('financial.cards.trashed'))
        ->assertSuccessful()
        ->assertViewIs('finance.cards.trashed');
});

it('can restore trashed credit card', function () {
    $card = FinancialCreditCard::factory()->trashed()->create();

    $this->patch(route('financial.cards.restore', $card))
        ->assertRedirect(route('financial.cards.show', $card));

    $this->assertNotSoftDeleted($card);
});

it('can force delete credit card', function () {
    $card = FinancialCreditCard::factory()->trashed()->create();

    $this->delete(route('financial.cards.forceDestroy', $card))
        ->assertRedirect(route('financial.cards.trashed'));

    $this->assertDatabaseMissing('financial_credit_cards', [
        'id' => $card->id,
    ]);
});

it('cannot force delete a credit card with an invoice', function () {
    $card = FinancialCreditCard::factory()->trashed()->create();
    $invoice = FinancialCreditCardInvoice::factory()->for($card, 'creditCard')->create();

    $this->delete(route('financial.cards.forceDestroy', $card))
        ->assertRedirect(route('financial.cards.trashed'))
        ->assertSessionHas('error');

    $this->assertSoftDeleted($card);
    $this->assertModelExists($invoice);
});

it('cannot force delete a credit card with a trashed recurrence', function () {
    $card = FinancialCreditCard::factory()->trashed()->create();
    FinancialRecurrence::factory()->create([
        'financial_account_id' => null,
        'financial_credit_card_id' => $card->id,
    ])->delete();

    $this->delete(route('financial.cards.forceDestroy', $card))
        ->assertRedirect(route('financial.cards.trashed'))
        ->assertSessionHas('error');

    $this->assertSoftDeleted($card);
});

it('index shows current open invoice, not the previous paid one', function () {
    // Card with closing on the 10th and due on the 15th
    $card = FinancialCreditCard::factory()->create([
        'closing_day' => 10,
        'due_day' => 15,
    ]);

    // Travel to a day inside the current open invoice period (e.g. the 5th of this month)
    Carbon::setTestNow(Carbon::create(2025, 8, 5));

    $previousMonth = Carbon::create(2025, 7, 1);
    $currentMonth = Carbon::create(2025, 8, 1);

    // Previous invoice (July) – already paid
    $paidInvoice = FinancialCreditCardInvoice::factory()->create([
        'financial_credit_card_id' => $card->id,
        'reference_month' => $previousMonth->toDateString(),
        'closing_date' => $previousMonth->copy()->day(10)->toDateString(),
        'due_date' => $previousMonth->copy()->day(15)->toDateString(),
        'paid_at' => $previousMonth->copy()->day(15)->toDateString(),
        'amount_paid' => 500.00,
    ]);

    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $paidInvoice->id,
        'financial_account_id' => null,
        'type' => 'expense',
        'amount' => 500.00,
        'date' => $previousMonth->copy()->day(5)->toDateString(),
        'status' => TransactionStatus::Posted,
    ]);

    // Current invoice (August) – open with a different amount
    $currentInvoice = FinancialCreditCardInvoice::factory()->create([
        'financial_credit_card_id' => $card->id,
        'reference_month' => $currentMonth->toDateString(),
        'closing_date' => $currentMonth->copy()->day(10)->toDateString(),
        'due_date' => $currentMonth->copy()->day(15)->toDateString(),
        'paid_at' => null,
    ]);

    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $currentInvoice->id,
        'financial_account_id' => null,
        'type' => 'expense',
        'amount' => 250.00,
        'date' => $currentMonth->copy()->day(3)->toDateString(),
        'status' => TransactionStatus::Pending,
    ]);

    $response = $this->get(route('financial.cards.index'))->assertSuccessful();

    $cards = $response->viewData('cards');
    $viewCard = $cards->firstWhere('id', $card->id);

    expect($viewCard->current_invoice_status)->toBe(InvoiceStatus::Open)
        ->and($viewCard->current_invoice_total)->toBe(250.0);

    Carbon::setTestNow();
});

it('uses the paid status color for a paid current invoice', function () {
    Carbon::setTestNow(Carbon::create(2025, 8, 5));

    $card = FinancialCreditCard::factory()->create([
        'closing_day' => 10,
        'due_day' => 15,
    ]);

    $invoice = FinancialCreditCardInvoice::factory()->create([
        'financial_credit_card_id' => $card->id,
        'reference_month' => '2025-08-01',
        'closing_date' => '2025-08-10',
        'due_date' => '2025-08-15',
        'paid_at' => '2025-08-12',
        'amount_paid' => 100.00,
    ]);

    FinancialTransaction::factory()->create([
        'financial_credit_card_invoice_id' => $invoice->id,
        'financial_account_id' => null,
        'type' => 'expense',
        'amount' => 100.00,
        'date' => '2025-08-03',
        'status' => TransactionStatus::Posted,
    ]);

    $this->get(route('financial.cards.index'))
        ->assertSuccessful()
        ->assertSeeInOrder(['Fatura Atual', 'text-green-600']);

    Carbon::setTestNow();
});
