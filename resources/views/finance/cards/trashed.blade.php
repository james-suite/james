<x-layouts.financial>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('financial.cards.index') }}">Cartões de Crédito</x-breadcrumbs.item>
            <x-breadcrumbs.item>Lixeira</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header 
        title="Lixeira" 
        description="Cartões de crédito excluídos. Eles podem ser restaurados ou excluídos permanentemente." 
    >
        <x-back-button fallback="{{ route('financial.cards.index') }}" class="w-full sm:w-auto" />
    </x-page-header>

    <x-filter-bar 
        action="{{ route('financial.cards.trashed') }}" 
        searchPlaceholder="Buscar na lixeira..." 
        :filters="['search']">
    </x-filter-bar>

    <x-table class="lg:mb-8"
         x-data="{
             selectedCardId: null,
             selectedCardName: '',
             openRestore(id, name) {
                 this.selectedCardId = id;
                 this.selectedCardName = name;
                 $dispatch('modal-open', 'restore-card');
             },
             openForceDelete(id, name) {
                 this.selectedCardId = id;
                 this.selectedCardName = name;
                 $dispatch('modal-open', 'force-delete-card');
             }
         }">
        @if($cards->isNotEmpty())
            {{-- Header - Desktop --}}
            <x-table.header class="hidden sm:grid sm:grid-cols-[2fr_1fr_1.5fr]">
                <x-table.column>Cartão</x-table.column>
                <x-table.column>Data da Exclusão</x-table.column>
                <x-table.column align="right">Ações</x-table.column>
            </x-table.header>
        @endif

        <x-table.body>
            @forelse($cards as $card)
                <x-table.row class="hidden sm:grid sm:grid-cols-[2fr_1fr_1.5fr]">
                    <x-table.cell>
                        <div class="flex items-center gap-3 w-full">
                            <div class="shrink-0 flex items-center justify-center size-10 rounded-full bg-neutral-100 text-neutral-400 grayscale opacity-80">
                                <x-heroicon-o-credit-card class="size-5" />
                            </div>
                            <div class="overflow-hidden">
                                <div class="font-medium text-neutral-900 truncate">{{ $card->name }}</div>
                                <div class="mt-1">
                                    <span class="text-sm text-neutral-500">{{ $card->financialAccount->name }}</span>
                                </div>
                            </div>
                        </div>
                    </x-table.cell>

                    <x-table.cell>
                        <span class="text-sm text-neutral-500">
                            {{ $card->deleted_at->formatDateTime() }}
                        </span>
                    </x-table.cell>

                    <x-table.cell align="right">
                        <div class="flex justify-end gap-2 w-full">
                            <x-button type="button" color="outline" class="bg-white hover:bg-neutral-50 text-neutral-600 border-neutral-300" @click="openRestore({{ $card->id }}, {{ Js::from($card->name) }})">
                                <x-heroicon-o-arrow-uturn-left class="size-4" />
                                Restaurar
                            </x-button>

                            <x-button type="button" color="outline" class="bg-white hover:bg-red-50 text-red-600 border-red-200 interaction-delete" @click="openForceDelete({{ $card->id }}, {{ Js::from($card->name) }})">
                                <x-heroicon-o-trash class="size-4" />
                                Excluir
                            </x-button>
                        </div>
                    </x-table.cell>

                    <x-slot name="mobile">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0 flex items-center gap-3">
                                <div class="shrink-0 flex items-center justify-center size-10 rounded-full bg-neutral-100 text-neutral-400 grayscale opacity-80">
                                    <x-heroicon-o-credit-card class="size-5" />
                                </div>
                                <div class="overflow-hidden">
                                    <h3 class="text-base font-semibold text-neutral-900 leading-tight mb-1 truncate">
                                        {{ $card->name }}
                                    </h3>
                                    <div class="flex flex-col gap-1 text-sm text-neutral-500 mt-1">
                                        <div class="truncate">
                                            <span class="text-sm text-neutral-500">{{ $card->financialAccount->name }}</span>
                                        </div>
                                        <span class="text-xs">Excluído em {{ $card->deleted_at->formatShort() }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0">
                                <x-dropdown position="bottom-end" accent contentClass="min-w-max">
                                    <x-slot name="trigger">
                                        <button type="button" class="cursor-pointer rounded-md border border-neutral-300 p-2 transition duration-150 ease-in-out hover:bg-neutral-100">
                                            <x-heroicon-o-ellipsis-horizontal class="size-5" />
                                        </button>
                                    </x-slot>

                                    <x-slot name="content">
                                        <button type="button" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100 cursor-pointer" @click="openRestore({{ $card->id }}, {{ Js::from($card->name) }})">
                                            <x-heroicon-o-arrow-uturn-left class="size-5 shrink-0" />
                                            <span class="whitespace-nowrap">Restaurar</span>
                                        </button>

                                        <button type="button" class="button-scale interaction-delete w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-neutral-100 cursor-pointer" @click="openForceDelete({{ $card->id }}, {{ Js::from($card->name) }})">
                                            <x-heroicon-o-trash class="size-5 shrink-0" />
                                            <span class="whitespace-nowrap">Excluir Permanentemente</span>
                                        </button>
                                    </x-slot>
                                </x-dropdown>
                            </div>
                        </div>
                    </x-slot>
                </x-table.row>
            @empty
                <x-empty-state 
                    icon="heroicon-o-trash" 
                    title="Nenhum cartão excluído" 
                    description="Não há cartões excluídos recentemente na lixeira." 
                />
            @endforelse
        </x-table.body>

        <x-modal.restore
            modal-name="restore-card"
            item-name="o cartão"
            dynamic-item-name="selectedCardName"
            alpine-action="'{{ route('financial.cards.restore', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedCardId)"
        >
            <x-slot:content>
                Tem certeza que deseja restaurar o cartão "<span class="font-medium text-neutral-900" x-text="selectedCardName"></span>"? Ele voltará a aparecer nos seus saldos e faturamentos.
            </x-slot:content>
        </x-modal.restore>

        <x-modal.delete
            modal-name="force-delete-card"
            item-name="o cartão"
            dynamic-item-name="selectedCardName"
            permanent="true"
            alpine-action="'{{ route('financial.cards.forceDestroy', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedCardId)"
        >
            <x-slot:content>
                <p class="mb-3">Tem certeza que deseja excluir o cartão "<span class="font-medium text-neutral-900" x-text="selectedCardName"></span>" permanentemente? Esta ação é irreversível e todos os dados serão perdidos.</p>
                <div class="rounded-md bg-amber-50 p-3 border border-amber-200 text-left">
                    <div class="flex">
                        <div class="shrink-0">
                            <x-heroicon-m-exclamation-triangle class="size-5 text-amber-400" />
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800">Atenção aos vínculos</h3>
                            <div class="mt-1 text-sm text-amber-700">
                                <p>A exclusão não será permitida se o cartão possuir faturas ou recorrências ativas ou arquivadas.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </x-slot:content>
        </x-modal.delete>
    </x-table>

    @if($cards->hasPages())
        <div class="mt-6">
            {{ $cards->links() }}
        </div>
    @endif
</x-layouts.financial>
