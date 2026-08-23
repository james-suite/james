<x-layouts.app>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('settlements.index') }}">Acertos</x-breadcrumbs.item>
            <x-breadcrumbs.item href="{{ route('settlements.groups.index') }}">Contas Divididas</x-breadcrumbs.item>
            <x-breadcrumbs.item>Lixeira</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header 
        title="Lixeira de Contas Divididas" 
        description="Divisões de contas excluídas. Restaure-as ou exclua permanentemente." 
    >
        <x-back-button fallback="{{ route('settlements.groups.index') }}" class="w-full sm:w-auto" />
    </x-page-header>

    <x-table class="lg:mb-8 mt-6"
         x-data="{
             selectedGroupId: null,
             selectedGroupDesc: '',
             openRestore(id, desc) {
                 this.selectedGroupId = id;
                 this.selectedGroupDesc = desc;
                 $dispatch('modal-open', 'restore-group');
             },
             openForceDelete(id, desc) {
                 this.selectedGroupId = id;
                 this.selectedGroupDesc = desc;
                 $dispatch('modal-open', 'force-delete-group');
             }
         }">
        @if($settlementGroups->isNotEmpty())
            {{-- Header - Desktop --}}
            <x-table.header class="hidden sm:grid sm:grid-cols-[2fr_1fr_1.5fr]">
                <x-table.column>Descrição</x-table.column>
                <x-table.column>Data da Exclusão</x-table.column>
                <x-table.column align="right">Ações</x-table.column>
            </x-table.header>
        @endif

        <x-table.body>
            @forelse($settlementGroups as $group)
                <x-table.row class="hidden sm:grid sm:grid-cols-[2fr_1fr_1.5fr]">
                    <x-table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium text-neutral-900 truncate">{{ $group->description }}</span>
                            <span class="text-xs text-neutral-500 font-semibold grayscale">
                                {{ formatCurrency($group->total_amount) }}
                            </span>
                        </div>
                    </x-table.cell>

                    <x-table.cell>
                        <span class="text-sm text-neutral-500">
                            {{ $group->deleted_at->formatDateTime() }}
                        </span>
                    </x-table.cell>

                    <x-table.cell align="right">
                        <div class="flex justify-end gap-2 w-full">
                            <x-button type="button" color="outline" class="bg-white hover:bg-neutral-50 text-neutral-600 border-neutral-300" @click="openRestore({{ $group->id }}, {{ Js::from($group->description) }})">
                                <x-heroicon-o-arrow-uturn-left class="size-4" />
                                Restaurar
                            </x-button>

                            <x-button type="button" color="outline" class="bg-white hover:bg-red-50 text-red-600 border-red-200 interaction-delete" @click="openForceDelete({{ $group->id }}, {{ Js::from($group->description) }})">
                                <x-heroicon-o-trash class="size-4" />
                                Excluir
                            </x-button>
                        </div>
                    </x-table.cell>

                    <x-slot name="mobile">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0 flex flex-col gap-1">
                                <h3 class="text-sm font-semibold text-neutral-900 leading-tight truncate">
                                    {{ $group->description }}
                                </h3>
                                <div class="text-xs text-neutral-500">
                                    Excluído em {{ $group->deleted_at->formatShort() }}
                                </div>
                            </div>
                            <div class="shrink-0 flex flex-col items-end gap-2">
                                <span class="font-semibold text-sm grayscale">
                                    {{ formatCurrency($group->total_amount) }}
                                </span>
                                
                                <x-dropdown position="bottom-end" accent contentClass="min-w-max">
                                    <x-slot name="trigger">
                                        <button type="button" class="cursor-pointer rounded-md border border-neutral-300 p-1.5 transition duration-150 ease-in-out hover:bg-neutral-100">
                                            <x-heroicon-o-ellipsis-horizontal class="size-4" />
                                        </button>
                                    </x-slot>

                                    <x-slot name="content">
                                        <button type="button" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100 cursor-pointer" @click="openRestore({{ $group->id }}, {{ Js::from($group->description) }})">
                                            <x-heroicon-o-arrow-uturn-left class="size-5 shrink-0" />
                                            <span class="whitespace-nowrap">Restaurar</span>
                                        </button>

                                        <button type="button" class="button-scale interaction-delete w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-neutral-100 cursor-pointer" @click="openForceDelete({{ $group->id }}, {{ Js::from($group->description) }})">
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
                    title="Nenhuma divisão excluída" 
                    description="Não há divisões de contas na lixeira." 
                />
            @endforelse
        </x-table.body>

        <x-modal.restore
            modal-name="restore-group"
            item-name="a divisão"
            dynamic-item-name="selectedGroupDesc"
            description="Isso reativará todos os acertos individuais associados a ela e a transação financeira, se houver."
            alpine-action="'{{ route('settlements.groups.restore', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedGroupId)"
        />

        <x-modal.delete
            modal-name="force-delete-group"
            item-name="a divisão de conta"
            dynamic-item-name="selectedGroupDesc"
            permanent="true"
            warning="Esta ação removerá definitivamente todos os acertos vinculados e a transação financeira relacionada, sendo irreversível."
            alpine-action="'{{ route('settlements.groups.force-delete', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedGroupId)"
        />
    </x-table>

    @if($settlementGroups->hasPages())
        <div class="mt-6">
            {{ $settlementGroups->links() }}
        </div>
    @endif
</x-layouts.app>
