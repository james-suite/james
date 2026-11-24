<x-layouts.financial>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('financial.accounts.index') }}">Contas Financeiras</x-breadcrumbs.item>
            <x-breadcrumbs.item>{{ $account->name }}</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header title="Detalhes da Conta">
        <x-back-button fallback="{{ route('financial.accounts.index') }}" />

        <x-modal.trigger name="adjust-balance-{{ $account->id }}">
            <x-button type="button" color="outline" class="bg-white">
                <x-heroicon-o-adjustments-horizontal class="size-4" />
                <span class="hidden sm:inline">Ajustar Saldo</span>
            </x-button>
        </x-modal.trigger>

        <x-modal
            name="adjust-balance-{{ $account->id }}"
            title="Ajustar Saldo"
            message="Informe o saldo atual real da conta. O sistema calculará a diferença e criará uma transação de ajuste automaticamente."
            confirmVariant="none">
            <form action="{{ route('financial.accounts.adjust-balance', $account) }}" method="POST" class="m-0">
                @csrf
                <div class="mb-6">
                    <x-form-input
                        label="Saldo Real (R$)"
                        name="real_balance"
                        :currency="true"
                        placeholder="0,00"
                        autofocus
                    />
                </div>
                <div class="flex justify-end items-center gap-3 pt-4 border-t border-neutral-100">
                    <x-button type="button" color="outline" @click="$dispatch('modal-close', 'adjust-balance-{{ $account->id }}')">
                        Cancelar
                    </x-button>
                    <x-button type="submit" class="w-full sm:w-auto">
                        <x-heroicon-o-adjustments-horizontal class="size-4" />
                        Aplicar Ajuste
                    </x-button>
                </div>
            </form>
        </x-modal>

        @if($account->isArchived())
            <form action="{{ route('financial.accounts.unarchive', $account) }}" method="POST" class="m-0">
                @csrf
                @method('PATCH')
                <x-button type="submit" color="outline" class="bg-white">
                    <x-heroicon-o-archive-box-arrow-down class="size-4" />
                    <span class="hidden sm:inline">Reativar</span>
                </x-button>
            </form>
        @else
            <x-modal.trigger name="archive-account-{{ $account->id }}">
                <x-button type="button" color="outline" class="bg-white">
                    <x-heroicon-o-archive-box class="size-4" />
                    <span class="hidden sm:inline">Arquivar</span>
                </x-button>
            </x-modal.trigger>

            <x-modal
                name="archive-account-{{ $account->id }}"
                title="Arquivar Conta"
                confirmVariant="warning">
                <x-slot:content>
                    <p class="mb-3">Ao arquivar <strong>{{ $account->name }}</strong>, ela deixará de aparecer nos seletores de novas transações, mantendo todo o histórico e saldo preservados.</p>
                    @if($account->balance != 0 || $activeCardsCount > 0 || $activeRecurrencesCount > 0)
                        <div class="rounded-md bg-amber-50 p-3 border border-amber-200 text-left text-amber-800 space-y-1">
                            <p class="font-semibold">Atenção antes de arquivar:</p>
                            @if($account->balance != 0)
                                <p>• Saldo atual: <strong>{{ formatCurrency($account->balance) }}</strong></p>
                            @endif
                            @if($activeCardsCount > 0)
                                <p>• {{ $activeCardsCount }} cartão(ões) de crédito ativo(s) vinculado(s)</p>
                            @endif
                            @if($activeRecurrencesCount > 0)
                                <p>• {{ $activeRecurrencesCount }} recorrência(s) vinculada(s)</p>
                            @endif
                        </div>
                    @endif
                </x-slot:content>

                <form action="{{ route('financial.accounts.archive', $account) }}" method="POST" class="m-0">
                    @csrf
                    @method('PATCH')
                    <x-button type="submit" class="w-full sm:w-auto">
                        Arquivar
                    </x-button>
                </form>
            </x-modal>
        @endif

        <x-modal.delete
            action="{{ route('financial.accounts.destroy', $account) }}"
            item-name="a conta"
            item-desc="{{ $account->name }}"
            title="Excluir Conta"
        />

        <x-button color="outline" href="{{ route('financial.accounts.edit', $account) }}" class="bg-white flex-1 sm:flex-initial">
            <x-heroicon-o-pencil-square class="size-4" />
            <span class="whitespace-nowrap">Editar</span>
        </x-button>
    </x-page-header>

    <x-card class="mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 sm:gap-6">
            <div class="flex items-center gap-4 sm:gap-6">
                <x-avatar :icon="$account->type->icon()" size="xl" />
                
                <div class="flex flex-col gap-2">
                    <h2 class="text-2xl font-bold text-neutral-900">{{ $account->name }}</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge color="accent" size="sm">
                            {{ $account->type->label() }}
                        </x-badge>
                        @if($account->isArchived())
                            <x-badge color="neutral" size="sm">
                                Arquivada
                            </x-badge>
                        @endif
                    </div>
                </div>
            </div>

            @if(!empty($account->pix_keys))
                <div class="md:text-right flex flex-col md:items-end gap-2 border-t md:border-t-0 md:border-l border-neutral-100 pt-4 md:pt-0 md:pl-6 overflow-x-auto">
                    <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest">Chaves Pix</h3>
                    <div class="flex gap-4 sm:gap-6 md:justify-end">
                        @foreach(array_chunk($account->pix_keys, 3) as $chunk)
                            <div class="flex flex-col gap-1.5 min-w-max">
                                @foreach($chunk as $pixKey)
                                    <div class="flex items-center md:justify-end gap-2 text-sm">
                                        <span class="font-medium text-neutral-500">{{ $pixKey['label'] }}:</span>
                                        <span class="font-semibold text-neutral-900">{{ $pixKey['value'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-card>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 sm:gap-4 mb-6">
        <x-finance.kpi-card 
            title="Total de Receitas" 
            :value="formatCurrency($globalIncome)" 
            icon="heroicon-o-arrow-trending-up" 
            color="green" 
            :href="route('financial.transactions.index', ['account_id' => $account->id, 'type' => 'income'])" 
        />

        <x-finance.kpi-card 
            title="Total de Despesas" 
            :value="formatCurrency($globalExpense)" 
            icon="heroicon-o-arrow-trending-down" 
            color="red" 
            :href="route('financial.transactions.index', ['account_id' => $account->id, 'type' => 'expense'])" 
        />

        <x-finance.kpi-card 
            title="Saldo Atual" 
            :value="($account->balance > 0 ? '+' : ($account->balance < 0 ? '-' : '')) . formatCurrency(abs($account->balance))" 
            icon="heroicon-o-scale" 
            :color="$account->balance > 0 ? 'green' : ($account->balance < 0 ? 'red' : 'neutral')" 
            :href="route('financial.transactions.index', ['account_id' => $account->id])" 
            class="col-span-2 md:col-span-1"
            :hide-icon-on-mobile="false"
        >
            Saldo contábil no sistema
        </x-finance.kpi-card>
    </div>

    @if($creditCards->isNotEmpty())
        <div class="mb-6">
            <h3 class="text-lg font-bold text-neutral-900 mb-4">Cartões de Crédito</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($creditCards as $card)
                    <x-finance.credit-card :card="$card" />
                @endforeach
            </div>
        </div>
    @endif

    <x-finance.recent-transactions :model="$account" limit="10" />

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_350px] gap-6 items-start mt-8">
        <x-activity-log :model="$account" class="!mt-0" />
        <x-metadata-card :model="$account" class="w-full mb-0" />
    </div>
</x-layouts.financial>
