<x-layouts.financial>
    <x-page-header :title="$showArchived ? 'Contas Arquivadas' : 'Contas Financeiras'" :action="route('financial.accounts.create')" actionText="Nova Conta" icon="heroicon-o-plus">
        @if ($showArchived)
            <x-button color="outline" href="{{ route('financial.accounts.index') }}" class="bg-white">
                <x-heroicon-o-arrow-uturn-left class="size-5!" />
                Ver Ativas
            </x-button>
        @elseif ($hasArchived)
            <x-button color="outline" href="{{ route('financial.accounts.index', ['archived' => 1]) }}" class="bg-white">
                <x-heroicon-o-archive-box class="size-5!" />
                Arquivadas
            </x-button>
        @endif
        @if ($hasTrashed)
            <x-button color="outline" href="{{ route('financial.accounts.trashed') }}" class="bg-white">
                <x-heroicon-o-trash class="size-5!" />
                Lixeira
            </x-button>
        @endif
    </x-page-header>

    <x-filter-bar 
        action="{{ route('financial.accounts.index') }}" 
        searchPlaceholder="Buscar por nome da conta..." 
        :filters="['search']">
    </x-filter-bar>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        @forelse($accounts as $account)
            <x-card href="{{ route('financial.accounts.show', $account) }}" size="sm" class="t-learn flex items-center gap-4 group">
                <x-avatar :icon="$account->type->icon()" size="lg" />
                
                <div class="overflow-hidden flex-1 flex flex-col justify-center">
                    <div class="flex justify-between items-center gap-2">
                        <h3 class="font-semibold text-neutral-900 truncate">{{ $account->name }}</h3>
                        <x-badge color="accent" size="sm" class="shrink-0 hidden sm:inline-flex">
                            {{ $account->type->label() }}
                        </x-badge>
                    </div>
                    
                    <div class="flex items-center justify-between mt-1">
                        <div class="flex items-center gap-1.5 {{ $account->balance < 0 ? 'text-red-600' : ($account->balance > 0 ? 'text-green-600' : 'text-neutral-700') }}">
                            @if($account->balance < 0)
                                <x-heroicon-o-arrow-trending-down class="size-4" />
                            @elseif($account->balance > 0)
                                <x-heroicon-o-arrow-trending-up class="size-4" />
                            @endif
                            <span class="font-bold text-base leading-none tracking-tight">
                                {{ formatCurrency($account->balance) }}
                            </span>
                        </div>
                        <div class="text-neutral-300 group-hover:text-primary-600 transition-colors">
                            <x-heroicon-o-chevron-right class="t-learn-chevron size-5" />
                        </div>
                    </div>
                </div>
            </x-card>
        @empty
            <div class="col-span-full bg-white rounded-xl border border-neutral-200">
                <x-empty-state 
                    icon="heroicon-o-wallet" 
                    message="Nenhuma conta encontrada." 
                />
            </div>
        @endforelse
    </div>

    <div class="mt-6 pb-6">
        {{ $accounts->links() }}
    </div>
</x-layouts.financial>
