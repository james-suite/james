<x-layouts.financial>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('financial.cards.index') }}">Cartões</x-breadcrumbs.item>
            
            <x-breadcrumbs.item>{{ $card->name }}</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header title="Detalhes do Cartão">
        <x-back-button fallback="{{ route('financial.cards.index') }}" class="flex-1 sm:flex-initial" />

        <x-button color="outline" href="{{ route('financial.cards.edit', $card) }}" class="bg-white flex-1 sm:flex-initial">
            <x-heroicon-o-pencil-square class="size-4" />
            Editar
        </x-button>

        <x-modal.delete
            action="{{ route('financial.cards.destroy', $card) }}"
            button-class="flex-1 sm:flex-initial"
            item-name="o cartão"
            item-desc="{{ $card->name }}"
            title="Excluir Cartão"
        />
    </x-page-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-8">
        <x-card class="col-span-full md:col-span-1 flex flex-col justify-center">
            <h3 class="text-neutral-500 font-medium text-sm mb-1">Conta Vinculada</h3>
            <div class="font-semibold text-lg text-neutral-900">{{ $card->financialAccount->name }}</div>
            
            <div class="mt-4 pt-4 border-t border-neutral-100 flex justify-between">
                <div>
                    <h3 class="text-neutral-500 font-medium text-xs mb-1">Fechamento</h3>
                    <div class="font-medium text-neutral-900">Dia {{ $card->closing_day }}</div>
                </div>
                <div>
                    <h3 class="text-neutral-500 font-medium text-xs mb-1">Vencimento</h3>
                    <div class="font-medium text-neutral-900">Dia {{ $card->due_day }}</div>
                </div>
            </div>
        </x-card>
        
        <x-card class="col-span-full md:col-span-2 flex flex-col justify-center">
            @if($card->credit_limit > 0)
                @php
                    $usedLimit = $card->usedLimit();
                    $percentage = min(100, max(0, ($usedLimit / $card->credit_limit) * 100));
                    $colorClass = $percentage > 90 ? 'bg-red-500' : ($percentage > 75 ? 'bg-amber-500' : 'bg-primary-500');
                    $available = max(0, $card->credit_limit - $usedLimit);
                @endphp
                
                <div class="flex justify-between items-end mb-4">
                    <div>
                        <h3 class="text-neutral-500 font-medium text-sm mb-1">Limite Disponível</h3>
                        <div class="font-bold text-3xl text-neutral-900">{{ formatCurrency($available) }}</div>
                    </div>
                    <div class="text-right">
                        <h3 class="text-neutral-500 font-medium text-sm mb-1">Limite Total</h3>
                        <div class="font-medium text-neutral-700">{{ formatCurrency($card->credit_limit) }}</div>
                    </div>
                </div>
                
                <x-progress :value="$usedLimit" :max="$card->credit_limit" :showValue="false" class="h-3" />
                <div class="mt-2 text-xs font-medium text-neutral-500">
                    Usado: {{ formatCurrency($usedLimit) }} ({{ number_format($percentage, 1, ',', '.') }}%)
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-4 text-center">
                    <x-heroicon-o-credit-card class="size-8 text-neutral-300 mb-2" />
                    <p class="text-neutral-500 font-medium">Este cartão não possui limite configurado.</p>
                </div>
            @endif
        </x-card>
    </div>

    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-bold text-neutral-900">Faturas</h2>
    </div>

    <x-table class="overflow-hidden">
        <x-table.header class="grid-cols-[1.5fr_1fr_1fr_1.5fr_100px_60px] hidden lg:grid">
            <x-table.column>Mês/Ano</x-table.column>
            <x-table.column>Fechamento</x-table.column>
            <x-table.column>Vencimento</x-table.column>
            <x-table.column>Valor</x-table.column>
            <x-table.column class="text-center">Status</x-table.column>
            <x-table.column><span class="sr-only">Ver</span></x-table.column>
        </x-table.header>
        <div class="divide-y divide-neutral-100">
            @forelse($invoices as $invoice)
                @php 
                    $total = $invoice->total(); 
                    $status = $invoice->status();
                @endphp
                <x-table.row :href="route('financial.cards.invoices.show', [$card, $invoice])" mobileBreakpoint="lg" class="grid-cols-[1.5fr_1fr_1fr_1.5fr_100px_60px] hidden lg:grid items-center hover:bg-neutral-50/50">
                    <x-table.cell class="font-medium text-neutral-900">
                        {{ formatMonthYearFull($invoice->reference_month) }}
                    </x-table.cell>
                    <x-table.cell class="text-sm text-neutral-500">
                        {{ formatShort($invoice->closing_date) }}
                    </x-table.cell>
                    <x-table.cell class="text-sm text-neutral-500">
                        {{ formatShort($invoice->due_date) }}
                    </x-table.cell>
                    <x-table.cell>
                        <div class="font-medium {{ $total < 0 ? 'text-green-600' : 'text-neutral-900' }}">
                            {{ formatCurrency($total) }}
                        </div>
                    </x-table.cell>
                    <x-table.cell class="text-center flex justify-center">
                        <x-badge :color="$status->color()" size="sm">
                            {{ $status->label() }}
                        </x-badge>
                    </x-table.cell>
                    <x-table.cell class="flex justify-end">
                        <div class="text-neutral-400 group-hover/row:text-neutral-600 transition-colors flex items-center justify-end p-2 -mr-2">
                            <x-heroicon-o-chevron-right class="size-5" />
                        </div>
                    </x-table.cell>

                    <x-slot:mobile>
                        <div class="flex items-center gap-3 w-full">
                            <div class="flex-1">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="font-medium text-neutral-900">
                                        {{ formatMonthYearFull($invoice->reference_month) }}
                                    </div>
                                    <x-badge :color="$status->color()" size="sm">
                                        {{ $status->label() }}
                                    </x-badge>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <div class="text-neutral-500">
                                        Venc: {{ formatShort($invoice->due_date) }}
                                    </div>
                                    <div class="font-medium {{ $total < 0 ? 'text-green-600' : 'text-neutral-900' }}">
                                        {{ formatCurrency($total) }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-neutral-400 group-hover/row:text-neutral-600 transition-colors shrink-0">
                                <x-heroicon-o-chevron-right class="size-5" />
                            </div>
                        </div>
                    </x-slot:mobile>
                </x-table.row>
            @empty
                <x-empty-state 
                    icon="heroicon-o-document-text" 
                    message="Nenhuma fatura gerada para este cartão." 
                />
            @endforelse
        </div>
    </x-table>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_350px] gap-6 items-start mt-8">
        <x-activity-log :model="$card" class="!mt-0" />
        <x-metadata-card :model="$card" class="w-full mb-0" />
    </div>
</x-layouts.financial>
