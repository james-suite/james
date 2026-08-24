<x-layouts.financial>
    <x-page-header title="Recorrências" :action="route('financial.recurrences.create')" actionText="Nova Recorrência" icon="heroicon-o-plus">
        @if($hasTrashed)
            <x-button color="outline" href="{{ route('financial.recurrences.trashed') }}" class="bg-white">
                <x-heroicon-o-trash class="size-4" />
                <span class="hidden sm:inline">Lixeira</span>
            </x-button>
        @endif
    </x-page-header>

    <x-filter-bar
        action="{{ route('financial.recurrences.index') }}"
        searchPlaceholder="Buscar por título..."
        :filters="['search']">
    </x-filter-bar>

    <x-table class="lg:mb-8">
        @if($recurrences->isNotEmpty())
            <x-table.header class="hidden sm:grid sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.5fr)_minmax(0,1fr)]">
                <x-table.column>Título</x-table.column>
                <x-table.column>Valor</x-table.column>
                <x-table.column>Frequência</x-table.column>
                <x-table.column>Conta/Cartão</x-table.column>
                <x-table.column>Próximo Proc.</x-table.column>
            </x-table.header>
        @endif

        <x-table.body>
            @forelse($recurrences as $recurrence)
                <x-table.row href="{{ route('financial.recurrences.edit', $recurrence) }}" class="hidden sm:grid sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.5fr)_minmax(0,1fr)] group transition-all">
                    <x-table.cell>
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $recurrence->type === 'income' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                                @if($recurrence->type === 'expense')
                                    <x-heroicon-o-arrow-trending-down class="size-5" />
                                @else
                                    <x-heroicon-o-arrow-trending-up class="size-5" />
                                @endif
                            </div>
                            <div class="flex min-w-0 flex-1 items-center gap-2">
                                <span class="min-w-0 flex-1 truncate font-semibold text-neutral-900">
                                    {{ $recurrence->title }}
                                </span>
                                @if(!$recurrence->is_active)
                                    <span class="shrink-0 rounded bg-neutral-100 px-1.5 py-0.5 text-xxs font-bold uppercase text-neutral-600 ring-1 ring-inset ring-neutral-300">Pausada</span>
                                @endif
                            </div>
                        </div>
                    </x-table.cell>

                    <x-table.cell>
                        <span class="whitespace-nowrap font-bold tracking-tight text-base {{ $recurrence->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $recurrence->type === 'income' ? '+' : '-' }} {{ formatCurrency($recurrence->amount) }}
                        </span>
                    </x-table.cell>

                    <x-table.cell>
                        <div class="flex flex-col gap-1">
                            <span class="text-sm text-neutral-900">{{ $recurrence->frequency === 'monthly' ? 'Mensal' : 'Anual' }}</span>
                            <span class="text-xs text-neutral-400">Dia {{ $recurrence->start_date->day }}</span>
                        </div>
                    </x-table.cell>

                    <x-table.cell>
                        <div class="flex min-w-0 items-center gap-1.5 truncate text-neutral-600">
                            @if($recurrence->financial_credit_card_id)
                                <x-heroicon-o-credit-card class="size-4 shrink-0" />
                                <span class="truncate">{{ $recurrence->financialCreditCard->name }}</span>
                            @else
                                <x-heroicon-o-building-library class="size-4 shrink-0" />
                                <span class="truncate">{{ $recurrence->financialAccount->name }}</span>
                            @endif
                        </div>
                    </x-table.cell>

                    <x-table.cell>
                        <span class="text-sm text-neutral-600">
                            {{ $recurrence->next_processing_date ? formatShort($recurrence->next_processing_date) : 'N/A' }}
                        </span>
                    </x-table.cell>

                    <x-slot name="mobile">
                        <div class="flex w-full items-start justify-between gap-3">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $recurrence->type === 'income' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                                    @if($recurrence->type === 'expense')
                                        <x-heroicon-o-arrow-trending-down class="size-5" />
                                    @else
                                        <x-heroicon-o-arrow-trending-up class="size-5" />
                                    @endif
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                                    <h3 class="flex min-w-0 flex-wrap items-center gap-2 text-base font-semibold leading-tight text-neutral-900">
                                        <span class="min-w-0 flex-1 truncate">{{ $recurrence->title }}</span>
                                        @if(!$recurrence->is_active)
                                            <span class="shrink-0 rounded bg-neutral-100 px-1.5 py-0.5 text-xxs font-bold uppercase text-neutral-600 ring-1 ring-inset ring-neutral-300">Pausada</span>
                                        @endif
                                    </h3>
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-neutral-500">
                                        <span>{{ $recurrence->frequency === 'monthly' ? 'Mensal' : 'Anual' }}</span>
                                        <span>&bull;</span>
                                        <span>Dia {{ $recurrence->start_date->day }}</span>
                                    </div>
                                    <div class="flex min-w-0 items-center gap-1.5 text-sm text-neutral-500">
                                        @if($recurrence->financial_credit_card_id)
                                            <x-heroicon-o-credit-card class="size-3 shrink-0" />
                                            <span class="truncate">{{ $recurrence->financialCreditCard->name }}</span>
                                        @else
                                            <x-heroicon-o-building-library class="size-3 shrink-0" />
                                            <span class="truncate">{{ $recurrence->financialAccount->name }}</span>
                                        @endif
                                    </div>
                                    <span class="text-sm text-neutral-500">Próx: {{ $recurrence->next_processing_date ? formatShort($recurrence->next_processing_date) : 'N/A' }}</span>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="whitespace-nowrap font-bold tracking-tight text-base {{ $recurrence->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $recurrence->type === 'income' ? '+' : '-' }} {{ formatCurrency($recurrence->amount) }}
                                </div>
                            </div>
                        </div>
                    </x-slot>
                </x-table.row>
            @empty
                <x-empty-state
                    icon="heroicon-o-arrow-path"
                    title="Nenhuma recorrência cadastrada"
                    description="Cadastre suas assinaturas ou contas fixas para que o sistema gere as transações automaticamente."
                />
            @endforelse
        </x-table.body>
    </x-table>

    @if($recurrences->hasPages())
        <div class="mt-6 pb-6">
            {{ $recurrences->links() }}
        </div>
    @endif
</x-layouts.financial>
