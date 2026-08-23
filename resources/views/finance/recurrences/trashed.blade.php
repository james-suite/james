<x-layouts.financial>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('financial.recurrences.index') }}">Recorrências</x-breadcrumbs.item>
            <x-breadcrumbs.item>Lixeira</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header 
        title="Lixeira" 
        description="Recorrências excluídas. Elas podem ser restauradas ou excluídas permanentemente." 
    >
        <x-back-button fallback="{{ route('financial.recurrences.index') }}" class="w-full sm:w-auto" />
    </x-page-header>

    <x-filter-bar 
        action="{{ route('financial.recurrences.trashed') }}" 
        searchPlaceholder="Buscar assinaturas na lixeira..." 
        :filters="['search']">
    </x-filter-bar>

    <div x-data="{
             selectedRecurrenceId: null,
             selectedRecurrenceTitle: '',
             openRestore(id, title) {
                 this.selectedRecurrenceId = id;
                 this.selectedRecurrenceTitle = title;
                 $dispatch('modal-open', 'restore-recurrence');
             },
             openForceDelete(id, title) {
                 this.selectedRecurrenceId = id;
                 this.selectedRecurrenceTitle = title;
                 $dispatch('modal-open', 'force-delete-recurrence');
             }
         }">
        
        @if($recurrences->isNotEmpty())
            <x-table>
                <x-table.header class="hidden sm:grid sm:grid-cols-[2fr_1fr_1fr_1.5fr_1fr_1.5fr]">
                    <x-table.column>Título</x-table.column>
                    <x-table.column>Valor</x-table.column>
                    <x-table.column>Frequência</x-table.column>
                    <x-table.column>Conta/Cartão</x-table.column>
                    <x-table.column>Data Exclusão</x-table.column>
                    <x-table.column align="right">Ações</x-table.column>
                </x-table.header>

                <x-table.body>
                    @foreach($recurrences as $recurrence)
                        <x-table.row class="hidden sm:grid sm:grid-cols-[2fr_1fr_1fr_1.5fr_1fr_1.5fr] opacity-80">
                            <x-table.cell>
                                <div class="flex items-center gap-3 w-full">
                                    <div class="shrink-0 flex items-center justify-center size-10 rounded-full bg-neutral-100 text-neutral-400 grayscale">
                                        @if($recurrence->type === 'expense')
                                            <x-heroicon-o-arrow-trending-down class="size-5" />
                                        @else
                                            <x-heroicon-o-arrow-trending-up class="size-5" />
                                        @endif
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="font-medium text-neutral-900 truncate flex items-center gap-2">
                                            {{ $recurrence->title }}
                                        </div>
                                    </div>
                                </div>
                            </x-table.cell>

                            <x-table.cell>
                                <span class="font-bold tracking-tight text-base {{ $recurrence->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $recurrence->type === 'income' ? '+' : '-' }}{{ formatCurrency($recurrence->amount) }}
                                </span>
                            </x-table.cell>

                            <x-table.cell>
                                <div class="flex flex-col">
                                    <span class="text-sm text-neutral-900">{{ $recurrence->frequency === 'monthly' ? 'Mensal' : 'Anual' }}</span>
                                    <span class="text-xs text-neutral-400">Dia {{ $recurrence->start_date->format('d') }}</span>
                                </div>
                            </x-table.cell>

                            <x-table.cell>
                                <div class="text-sm text-neutral-600 flex items-center gap-1">
                                    @if($recurrence->financial_credit_card_id)
                                        <x-heroicon-o-credit-card class="size-4 text-neutral-400" />
                                        {{ $recurrence->financialCreditCard->name }}
                                    @else
                                        <x-heroicon-o-building-library class="size-4 text-neutral-400" />
                                        {{ $recurrence->financialAccount->name }}
                                    @endif
                                </div>
                            </x-table.cell>

                            <x-table.cell>
                                <span class="text-sm text-neutral-600">
                                    {{ formatDateTime($recurrence->deleted_at) }}
                                </span>
                            </x-table.cell>

                            <x-table.cell align="right">
                                <div class="flex justify-end gap-2 w-full">
                                    <x-button type="button" color="outline" size="sm" class="bg-white hover:bg-neutral-50 text-neutral-600 border-neutral-300" @click="openRestore({{ $recurrence->id }}, {{ Js::from($recurrence->title) }})">
                                        <x-heroicon-o-arrow-uturn-left class="size-4" />
                                        Restaurar
                                    </x-button>

                                    <x-button type="button" color="outline" size="sm" class="bg-white hover:bg-red-50 text-red-600 border-red-200 interaction-delete" @click="openForceDelete({{ $recurrence->id }}, {{ Js::from($recurrence->title) }})">
                                        <x-heroicon-o-trash class="size-4" />
                                        Excluir
                                    </x-button>
                                </div>
                            </x-table.cell>

                            <x-slot name="mobile">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0 flex items-center gap-3">
                                        <div class="shrink-0 flex items-center justify-center size-10 rounded-full bg-neutral-100 text-neutral-400 grayscale">
                                            @if($recurrence->type === 'expense')
                                                <x-heroicon-o-arrow-trending-down class="size-5" />
                                            @else
                                                <x-heroicon-o-arrow-trending-up class="size-5" />
                                            @endif
                                        </div>
                                        <div class="overflow-hidden">
                                            <h3 class="text-base font-semibold text-neutral-900 leading-tight mb-1 truncate flex items-center gap-2">
                                                {{ $recurrence->title }}
                                            </h3>
                                            <div class="flex flex-col gap-1 text-sm text-neutral-500 mt-1">
                                                <div class="truncate font-bold tracking-tight text-base {{ $recurrence->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $recurrence->type === 'income' ? '+' : '-' }}{{ formatCurrency($recurrence->amount) }}
                                                </div>
                                                <span class="text-xs">Excluída: {{ formatShort($recurrence->deleted_at) }}</span>
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
                                                <button type="button" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100 cursor-pointer" @click="openRestore({{ $recurrence->id }}, {{ Js::from($recurrence->title) }})">
                                                    <x-heroicon-o-arrow-uturn-left class="size-5 shrink-0" />
                                                    <span class="whitespace-nowrap">Restaurar</span>
                                                </button>

                                                <button type="button" class="button-scale interaction-delete w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-neutral-100 cursor-pointer" @click="openForceDelete({{ $recurrence->id }}, {{ Js::from($recurrence->title) }})">
                                                    <x-heroicon-o-trash class="size-5 shrink-0" />
                                                    <span class="whitespace-nowrap">Excluir Permanentemente</span>
                                                </button>
                                            </x-slot>
                                        </x-dropdown>
                                    </div>
                                </div>
                            </x-slot>
                        </x-table.row>
                    @endforeach
                </x-table.body>
            </x-table>
        @else
            <x-card class="p-6">
                <x-empty-state 
                    icon="heroicon-o-trash" 
                    title="Nenhuma recorrência excluída" 
                    description="Não há assinaturas ou contas fixas na lixeira." 
                />
            </x-card>
        @endif

        <x-modal.restore
            modal-name="restore-recurrence"
            item-name="a recorrência"
            dynamic-item-name="selectedRecurrenceTitle"
            alpine-action="'{{ route('financial.recurrences.restore', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedRecurrenceId)"
        />

        <x-modal.delete
            modal-name="force-delete-recurrence"
            item-name="a recorrência"
            dynamic-item-name="selectedRecurrenceTitle"
            permanent="true"
            warning="Isso não excluirá as transações geradas por esta recorrência, mas o vínculo se perderá para sempre."
            alpine-action="'{{ route('financial.recurrences.forceDestroy', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedRecurrenceId)"
        />
        
        @if($recurrences->hasPages())
            <div class="px-6 py-4 border-t border-neutral-200">
                {{ $recurrences->links() }}
            </div>
        @endif
    </div>
</x-layouts.financial>
