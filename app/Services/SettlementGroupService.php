<?php

namespace App\Services;

use App\Enums\SettlementType;
use App\Enums\TransactionStatus;
use App\Models\Contact;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\FinancialTransactionItem;
use App\Models\Settlement;
use App\Models\SettlementGroup;
use App\Traits\HandlesAttachments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SettlementGroupService
{
    use HandlesAttachments;

    /**
     * Create a new settlement group with its child settlements.
     *
     * @param  array{
     *     description: string,
     *     total_amount: float,
     *     date: string,
     *     mode: string,
     *     contacts: array<int, array{id: int, amount: float}>,
     *     my_amount: float,
     *     create_transaction?: bool,
     *     targetType?: string,
     *     financial_account_id?: int,
     *     financial_credit_card_id?: int,
     *     financial_tag_ids?: array<int>,
     * } $validated
     */
    public function storeGroup(array $validated): SettlementGroup
    {
        $this->assertValidDistribution($validated);

        return DB::transaction(function () use ($validated) {
            $date = Carbon::parse($validated['date']);
            $financialTransactionId = null;

            // 1. Create FinancialTransaction if requested
            if (! empty($validated['create_transaction'])) {
                $transaction = $this->createTransaction($validated, $date);
                $financialTransactionId = $transaction->id;
            }

            // 2. Create the SettlementGroup
            $group = SettlementGroup::create([
                'description' => $validated['description'],
                'total_amount' => $validated['total_amount'],
                'date' => $date,
                'mode' => $validated['mode'],
                'financial_transaction_id' => $financialTransactionId,
            ]);

            // 3. Create child settlements for each contact
            foreach ($validated['contacts'] as $contactData) {
                $contact = Contact::findOrFail($contactData['id']);

                Settlement::create([
                    'contact_id' => $contact->id,
                    'settlement_group_id' => $group->id,
                    'type' => SettlementType::TheyOwe->value,
                    'amount' => $contactData['amount'],
                    'description' => $validated['description'].' - '.$contact->name,
                    'date' => $date,
                ]);
            }

            // 4. Attach media if provided
            $this->syncAttachments($group, $validated);

            return $group;
        });
    }

    /**
     * Update an existing settlement group (wipe & replace children).
     */
    public function updateGroup(SettlementGroup $group, array $validated): SettlementGroup
    {
        $this->assertValidDistribution($validated);

        return DB::transaction(function () use ($group, $validated) {
            $date = Carbon::parse($validated['date']);

            // 1. Manage FinancialTransaction
            if (! empty($validated['create_transaction'])) {
                $existingTransaction = $group->financialTransaction;

                if ($existingTransaction) {
                    $existingTransaction->forceDelete();
                }

                $transaction = $this->createTransaction($validated, $date);
                $group->financial_transaction_id = $transaction->id;
            } else {
                if ($group->financial_transaction_id) {
                    $existingTransaction = $group->financialTransaction;
                    if ($existingTransaction) {
                        $existingTransaction->forceDelete();
                    }
                    $group->financial_transaction_id = null;
                }
            }

            // 2. Update group metadata
            $group->description = $validated['description'];
            $group->total_amount = $validated['total_amount'];
            $group->date = $date;
            $group->mode = $validated['mode'];
            $group->save();

            // 3. Wipe old children
            $group->forceDeleteSettlements();

            // 4. Replace with new children
            foreach ($validated['contacts'] as $contactData) {
                $contact = Contact::findOrFail($contactData['id']);

                Settlement::create([
                    'contact_id' => $contact->id,
                    'settlement_group_id' => $group->id,
                    'type' => SettlementType::TheyOwe->value,
                    'amount' => $contactData['amount'],
                    'description' => $validated['description'].' - '.$contact->name,
                    'date' => $date,
                ]);
            }

            // 5. Handle media attachments
            $this->syncAttachments($group, $validated);

            return $group->fresh();
        });
    }

    /**
     * Destroy a settlement group and its related data.
     */
    public function destroyGroup(SettlementGroup $group): void
    {
        DB::transaction(function () use ($group) {
            if ($group->financialTransaction) {
                $group->financialTransaction->forceDelete();
            }

            // Soft-delete group (cascade will soft-delete children)
            $group->deleteSettlements();
            $group->delete();
        });
    }

    /**
     * Create the financial transaction with items for a split bill.
     */
    private function createTransaction(array $validated, Carbon $date): FinancialTransaction
    {
        $data = [
            'type' => 'expense',
            'amount' => $validated['total_amount'],
            'description' => $validated['description'].' (Conta Dividida)',
            'date' => $date,
            'status' => TransactionStatus::Posted,
            'financial_account_id' => null,
            'financial_credit_card_invoice_id' => null,
        ];

        if ($validated['targetType'] === 'card') {
            $card = FinancialCreditCard::findOrFail($validated['financial_credit_card_id']);
            $invoice = FinancialCreditCardInvoice::resolveForDate($card, $date);
            $data['financial_credit_card_invoice_id'] = $invoice->id;
            $data['status'] = TransactionStatus::Pending;
        } else {
            $data['financial_account_id'] = $validated['financial_account_id'];
        }

        $transaction = FinancialTransaction::create($data);

        // Item: "Minha Parte" (user's share, tagged with user-selected tags)
        $myItem = FinancialTransactionItem::create([
            'financial_transaction_id' => $transaction->id,
            'description' => 'Minha Parte',
            'quantity' => 1,
            'unit_price' => $validated['my_amount'],
            'total' => $validated['my_amount'],
        ]);

        // Attach user-selected tags to "Minha Parte"
        if (! empty($validated['tags'])) {
            $tagSync = [];
            foreach ($validated['tags'] as $tagId) {
                $isPrimary = ! empty($validated['primary_tag_id'])
                    ? (int) $tagId === (int) $validated['primary_tag_id']
                    : false;
                $tagSync[$tagId] = ['is_primary' => $isPrimary];
            }
            $myItem->tags()->sync($tagSync);
        }

        // Items: one per contact (tagged with Reembolso)
        foreach ($validated['contacts'] as $contactData) {
            $contact = Contact::findOrFail($contactData['id']);

            $contactItem = FinancialTransactionItem::create([
                'financial_transaction_id' => $transaction->id,
                'description' => $contact->name,
                'quantity' => 1,
                'unit_price' => $contactData['amount'],
                'total' => $contactData['amount'],
            ]);

            $contactItem->tags()->attach(FinancialTag::REEMBOLSO_ID, ['is_primary' => true]);
        }

        // Tag the transaction itself with all selected tags + Reembolso (none primary)
        $transactionTags = [];
        if (! empty($validated['tags'])) {
            foreach ($validated['tags'] as $tagId) {
                $transactionTags[$tagId] = ['is_primary' => false];
            }
        }
        $transactionTags[FinancialTag::REEMBOLSO_ID] = ['is_primary' => false];

        $transaction->tags()->sync($transactionTags);

        return $transaction;
    }

    /**
     * @param  array{
     *     total_amount: float|int|string,
     *     my_amount: float|int|string,
     *     mode: string,
     *     contacts: array<int, array{id: int, amount: float|int|string}>
     * }  $validated
     */
    private function assertValidDistribution(array $validated): void
    {
        if ($validated['contacts'] === []) {
            throw new InvalidArgumentException('A divisão deve possuir pelo menos um contato.');
        }

        $contactIds = array_column($validated['contacts'], 'id');

        if (count($contactIds) !== count(array_unique($contactIds))) {
            throw new InvalidArgumentException('Os contatos da divisão devem ser únicos.');
        }

        if (! in_array($validated['mode'], ['equal', 'exact'], true)) {
            throw new InvalidArgumentException('O modo da divisão é inválido.');
        }

        $totalCents = $this->amountToCents($validated['total_amount']);
        $myAmountCents = $this->amountToCents($validated['my_amount']);
        $contactAmounts = array_map(
            fn (array $contact): int => $this->amountToCents($contact['amount']),
            $validated['contacts'],
        );

        if ($totalCents <= 0 || $myAmountCents < 0 || collect($contactAmounts)->contains(fn (int $amountCents): bool => $amountCents <= 0)) {
            throw new InvalidArgumentException('Os valores da divisão devem ser positivos.');
        }

        if ($myAmountCents + array_sum($contactAmounts) !== $totalCents) {
            throw new InvalidArgumentException('A soma das partes deve ser igual ao valor total.');
        }

        if ($validated['mode'] !== 'equal') {
            return;
        }

        $contactShareCents = intdiv($totalCents, count($contactAmounts) + 1);
        $expectedMyAmountCents = $totalCents - ($contactShareCents * count($contactAmounts));

        if ($myAmountCents !== $expectedMyAmountCents
            || collect($contactAmounts)->contains(fn (int $amountCents): bool => $amountCents !== $contactShareCents)) {
            throw new InvalidArgumentException('Os valores não correspondem à divisão igual.');
        }
    }

    private function amountToCents(float|int|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
