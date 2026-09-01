@props([
    'alert',
    'actionUrl' => null,
])

@php
    $formatSignedCurrency = function (float $amount): string {
        if (abs($amount) < 0.005) {
            return formatCurrency(0);
        }

        return ($amount > 0 ? '+ ' : '- ') . formatCurrency(abs($amount));
    };
@endphp

<div class="space-y-6">
    <x-card class="space-y-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xxs font-bold uppercase tracking-wider text-neutral-500">Próximos vencimentos</p>
                <h3 class="mt-1 text-xl font-bold text-neutral-900">Hoje e amanhã</h3>
                <p class="mt-1 text-sm text-neutral-600">{{ $alert['total_items'] }} {{ $alert['total_items'] === 1 ? 'item previsto' : 'itens previstos' }} no período.</p>
            </div>

            <x-badge color="yellow" size="sm">Atenção necessária</x-badge>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-finance.kpi-card
                title="Receitas"
                :value="formatCurrency($alert['income'])"
                icon="heroicon-o-arrow-trending-up"
                color="green"
                :hide-icon-on-mobile="true"
            />

            <x-finance.kpi-card
                title="Despesas"
                :value="formatCurrency($alert['expense'])"
                icon="heroicon-o-arrow-trending-down"
                color="red"
                :hide-icon-on-mobile="true"
            />

            <x-finance.kpi-card
                title="Impacto líquido"
                :value="$formatSignedCurrency($alert['net'])"
                icon="heroicon-o-scale"
                :color="$alert['net'] >= 0 ? 'green' : 'red'"
                :hide-icon-on-mobile="true"
            />
        </div>
    </x-card>

    @foreach($alert['days'] as $day)
        <x-card class="!p-0 overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-neutral-100 bg-neutral-50 px-5 py-4">
                <div>
                    <p class="text-xxs font-bold uppercase tracking-wider text-neutral-500">Vencimento</p>
                    <h4 class="mt-1 text-base font-bold text-neutral-900">{{ $day['label'] }} · {{ $day['date'] }}</h4>
                </div>
                <x-heroicon-o-calendar-days class="size-5 shrink-0 text-neutral-400" />
            </div>

            <div class="divide-y divide-neutral-100">
                @foreach([
                    ['items' => $day['incomes'], 'title' => 'Receitas', 'icon' => 'heroicon-o-arrow-trending-up', 'color' => 'green'],
                    ['items' => $day['expenses'], 'title' => 'Despesas', 'icon' => 'heroicon-o-arrow-trending-down', 'color' => 'red'],
                    ['items' => $day['invoices'], 'title' => 'Faturas', 'icon' => 'heroicon-o-credit-card', 'color' => 'yellow'],
                ] as $section)
                    @if($section['items'] !== [])
                        <section class="space-y-3 px-5 py-4">
                            <p class="flex items-center gap-2 text-xxs font-bold uppercase tracking-wider {{ $section['color'] === 'green' ? 'text-green-700' : ($section['color'] === 'red' ? 'text-red-700' : 'text-yellow-800') }}">
                                <x-dynamic-component :component="$section['icon']" class="size-4" />
                                {{ $section['title'] }}
                            </p>

                            <div class="space-y-3">
                                @foreach($section['items'] as $item)
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="truncate text-sm font-semibold text-neutral-900">{{ $item['description'] }}</p>
                                                @if($item['is_recurrence'])
                                                    <x-badge color="blue" size="sm">Recorrente</x-badge>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-xs text-neutral-500">
                                                {{ $item['destination'] }}
                                                @if($item['is_invoice'])
                                                    · {{ $item['transactions_count'] }} {{ $item['transactions_count'] === 1 ? 'lançamento' : 'lançamentos' }}
                                                    · {{ $item['recurrences_count'] }} {{ $item['recurrences_count'] === 1 ? 'recorrência' : 'recorrências' }}
                                                @endif
                                            </p>
                                        </div>
                                        <span class="shrink-0 text-sm font-bold {{ $section['color'] === 'green' ? 'text-green-700' : ($section['color'] === 'red' ? 'text-red-700' : 'text-yellow-800') }}">
                                            {{ $section['color'] === 'green' ? '+ ' : '- ' }}{{ formatCurrency($item['amount']) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach
            </div>
        </x-card>
    @endforeach

    @if($actionUrl)
        <x-button :href="$actionUrl" class="w-full sm:w-auto">
            <x-heroicon-m-chart-bar-square class="size-4!" />
            Ver vencimentos no painel financeiro
        </x-button>
    @endif
</div>
