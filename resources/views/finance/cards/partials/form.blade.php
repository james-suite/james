<div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 pb-6">
    <div class="flex flex-col gap-4 sm:gap-6">
        <x-card>
            <div class="flex flex-col gap-4">
                <div>
                    <x-form-input name="name" label="Nome do Cartão" :value="old('name', $card->name ?? '')" placeholder="Ex: Nubank, Itaú" />
                </div>
                <div>
                    <x-form-select name="financial_account_id" label="Conta para Pagamento">
                        <option value="" disabled {{ !isset($card) ? 'selected' : '' }}>Selecione uma conta...</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ old('financial_account_id', $card->financial_account_id ?? '') == $account->id ? 'selected' : '' }}>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </x-form-select>
                </div>
                <div>
                    <x-form-input name="credit_limit" :currency="true" label="Limite (Opcional)" :value="old('credit_limit', isset($card) && $card->credit_limit ? (float) $card->credit_limit : '')" placeholder="Ex: 5000.00" />
                </div>
            </div>
        </x-card>
    </div>

    <div class="flex flex-col gap-4 sm:gap-6">
        <x-card>
            <h3 class="font-medium text-neutral-900 mb-4">Datas e Vencimento</h3>
            <div class="flex flex-col gap-4">
                <div>
                    <x-form-input name="closing_day" type="number" min="1" max="31" label="Dia de Fechamento" :value="old('closing_day', $card->closing_day ?? '')" placeholder="Ex: 25" />
                </div>
                <div>
                    <x-form-input name="due_day" type="number" min="1" max="31" label="Dia de Vencimento" :value="old('due_day', $card->due_day ?? '')" placeholder="Ex: 5" />
                </div>
            </div>
        </x-card>
    </div>
</div>
