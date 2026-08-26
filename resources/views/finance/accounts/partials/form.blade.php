<div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 pb-6" x-data="{ type: '{{ old('type', isset($account) ? $account->type->value : '') }}' }">
    <!-- Coluna da Esquerda: Informações Básicas -->
    <div class="flex flex-col gap-4 sm:gap-6">
        <x-card>
            <div class="flex flex-col gap-4">
                @isset($account)
                    <div class="flex items-center gap-4 sm:gap-6 mb-2">
                        <x-avatar :icon="$account->type->icon()" size="2xl" />
                    </div>
                @endisset
                
                <div>
                    <x-form-input name="name" label="Nome da Conta" :value="old('name', $account->name ?? '')" placeholder="Ex: Dinheiro Físico" />
                </div>
                <div>
                    <x-form-select name="type" label="Tipo de Conta" x-model="type" @change="$dispatch('account-type-changed', type)">
                        <option value="" disabled>Selecione um tipo...</option>
                        @foreach($types as $enum)
                            <option value="{{ $enum->value }}">
                                {{ $enum->label() }}
                            </option>
                        @endforeach
                    </x-form-select>
                </div>
                
                @if(!isset($account))
                <div>
                    <x-form-input label="Saldo Inicial (R$)" name="initial_balance" :currency="true" placeholder="0,00" :value="old('initial_balance')" help="Opcional. Se preenchido, criará uma transação na data de hoje ajustando o saldo inicial." />
                </div>
                @endif
            </div>
        </x-card>
    </div>

    <!-- Coluna da Direita: Chaves Pix -->
    <div class="flex flex-col gap-4 sm:gap-6 transition-all duration-300" :class="type !== 'checking' ? 'opacity-40 grayscale pointer-events-none' : ''">
        <x-form-key-value-repeater 
            name="pix_keys" 
            title="Chaves Pix" 
            :items="$pixKeys ?? []" 
            value-placeholder="Chave Pix" 
            empty-message="Nenhuma chave Pix adicionada." 
            @account-type-changed.window="if ($event.detail !== 'checking') items = []"
        />
    </div>
</div>
