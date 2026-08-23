<x-layouts.financial>
    <x-page-header title="Transações" :action="route('financial.transactions.create')" actionText="Nova Transação" icon="heroicon-o-plus">
        <x-modal.trigger name="transfer-modal">
            <x-button type="button" color="outline" class="bg-white">
                <x-heroicon-o-arrows-right-left class="size-4!" />
                <span class="hidden sm:inline">Transferência</span>
            </x-button>
        </x-modal.trigger>

        <x-modal
            name="transfer-modal"
            title="Nova Transferência"
            size="lg"
            confirmVariant="none">
            <form
                action="{{ route('financial.transactions.transfer.store') }}"
                method="POST"
                class="m-0"
                x-data="{ amount: '', feeAmount: '' }">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto_1fr] gap-x-6 gap-y-4">
                    {{-- Coluna Esquerda: Dados da Transferência --}}
                    <div class="flex flex-col gap-4">
                        <x-form-input
                            label="Descrição"
                            name="description"
                            value="{{ old('description', 'Transferência entre contas') }}"
                            placeholder="Ex: Transferência poupança"
                        />
                        <x-form-input label="Valor (R$)" name="amount" x-model="amount" :currency="true" placeholder="0,00" />
                        <x-form-input label="Data" name="date" type="date" value="{{ old('date', \Carbon\Carbon::today()->format('Y-m-d')) }}" />

                        <div>
                            <p class="text-xs font-semibold text-neutral-400 uppercase tracking-widest mb-2">Taxa / IOF / Imposto <span class="font-normal normal-case">(opcional)</span></p>
                            <x-form-input label="Valor da Taxa (R$)" name="fee_amount" x-model="feeAmount" :currency="true" placeholder="0,00" value="{{ old('fee_amount') }}" />
                        </div>
                    </div>

                    {{-- Divisor --}}
                    <div class="hidden sm:block w-px bg-neutral-200"></div>

                    {{-- Coluna Direita: Contas --}}
                    <div class="flex flex-col gap-3">
                        <p class="text-xs font-semibold text-neutral-400 uppercase tracking-widest">Contas</p>

                        <x-form-select name="from_account_id" label="Conta de Origem">
                            <option value="">Selecione a conta de origem...</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ old('from_account_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </x-form-select>

                        <div class="flex items-center gap-3 py-1">
                            <div class="flex-1 h-px bg-neutral-200"></div>
                            <div class="shrink-0 flex items-center justify-center size-7 rounded-full bg-neutral-100 text-neutral-500">
                                <x-heroicon-o-arrow-down class="size-3.5" />
                            </div>
                            <div class="flex-1 h-px bg-neutral-200"></div>
                        </div>

                        <x-form-select name="to_account_id" label="Conta de Destino">
                            <option value="">Selecione a conta de destino...</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ old('to_account_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </x-form-select>
                    </div>
                </div>

                <div class="flex justify-end items-center gap-3 mt-6 pt-4 border-t border-neutral-100">
                    <x-button type="button" color="outline" @click="$dispatch('modal-close', 'transfer-modal')">
                        Cancelar
                    </x-button>
                    <x-button type="submit">
                        <x-heroicon-o-arrows-right-left class="size-4" />
                        Salvar Transferência
                    </x-button>
                </div>
            </form>
        </x-modal>

        @if($hasTrashed)
            <x-button color="outline" href="{{ route('financial.transactions.trashed') }}" class="bg-white">
                <x-heroicon-o-trash class="size-4" />
                <span class="hidden sm:inline">Lixeira</span>
            </x-button>
        @endif
    </x-page-header>

    <x-filter-bar 
        action="{{ route('financial.transactions.index') }}" 
        searchPlaceholder="Buscar por descrição..." 
        :filters="['search', 'account_id', 'tag_id', 'type', 'status', 'date']">
        
        <div class="flex flex-col sm:flex-row w-full sm:w-auto divide-y sm:divide-y-0 sm:divide-x divide-neutral-200">
            <x-filter-bar.select name="account_id">
                <option value="">Todas as Contas</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}" @selected(request('account_id') == $account->id)>{{ $account->name }}</option>
                @endforeach
            </x-filter-bar.select>
            
            <x-filter-bar.select name="tag_id">
                <option value="">Todas as Tags</option>
                @foreach($tags as $tag)
                    <option value="{{ $tag->id }}" @selected(request('tag_id') == $tag->id)>{{ $tag->name }}</option>
                @endforeach
            </x-filter-bar.select>
            
            <x-filter-bar.select name="type">
                <option value="">Todos os Tipos</option>
                <option value="income" @selected(request('type') == 'income')>Receita</option>
                <option value="expense" @selected(request('type') == 'expense')>Despesa</option>
            </x-filter-bar.select>

            <x-filter-bar.select name="status">
                <option value="">Todos os Status</option>
                <option value="posted" @selected(request('status') === 'posted')>Efetivadas</option>
                <option value="pending" @selected(request('status') === 'pending')>Pendentes</option>
                <option value="draft" @selected(request('status') === 'draft')>Rascunhos</option>
            </x-filter-bar.select>

            <x-filter-bar.date name="date" value="{{ request('date') }}" title="Data específica" />
        </div>
    </x-filter-bar>

    <x-finance.transaction-table :transactions="$transactions" class="lg:mb-8" />
    
    <div class="mt-6 pb-6">
        {{ $transactions->links() }}
    </div>
</x-layouts.financial>
