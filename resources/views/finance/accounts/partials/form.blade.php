<div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 pb-6" x-data="{
    type: {{ Js::from(old('type', isset($account) ? $account->type->value : '')) }},
    name: {{ Js::from(old('name', $account->name ?? '')) }},
    banks: [],
    banksLoaded: false,
    banksLoading: false,
    banksOpen: false,
    async loadBanks() {
        if (this.banksLoaded || this.banksLoading) return;
        this.banksLoading = true;
        try {
            const response = await fetch('{{ route('financial.accounts.banks') }}', { headers: { 'Accept': 'application/json' } });
            if (response.ok) {
                this.banks = await response.json();
            }
        } catch (e) {
            this.banks = [];
        } finally {
            this.banksLoaded = true;
            this.banksLoading = false;
        }
    },
    get filteredBanks() {
        const term = (this.name || '').trim().toLowerCase();
        if (term.length < 2 || this.type === 'wallet') return [];
        return this.banks
            .filter(b => b.name.toLowerCase().includes(term) || b.full_name.toLowerCase().includes(term) || (b.code && b.code.includes(term)))
            .slice(0, 8);
    },
    selectBank(bank) {
        this.name = bank.name;
        this.banksOpen = false;
    }
}">
    <!-- Coluna da Esquerda: Informações Básicas -->
    <div class="flex flex-col gap-4 sm:gap-6">
        <x-card>
            <div class="flex flex-col gap-4">
                @isset($account)
                    <div class="flex items-center gap-4 sm:gap-6 mb-2">
                        <x-avatar :icon="$account->type->icon()" size="2xl" />
                    </div>
                @endisset
                
                <div class="relative" @click.outside="banksOpen = false">
                    <x-form-input
                        name="name"
                        label="Nome da Conta"
                        :value="old('name', $account->name ?? '')"
                        placeholder="Ex: Nubank, Itaú ou Dinheiro Físico"
                        autocomplete="off"
                        x-model="name"
                        @focus="loadBanks(); banksOpen = true"
                        @input="loadBanks(); banksOpen = true"
                        @keydown.escape="banksOpen = false"
                    />
                    <ul
                        class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-neutral-200 bg-white p-1"
                        x-show="banksOpen && filteredBanks.length > 0"
                        x-cloak
                    >
                        <template x-for="bank in filteredBanks" :key="bank.name">
                            <li>
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm text-neutral-700 hover:bg-neutral-100 hover:text-neutral-900 cursor-pointer"
                                    @click="selectBank(bank)"
                                >
                                    <span class="truncate font-medium" x-text="bank.name"></span>
                                    <span class="shrink-0 text-xs text-neutral-400" x-show="bank.code" x-text="bank.code"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
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
