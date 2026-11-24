<x-layouts.financial>
    <x-page-header :title="$showArchived ? 'Cartões Arquivados' : 'Cartões de Crédito'" :action="route('financial.cards.create')" actionText="Novo Cartão" icon="heroicon-o-plus">
        @if ($showArchived)
            <x-button color="outline" href="{{ route('financial.cards.index') }}" class="bg-white">
                <x-heroicon-o-arrow-uturn-left class="size-5!" />
                Ver Ativos
            </x-button>
        @elseif ($hasArchived)
            <x-button color="outline" href="{{ route('financial.cards.index', ['archived' => 1]) }}" class="bg-white">
                <x-heroicon-o-archive-box class="size-5!" />
                Arquivados
            </x-button>
        @endif
        @if ($hasTrashed)
            <x-button color="outline" href="{{ route('financial.cards.trashed') }}" class="bg-white">
                <x-heroicon-o-trash class="size-5!" />
                Lixeira
            </x-button>
        @endif
    </x-page-header>

    <x-filter-bar 
        action="{{ route('financial.cards.index') }}" 
        searchPlaceholder="Buscar por nome do cartão..." 
        :filters="['search']">
    </x-filter-bar>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        @forelse($cards as $card)
            <x-finance.credit-card :card="$card" />
        @empty
            <div class="col-span-full bg-white rounded-xl border border-neutral-200">
                <x-empty-state 
                    icon="heroicon-o-credit-card" 
                    message="Nenhum cartão encontrado." 
                />
            </div>
        @endforelse
    </div>

    <div class="mt-6 pb-6">
        {{ $cards->links() }}
    </div>
</x-layouts.financial>
