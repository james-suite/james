<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSettlementGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('create_transaction')) {
            $targetType = $this->input('targetType');

            if ($targetType === 'account') {
                $this->merge(['financial_credit_card_id' => null]);
            } elseif ($targetType === 'card') {
                $this->merge(['financial_account_id' => null]);
            }
        }

        // Convert comma-formatted amounts to dot notation
        if ($this->has('total_amount') && is_string($this->total_amount)) {
            $this->merge(['total_amount' => str_replace(',', '.', $this->total_amount)]);
        }

        if ($this->has('my_amount') && is_string($this->my_amount)) {
            $this->merge(['my_amount' => str_replace(',', '.', $this->my_amount)]);
        }

        if ($this->has('contacts') && is_array($this->contacts)) {
            $contacts = $this->contacts;
            foreach ($contacts as &$contact) {
                if (isset($contact['amount']) && is_string($contact['amount'])) {
                    $contact['amount'] = str_replace(',', '.', $contact['amount']);
                }
            }
            $this->merge(['contacts' => $contacts]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'my_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'date' => ['required', 'date'],
            'mode' => ['required', 'in:equal,exact'],
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.id' => ['required', 'integer', 'distinct:strict', 'exists:contacts,id'],
            'contacts.*.amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'create_transaction' => ['boolean'],
            'targetType' => ['exclude_if:create_transaction,0', 'exclude_if:create_transaction,false', 'required', 'in:account,card'],
            'financial_account_id' => ['exclude_if:create_transaction,0', 'exclude_if:create_transaction,false', 'nullable', 'required_if:targetType,account', 'exists:financial_accounts,id'],
            'financial_credit_card_id' => ['exclude_if:create_transaction,0', 'exclude_if:create_transaction,false', 'nullable', 'required_if:targetType,card', 'exists:financial_credit_cards,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:financial_tags,id'],
            'primary_tag_id' => ['nullable', 'exists:financial_tags,id'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpeg,png,jpg,pdf', 'max:10240'], // 10MB max per file
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $validated = $validator->validated();
                $totalCents = $this->amountToCents($validated['total_amount']);
                $myAmountCents = $this->amountToCents($validated['my_amount']);
                $contactAmounts = array_map(
                    fn (array $contact): int => $this->amountToCents($contact['amount']),
                    $validated['contacts'],
                );

                if ($myAmountCents + array_sum($contactAmounts) !== $totalCents) {
                    $validator->errors()->add('total_amount', 'A soma das partes deve ser igual ao valor total.');

                    return;
                }

                if ($validated['mode'] !== 'equal') {
                    return;
                }

                $contactShareCents = intdiv($totalCents, count($contactAmounts) + 1);
                $expectedMyAmountCents = $totalCents - ($contactShareCents * count($contactAmounts));

                if ($myAmountCents !== $expectedMyAmountCents) {
                    $validator->errors()->add('my_amount', 'A sua parte não corresponde à divisão igual.');
                }

                foreach ($contactAmounts as $index => $amountCents) {
                    if ($amountCents !== $contactShareCents) {
                        $validator->errors()->add("contacts.$index.amount", 'O valor não corresponde à divisão igual.');
                    }
                }
            },
        ];
    }

    private function amountToCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public function messages(): array
    {
        return [
            'description.required' => 'A descrição é obrigatória.',
            'total_amount.required' => 'O valor total é obrigatório.',
            'total_amount.min' => 'O valor total deve ser maior que zero.',
            'date.required' => 'A data é obrigatória.',
            'contacts.required' => 'Selecione ao menos um contato.',
            'contacts.min' => 'Selecione ao menos um contato.',
            'contacts.*.amount.required' => 'O valor de cada participante é obrigatório.',
            'contacts.*.amount.min' => 'O valor de cada participante deve ser maior que zero.',
            'financial_account_id.required_if' => 'Selecione uma conta bancária.',
            'financial_credit_card_id.required_if' => 'Selecione um cartão de crédito.',
            'attachments.*.mimes' => 'Os anexos devem ser imagens (JPEG, PNG) ou PDFs.',
            'attachments.*.max' => 'Cada anexo não pode ultrapassar 10MB.',
        ];
    }
}
