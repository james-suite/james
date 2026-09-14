<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialRecurrenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $targetType = $this->input('targetType');

        if ($targetType === 'account') {
            $this->merge(['financial_credit_card_id' => null]);
        } elseif ($targetType === 'card') {
            $this->merge(['financial_account_id' => null]);
        }

        if ($this->has('amount') && is_string($this->amount)) {
            $this->merge([
                'amount' => str_replace(',', '.', $this->amount),
            ]);
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
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'frequency' => ['required', Rule::in(['monthly', 'yearly'])],
            'targetType' => ['nullable', Rule::in(['account', 'card'])],
            'financial_account_id' => ['nullable', 'required_without:financial_credit_card_id', 'exists:financial_accounts,id'],
            'financial_credit_card_id' => ['nullable', 'required_without:financial_account_id', 'exists:financial_credit_cards,id', 'prohibits:financial_account_id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:financial_tags,id'],
            'primary_tag_id' => ['nullable', 'exists:financial_tags,id'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'financial_credit_card_id.prohibits' => 'Uma recorrência não pode estar vinculada a uma conta e um cartão ao mesmo tempo.',
            'financial_account_id.required_without' => 'Você deve selecionar uma conta corrente ou um cartão de crédito.',
            'financial_credit_card_id.required_without' => 'Você deve selecionar uma conta corrente ou um cartão de crédito.',
        ];
    }
}
