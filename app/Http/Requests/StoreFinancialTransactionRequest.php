<?php

namespace App\Http\Requests;

use App\Enums\TransactionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $targetType = $this->input('targetType');

        if ($targetType === 'account') {
            $this->merge(['financial_credit_card_id' => null]);
        } elseif ($targetType === 'card') {
            $this->merge(['financial_account_id' => null]);
        }

        if ($this->has('amount') && is_string($this->amount)) {
            $this->merge(['amount' => str_replace(',', '.', $this->amount)]);
        }

        if ($this->has('items') && is_array($this->items)) {
            $items = $this->items;
            foreach ($items as $key => $item) {
                if (isset($item['unit_price']) && is_string($item['unit_price'])) {
                    $items[$key]['unit_price'] = str_replace(',', '.', $item['unit_price']);
                }
                if (isset($item['quantity']) && is_string($item['quantity'])) {
                    $items[$key]['quantity'] = str_replace(',', '.', $item['quantity']);
                }
            }
            $this->merge(['items' => $items]);
        }
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:single,installment'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'type' => ['required', 'string', 'in:income,expense'],
            'targetType' => ['required', 'string', 'in:account,card'],
            'financial_account_id' => ['nullable', 'required_if:targetType,account', 'exists:financial_accounts,id'],
            'financial_credit_card_id' => ['nullable', 'required_if:targetType,card', 'exists:financial_credit_cards,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:financial_tags,id'],
            'primary_tag_id' => ['nullable', 'exists:financial_tags,id'],
            'status' => ['nullable', new Enum(TransactionStatus::class)],
            'installments' => ['required_if:mode,installment', 'integer', 'min:2'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'not_regex:/^-?0+(?:\.0+)?$/'],
            'items.*.tags' => ['nullable', 'array'],
            'items.*.tags.*' => ['exists:financial_tags,id'],
            'items.*.primary_tag_id' => ['nullable', 'exists:financial_tags,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->input('mode') === 'installment'
                    && $this->input('targetType') === 'card'
                    && $this->input('type') === 'income'
                ) {
                    $validator->errors()->add('type', 'Parcelamentos no cartão só podem ser despesas.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'A descrição da transação é obrigatória.',
            'amount.required' => 'O valor da transação é obrigatório.',
            'amount.min' => 'O valor da transação deve ser maior que zero.',
            'financial_account_id.required_if' => 'Selecione uma conta bancária para esta transação.',
            'financial_credit_card_id.required_if' => 'Selecione um cartão de crédito para esta transação.',
            'installments.required_if' => 'O número de parcelas é obrigatório para transações parceladas.',
            'items.*.description.required' => 'Preencha a descrição de todos os itens adicionados.',
            'items.*.quantity.required' => 'A quantidade é obrigatória nos itens.',
            'items.*.quantity.gt' => 'A quantidade do item deve ser maior que zero.',
            'items.*.unit_price.required' => 'O valor é obrigatório nos itens.',
            'items.*.unit_price.not_regex' => 'O valor do item não pode ser zero.',
        ];
    }
}
