@props([
    'summary',
    'actionUrl' => null,
])

@php
    $formatSignedCurrency = function (float $amount): string {
        if (abs($amount) < 0.005) {
            return formatCurrency(0);
        }

        return ($amount > 0 ? '+ ' : '- ') . formatCurrency(abs($amount));
    };

    $formatComparison = function (float $current, float $variation, string $metric): array {
        if (abs($variation) < 0.005) {
            return [
                'text' => 'Sem variação em relação ao mês anterior',
                'class' => 'text-neutral-500',
            ];
        }

        $previous = $current - $variation;
        $direction = $variation > 0 ? '↑' : '↓';
        $percentage = abs($previous) >= 0.005
            ? ' ('.($variation > 0 ? '+' : '-').number_format(abs(($variation / abs($previous)) * 100), 1, ',', '.').'%)'
            : ' · nova movimentação';
        $isFavorable = match ($metric) {
            'expense' => $variation < 0,
            default => $variation > 0,
        };

        return [
            'text' => "{$direction} ".formatCurrency(abs($variation))."{$percentage} vs. mês anterior",
            'class' => $isFavorable ? 'text-green-700' : 'text-red-700',
        ];
    };

    $hasPositiveResult = $summary['net'] >= 0;
    $incomeComparison = $formatComparison($summary['income'], $summary['income_variation'], 'income');
    $expenseComparison = $formatComparison($summary['expense'], $summary['expense_variation'], 'expense');
    $netComparison = $formatComparison($summary['net'], $summary['net_variation'], 'net');
@endphp

