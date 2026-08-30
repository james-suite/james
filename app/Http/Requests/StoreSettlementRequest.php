<?php

namespace App\Http\Requests;

use App\Enums\SettlementType;
use App\Models\FinancialTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSettlementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount') && is_string($this->amount)) {
            $this->merge(['amount' => str_replace(',', '.', $this->amount)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(SettlementType::class)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'create_transaction' => ['boolean'],
            'targetType' => ['exclude_if:create_transaction,0', 'exclude_if:create_transaction,false', 'required', 'in:account,card'],
            'financial_account_id' => ['exclude_if:create_transaction,0', 'exclude_if:create_transaction,false', 'nullable', 'required_if:targetType,account', 'exists:financial_accounts,id'],
            'financial_credit_card_id' => ['exclude_if:create_transaction,0', 'exclude_if:create_transaction,false', 'nullable', 'required_if:targetType,card', 'exists:financial_credit_cards,id'],
            'tags' => ['exclude_unless:type,'.SettlementType::IPaid->value, 'exclude_if:create_transaction,0', 'exclude_if:create_transaction,false', 'nullable', 'array'],
            'tags.*' => ['exclude_unless:type,'.SettlementType::IPaid->value, 'exclude_if:create_transaction,0', 'exclude_if:create_transaction,false', 'exists:financial_tags,id', Rule::notIn([FinancialTag::REEMBOLSO_ID])],
            'primary_tag_id' => ['exclude_unless:type,'.SettlementType::IPaid->value, 'exclude_if:create_transaction,0', 'exclude_if:create_transaction,false', 'nullable', 'exists:financial_tags,id', Rule::notIn([FinancialTag::REEMBOLSO_ID])],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpeg,png,jpg,pdf', 'max:10240'], // 10MB max per file
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('type') !== SettlementType::IPaid->value || ! $this->boolean('create_transaction')) {
                return;
            }

            $tags = $this->input('tags', []);
            $primaryTagId = $this->input('primary_tag_id');

            if ($tags === [] || $tags === null) {
                if ($primaryTagId !== null) {
                    $validator->errors()->add('primary_tag_id', 'A tag principal deve estar entre as tags selecionadas.');
                }

                return;
            }

            if ($primaryTagId === null || ! collect($tags)->contains(fn ($tagId): bool => (int) $tagId === (int) $primaryTagId)) {
                $validator->errors()->add('primary_tag_id', 'Selecione uma tag principal entre as tags escolhidas.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'A descrição é obrigatória.',
            'amount.required' => 'O valor é obrigatório.',
            'amount.min' => 'O valor deve ser maior que zero.',
            'date.required' => 'A data é obrigatória.',
            'financial_account_id.required_if' => 'Selecione uma conta bancária para a transação financeira.',
            'financial_credit_card_id.required_if' => 'Selecione um cartão de crédito para a transação financeira.',
            'attachments.*.mimes' => 'Os anexos devem ser imagens (JPEG, PNG) ou PDFs.',
            'attachments.*.max' => 'Cada anexo não pode ultrapassar 10MB.',
        ];
    }
}
