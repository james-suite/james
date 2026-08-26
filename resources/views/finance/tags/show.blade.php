<x-layouts.financial>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('financial.tags.index') }}">Tags Financeiras</x-breadcrumbs.item>
            <x-breadcrumbs.item>Detalhes da Tag</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header :title="'Detalhes da Tag: ' . $financialTag->name">
        <x-back-button fallback="{{ route('financial.tags.index') }}" />

        @if(!$financialTag->is_protected)
            <x-modal.delete
                action="{{ route('financial.tags.destroy', $financialTag) }}"
                item-name="a tag"
                item-desc="{{ $financialTag->name }}"
                title="Excluir Tag"
                description="Tem certeza que deseja excluir esta tag?"
            />
            <x-button color="outline" href="{{ route('financial.tags.edit', $financialTag) }}" class="bg-white flex-1 sm:flex-initial">
                <x-heroicon-o-pencil class="size-4" />
                <span class="whitespace-nowrap">Editar</span>
            </x-button>
        @endif
    </x-page-header>

    <div class="mt-6">
        <x-card>
            <div class="flex items-center gap-4">
                <x-avatar :icon="$financialTag->icon" class="border-transparent! text-white! w-12 h-12" style="background-color: {{ $financialTag->color_hex }};" />
                
                <div>
                    <h2 class="text-xl font-bold text-neutral-900 flex items-center gap-2">
                        {{ $financialTag->name }}
                        @if($financialTag->is_protected)
                            <span class="text-xs uppercase font-bold text-yellow-700 bg-yellow-50 px-2 py-1 rounded ring-1 ring-inset ring-yellow-600/20">Protegida</span>
                        @endif
                    </h2>
                    
                    <div class="mt-1 text-sm text-neutral-500">
                        Usada em {{ $financialTag->transactions_count + $financialTag->transaction_items_count }} registros.
                        <a href="{{ route('financial.transactions.index', ['tag_id' => $financialTag->id]) }}" class="text-accent hover:underline ml-1 font-medium">
                            Ver transações
                        </a>
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    <x-finance.recent-transactions :model="$financialTag" />

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_350px] gap-6 items-start mt-8">
        <x-activity-log :model="$financialTag" class="!mt-0" />
        <x-metadata-card :model="$financialTag" class="w-full mb-0" />
    </div>
</x-layouts.financial>