<div class="space-y-6">
    <x-card class="space-y-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xxs font-bold uppercase tracking-wider text-neutral-500">Resumo do período</p>
                <h3 class="mt-1 text-xl font-bold text-neutral-900">{{ $summary['period'] }}</h3>
                <p class="mt-1 text-sm text-neutral-600">Comparado com {{ $summary['previous_period'] }}</p>
            </div>

            <x-badge :color="$hasPositiveResult ? 'green' : 'yellow'" size="sm">
                {{ $hasPositiveResult ? 'Resultado positivo' : 'Resultado negativo' }}
            </x-badge>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-finance.kpi-card
                title="Receitas"
                :value="formatCurrency($summary['income'])"
                icon="heroicon-o-arrow-trending-up"
                color="green"
                :hide-icon-on-mobile="true"
            >
                <span class="{{ $incomeComparison['class'] }}">{{ $incomeComparison['text'] }}</span>
            </x-finance.kpi-card>

            <x-finance.kpi-card
                title="Despesas"
                :value="formatCurrency($summary['expense'])"
                icon="heroicon-o-arrow-trending-down"
                color="red"
                :hide-icon-on-mobile="true"
            >
                <span class="{{ $expenseComparison['class'] }}">{{ $expenseComparison['text'] }}</span>
            </x-finance.kpi-card>

            <x-finance.kpi-card
                title="Resultado líquido"
                :value="$formatSignedCurrency($summary['net'])"
                icon="heroicon-o-scale"
                :color="$hasPositiveResult ? 'green' : 'red'"
                :hide-icon-on-mobile="true"
            >
                <span class="{{ $netComparison['class'] }}">{{ $netComparison['text'] }}</span>
            </x-finance.kpi-card>
        </div>
    </x-card>

    <x-card class="space-y-4">
        <div>
            <p class="text-xxs font-bold uppercase tracking-wider text-neutral-500">Posição financeira atual</p>
            <p class="mt-1 text-sm text-neutral-600">Valores atualizados no momento do envio.</p>
        </div>

        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-neutral-50 p-4">
                <dt class="text-xs font-semibold text-neutral-500">Saldo em contas</dt>
                <dd class="mt-1 text-base font-bold text-neutral-900">{{ formatCurrency($summary['account_balance']) }}</dd>
            </div>
            <div class="rounded-xl bg-neutral-50 p-4">
                <dt class="text-xs font-semibold text-neutral-500">Compromissos pendentes</dt>
                <dd class="mt-1 text-base font-bold text-neutral-900">{{ formatCurrency($summary['pending_commitments']) }}</dd>
            </div>
            <div class="rounded-xl bg-neutral-900 p-4">
                <dt class="text-xs font-semibold text-neutral-300">Saldo líquido</dt>
                <dd class="mt-1 text-base font-bold text-white">{{ $formatSignedCurrency($summary['net_balance']) }}</dd>
            </div>
        </dl>

        @if($actionUrl)
            <div class="border-t border-neutral-100 pt-4">
                <x-button :href="$actionUrl" class="w-full sm:w-auto">
                    <x-heroicon-m-chart-bar-square class="size-4!" />
                    Abrir painel financeiro
                </x-button>
            </div>
        @endif
    </x-card>

    <details class="group rounded-xl border border-neutral-200 bg-white">
        <summary class="flex cursor-pointer items-center justify-between gap-4 p-4 text-sm font-semibold text-neutral-900">
            Categorias de receitas ({{ count($summary['income_categories']) }})
            <x-heroicon-m-chevron-down class="size-5 shrink-0 text-neutral-400 transition-transform group-open:rotate-180" />
        </summary>
        <div class="border-t border-neutral-100 p-4">
            @if($summary['income_categories'] !== [])
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach($summary['income_categories'] as $category)
                        @php($tagColor = $category['color'] ?? '#9ca3af')
                        <div class="rounded-lg border border-neutral-100 bg-white p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-2 text-sm font-medium text-neutral-800">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg" style="background-color: {{ $tagColor }}15; color: {{ $tagColor }};">
                                        <x-dynamic-component :component="$category['icon'] ?? 'heroicon-o-tag'" class="size-4" />
                                    </span>
                                    <span class="truncate">{{ $category['name'] }}</span>
                                </span>
                                <span class="shrink-0 text-right text-sm font-bold text-green-700">{{ formatCurrency($category['amount']) }}</span>
                            </div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-full rounded-full" style="width: {{ $category['percentage'] }}%; background-color: {{ $tagColor }};"></div>
                            </div>
                            <p class="mt-1.5 text-xxs font-medium text-neutral-500">{{ $category['percentage'] }}% das receitas</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-neutral-500">Não houve receitas categorizadas no período.</p>
            @endif
        </div>
    </details>

    <details class="group rounded-xl border border-neutral-200 bg-white">
        <summary class="flex cursor-pointer items-center justify-between gap-4 p-4 text-sm font-semibold text-neutral-900">
            Categorias de despesas ({{ count($summary['expense_categories']) }})
            <x-heroicon-m-chevron-down class="size-5 shrink-0 text-neutral-400 transition-transform group-open:rotate-180" />
        </summary>
        <div class="border-t border-neutral-100 p-4">
            @if($summary['expense_categories'] !== [])
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach($summary['expense_categories'] as $category)
                        @php($tagColor = $category['color'] ?? '#9ca3af')
                        <div class="rounded-lg border border-neutral-100 bg-white p-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-2 text-sm font-medium text-neutral-800">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg" style="background-color: {{ $tagColor }}15; color: {{ $tagColor }};">
                                        <x-dynamic-component :component="$category['icon'] ?? 'heroicon-o-tag'" class="size-4" />
                                    </span>
                                    <span class="truncate">{{ $category['name'] }}</span>
                                </span>
                                <span class="shrink-0 text-right text-sm font-bold text-red-700">{{ formatCurrency($category['amount']) }}</span>
                            </div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-full rounded-full" style="width: {{ $category['percentage'] }}%; background-color: {{ $tagColor }};"></div>
                            </div>
                            <p class="mt-1.5 text-xxs font-medium text-neutral-500">{{ $category['percentage'] }}% das despesas</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-neutral-500">Não houve despesas categorizadas no período.</p>
            @endif
        </div>
    </details>
</div>
