<x-layouts.app>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('settlements.index') }}">Acertos</x-breadcrumbs.item>
            <x-breadcrumbs.item href="{{ route('settlements.history') }}">Histórico Global</x-breadcrumbs.item>
            <x-breadcrumbs.item>Lixeira</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header 
        title="Lixeira" 
        description="Acertos excluídos. Eles podem ser restaurados ou excluídos permanentemente." 
    >
        <x-back-button fallback="{{ route('settlements.history') }}" class="w-full sm:w-auto" />
    </x-page-header>

    <x-table class="lg:mb-8 mt-6"
         x-data="{
             selectedSettlementId: null,
             selectedSettlementDesc: '',
             openRestore(id, desc) {
                 this.selectedSettlementId = id;
                 this.selectedSettlementDesc = desc;
                 $dispatch('modal-open', 'restore-settlement');
             },
             openForceDelete(id, desc) {
                 this.selectedSettlementId = id;
                 this.selectedSettlementDesc = desc;
                 $dispatch('modal-open', 'force-delete-settlement');
             }
         }">
        @if($settlements->isNotEmpty())
            {{-- Header - Desktop --}}
            <x-table.header class="hidden sm:grid sm:grid-cols-[2fr_1.5fr_1fr_1.5fr]">
                <x-table.column>Acerto</x-table.column>
                <x-table.column>Contato</x-table.column>
                <x-table.column>Data da Exclusão</x-table.column>
                <x-table.column align="right">Ações</x-table.column>
            </x-table.header>
        @endif

        <x-table.body>
            @forelse($settlements as $settlement)
                <x-table.row class="hidden sm:grid sm:grid-cols-[2fr_1.5fr_1fr_1.5fr]">
                    <x-table.cell>
                        <div class="flex items-center gap-3 w-full">
                            <x-badge :color="$settlement->type->color()" class="flex items-center shrink-0 grayscale opacity-80">
                                <x-dynamic-component :component="$settlement->type->icon()" class="size-4" />
                            </x-badge>
                            <div class="overflow-hidden flex flex-col">
                                @php
                                    $isPositiveForMe = in_array($settlement->type->value, [\App\Enums\SettlementType::TheyOwe->value, \App\Enums\SettlementType::IPaid->value]);
                                    $amountColor = $isPositiveForMe ? 'text-emerald-600' : 'text-red-600';
                                    $amountPrefix = $isPositiveForMe ? '+' : '-';
                                @endphp
                                <span class="font-medium text-neutral-900 truncate">{{ $settlement->description }}</span>
                                <span class="text-xs font-semibold {{ $amountColor }} grayscale">
                                    {{ $amountPrefix }} {{ formatCurrency($settlement->amount) }}
                                </span>
                            </div>
                        </div>
                    </x-table.cell>

                    <x-table.cell>
                        <div class="flex items-center gap-2">
                            <x-avatar :model="$settlement->contact" size="sm" class="grayscale opacity-80" />
                            <span class="text-sm font-medium text-neutral-700 truncate">{{ $settlement->contact->name }}</span>
                        </div>
                    </x-table.cell>

                    <x-table.cell>
                        <span class="text-sm text-neutral-500">
                            {{ $settlement->deleted_at->formatDateTime() }}
                        </span>
                    </x-table.cell>

                    <x-table.cell align="right">
                        @if($settlement->settlement_group_id === null)
                            <div class="flex justify-end gap-2 w-full">
                                <x-button type="button" color="outline" class="bg-white hover:bg-neutral-50 text-neutral-600 border-neutral-300" @click="openRestore({{ $settlement->id }}, {{ Js::from($settlement->description) }})">
                                    <x-heroicon-o-arrow-uturn-left class="size-4" />
                                    Restaurar
                                </x-button>

                                <x-button type="button" color="outline" class="bg-white hover:bg-red-50 text-red-600 border-red-200 interaction-delete" @click="openForceDelete({{ $settlement->id }}, {{ Js::from($settlement->description) }})">
                                    <x-heroicon-o-trash class="size-4" />
                                    Excluir
                                </x-button>
                            </div>
                        @else
                            <div class="text-xs text-neutral-400 italic">
                                Gerenciado por Divisão de Conta
                            </div>
                        @endif
                    </x-table.cell>

                    <x-slot name="mobile">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0 flex items-center gap-3">
                                <x-badge :color="$settlement->type->color()" class="flex items-center shrink-0 grayscale opacity-80">
                                    <x-dynamic-component :component="$settlement->type->icon()" class="size-4" />
                                </x-badge>
                                <div class="overflow-hidden">
                                    <h3 class="text-sm font-semibold text-neutral-900 leading-tight mb-1 truncate">
                                        {{ $settlement->description }}
                                    </h3>
                                    <div class="flex flex-col gap-1 text-sm text-neutral-500 mt-1">
                                        <span class="truncate">com {{ $settlement->contact->name }}</span>
                                        <span class="text-xs">Excluído em {{ $settlement->deleted_at->formatShort() }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0 flex flex-col items-end gap-2">
                                @php
                                    $isPositiveForMe = in_array($settlement->type->value, [\App\Enums\SettlementType::TheyOwe->value, \App\Enums\SettlementType::IPaid->value]);
                                    $amountColor = $isPositiveForMe ? 'text-emerald-600' : 'text-red-600';
                                    $amountPrefix = $isPositiveForMe ? '+' : '-';
                                @endphp
                                <span class="font-semibold text-sm {{ $amountColor }} grayscale">
                                    {{ $amountPrefix }} {{ formatCurrency($settlement->amount) }}
                                </span>
                                
                                @if($settlement->settlement_group_id === null)
                                    <x-dropdown position="bottom-end" accent contentClass="min-w-max">
                                        <x-slot name="trigger">
                                            <button type="button" class="cursor-pointer rounded-md border border-neutral-300 p-1.5 transition duration-150 ease-in-out hover:bg-neutral-100">
                                                <x-heroicon-o-ellipsis-horizontal class="size-4" />
                                            </button>
                                        </x-slot>

                                        <x-slot name="content">
                                            <button type="button" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100 cursor-pointer" @click="openRestore({{ $settlement->id }}, {{ Js::from($settlement->description) }})">
                                                <x-heroicon-o-arrow-uturn-left class="size-5 shrink-0" />
                                                <span class="whitespace-nowrap">Restaurar</span>
                                            </button>

                                            <button type="button" class="button-scale interaction-delete w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-neutral-100 cursor-pointer" @click="openForceDelete({{ $settlement->id }}, {{ Js::from($settlement->description) }})">
                                                <x-heroicon-o-trash class="size-5 shrink-0" />
                                                <span class="whitespace-nowrap">Excluir Permanentemente</span>
                                            </button>
                                        </x-slot>
                                    </x-dropdown>
                                @endif
                            </div>
                        </div>
                    </x-slot>
                </x-table.row>
            @empty
                <x-empty-state 
                    icon="heroicon-o-trash" 
                    title="Nenhum acerto excluído" 
                    description="Não há acertos individuais na lixeira." 
                />
            @endforelse
        </x-table.body>

        <x-modal.restore
            modal-name="restore-settlement"
            item-name="o acerto"
            dynamic-item-name="selectedSettlementDesc"
            description="Ele voltará para o seu histórico global e reativará a transação vinculada, caso exista."
            alpine-action="'{{ route('settlements.restore', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedSettlementId)"
        />

        <x-modal.delete
            modal-name="force-delete-settlement"
            item-name="o acerto"
            dynamic-item-name="selectedSettlementDesc"
            permanent="true"
            warning="Esta ação é irreversível e removerá também a transação financeira vinculada."
            alpine-action="'{{ route('settlements.force-delete', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedSettlementId)"
        />
    </x-table>

    @if($settlements->hasPages())
        <div class="mt-6">
            {{ $settlements->links() }}
        </div>
    @endif
</x-layouts.app>
