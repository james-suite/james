<x-layouts.financial>
    @php
        $hasReportTagFilter = $selectedTagId !== null;
        $clearTagUrl = route('financial.reports', array_filter([
            'period' => $period,
            'account' => $accountId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ], fn ($value): bool => $value !== null && $value !== ''));
    @endphp

    <x-page-header title="Relatórios Financeiros" icon="heroicon-o-chart-pie"></x-page-header>

    <div class="space-y-4 pb-6 lg:hidden">
        <form action="{{ route('financial.reports') }}" method="GET" x-data="{ period: @js($period), loading: false }" @submit="loading = true">
            <x-card>
                <div class="grid gap-4">
                    <x-form-select name="period" label="Período" x-model="period">
                        <option value="this_month">Este mês</option>
                        <option value="last_month">Mês passado</option>
                        <option value="last_3m">Últimos 3 meses</option>
                        <option value="last_6m">Últimos 6 meses</option>
                        <option value="this_year">Este ano</option>
                        <option value="next_month">Próximo mês</option>
                        <option value="next_6m">Próximos 6 meses</option>
                        <option value="next_12m">Próximos 12 meses</option>
                        <option value="all_time">Todo o período</option>
                        <option value="until_today">Até hoje</option>
                        <option value="custom">Personalizado</option>
                    </x-form-select>

                    <x-form-select name="account" label="Conta">
                        <option value="">Todas as contas</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected($accountId == $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </x-form-select>

                    <div x-show="period === 'custom'" x-cloak class="grid grid-cols-2 gap-3">
                        <x-form-input name="startDate" label="Início" type="date" :value="$startDate" />
                        <x-form-input name="endDate" label="Fim" type="date" :value="$endDate" />
                    </div>

                    <x-button type="submit" class="w-full justify-center">
                        <x-heroicon-o-funnel class="size-4" />
                        Atualizar relatório
                    </x-button>
                </div>
            </x-card>
        </form>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-card class="p-4">
                <p class="text-sm font-medium text-neutral-500">Receitas</p>
                <p class="mt-1 text-xl font-bold text-green-600">+ {{ formatCurrency($summary['income']) }}</p>
            </x-card>
            <x-card class="p-4">
                <p class="text-sm font-medium text-neutral-500">Despesas</p>
                <p class="mt-1 text-xl font-bold text-red-600">- {{ formatCurrency($summary['expense']) }}</p>
            </x-card>
            <x-card class="p-4">
                <p class="text-sm font-medium text-neutral-500">Resultado</p>
                <p class="mt-1 text-xl font-bold {{ $summary['balance'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $summary['balance'] >= 0 ? '+' : '-' }} {{ formatCurrency(abs($summary['balance'])) }}
                </p>
            </x-card>
        </div>

        <x-card>
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-neutral-900">Principais categorias</h2>
                    <p class="mt-1 text-sm text-neutral-500">Toque em uma categoria para filtrar o relatório.</p>
                </div>
                <x-heroicon-o-tag class="size-5 text-neutral-400" />
            </div>

            <div class="divide-y divide-neutral-100">
                @forelse ($mobileCategories as $item)
                    <a
                        href="{{ route('financial.reports', ['period' => $period, 'account' => $accountId, 'startDate' => $startDate, 'endDate' => $endDate, 'tag_id' => $item['id']]) }}"
                        class="flex min-h-11 items-center justify-between gap-3 py-3 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:ring-inset"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <x-dynamic-component :component="$item['icon']" class="size-4 shrink-0" style="color: {{ $item['color'] }}" />
                            <span class="truncate font-medium text-neutral-900">{{ $item['name'] }}</span>
                        </span>
                        <span class="shrink-0 font-semibold {{ $item['type'] === 'expense' ? 'text-red-600' : 'text-green-600' }}">
                            {{ $item['type'] === 'expense' ? '-' : '+' }} {{ formatCurrency($item['value']) }}
                        </span>
                    </a>
                @empty
                    <p class="py-4 text-sm text-neutral-500">Nenhuma movimentação no período.</p>
                @endforelse
            </div>
        </x-card>

        <section aria-labelledby="mobile-report-transactions">
            <div class="mb-4">
                <h2 id="mobile-report-transactions" class="text-lg font-bold text-neutral-900">Lançamentos recentes</h2>
                <p class="mt-1 text-sm text-neutral-500">Os cinco últimos lançamentos do período selecionado.</p>
            </div>
            <x-finance.transaction-table
                :transactions="$transactions->getCollection()->take(5)"
                :empty-title="$hasReportTagFilter ? 'Nenhuma transação corresponde a esta tag' : 'Nenhuma transação encontrada'"
                :empty-description="$hasReportTagFilter ? 'Remova o filtro de tag para voltar a ver todos os lançamentos do período.' : 'Não há transações disponíveis no momento.'"
                :empty-action-text="$hasReportTagFilter ? 'Remover filtro' : null"
                :empty-action-route="$hasReportTagFilter ? $clearTagUrl : null"
            />
        </section>
    </div>

    <div class="hidden lg:block pb-2" x-data="reportsPage()" x-init="initCharts()">

        <!-- Filters Bar -->
        <div class="w-full mb-6">
            <x-filter-bar :show-search="false" :show-mobile-toggle="false" action="{{ route('financial.reports') }}" class="pe-2 py-3" button-class="sm:w-11 h-11" align="end">
                <div class="flex flex-col sm:flex-row items-stretch divide-y sm:divide-y-0 sm:divide-x divide-neutral-200">

                    {{-- Period --}}
                    <div class="flex flex-col justify-center py-1 sm:py-0 px-1 sm:px-3">
                        <label class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider pl-3 mb-0.5">Período</label>
                        <x-filter-bar.select name="period" x-model="period" @change="submitIfNotCustom()">
                            <option value="this_month" @selected($period === 'this_month')>Este Mês</option>
                            <option value="last_month" @selected($period === 'last_month')>Mês Passado</option>
                            <option value="last_3m" @selected($period === 'last_3m')>Últimos 3 Meses</option>
                            <option value="last_6m" @selected($period === 'last_6m')>Últimos 6 Meses</option>
                            <option value="this_year" @selected($period === 'this_year')>Este Ano</option>
                            <option value="next_month" @selected($period === 'next_month')>Próximo Mês</option>
                            <option value="next_6m" @selected($period === 'next_6m')>Próximos 6 Meses</option>
                            <option value="next_12m" @selected($period === 'next_12m')>Próximos 12 Meses</option>
                            <option value="all_time" @selected($period === 'all_time')>Todo o Tempo</option>
                            <option value="until_today" @selected($period === 'until_today')>Até Hoje</option>
                            <option value="custom" @selected($period === 'custom')>Personalizado</option>
                        </x-filter-bar.select>
                    </div>

                    {{-- Date range --}}
                    <div class="flex flex-col sm:flex-row items-stretch divide-y sm:divide-y-0 sm:divide-x divide-neutral-200">

                        <div class="flex flex-col justify-center py-1 sm:py-0 px-1 sm:px-3">
                            <label class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider pl-3 mb-0.5">Início</label>
                            <div class="relative flex items-center transition-opacity" :class="period !== 'custom' && 'opacity-50'">
                                <span class="pointer-events-none absolute left-2.5 flex items-center text-neutral-400">
                                    <x-heroicon-o-calendar-days class="size-3.5 shrink-0" />
                                </span>
                                <input type="date" name="startDate" value="{{ $startDate }}"
                                       @change="period = 'custom'"
                                       x-bind:disabled="period !== 'custom'"
                                       class="w-full sm:w-auto bg-transparent border-0 py-2 sm:py-1.5 pl-7 pr-2 text-sm text-neutral-600 focus:outline-none focus:ring-0 focus:bg-neutral-100 rounded-md cursor-pointer disabled:cursor-not-allowed transition-colors [&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:inset-0 [&::-webkit-calendar-picker-indicator]:w-full [&::-webkit-calendar-picker-indicator]:h-full [&::-webkit-calendar-picker-indicator]:cursor-pointer [&:disabled::-webkit-calendar-picker-indicator]:hidden">
                            </div>
                        </div>

                        <div class="flex flex-col justify-center py-1 sm:py-0 px-1 sm:px-3">
                            <label class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider pl-3 mb-0.5">Fim</label>
                            <div class="relative flex items-center transition-opacity" :class="period !== 'custom' && 'opacity-50'">
                                <span class="pointer-events-none absolute left-2.5 flex items-center text-neutral-400">
                                    <x-heroicon-o-calendar-days class="size-3.5 shrink-0" />
                                </span>
                                <input type="date" name="endDate" value="{{ $endDate }}"
                                       @change="period = 'custom'"
                                       x-bind:disabled="period !== 'custom'"
                                       class="w-full sm:w-auto bg-transparent border-0 py-2 sm:py-1.5 pl-7 pr-2 text-sm text-neutral-600 focus:outline-none focus:ring-0 focus:bg-neutral-100 rounded-md cursor-pointer disabled:cursor-not-allowed transition-colors [&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:inset-0 [&::-webkit-calendar-picker-indicator]:w-full [&::-webkit-calendar-picker-indicator]:h-full [&::-webkit-calendar-picker-indicator]:cursor-pointer [&:disabled::-webkit-calendar-picker-indicator]:hidden">
                            </div>
                        </div>
                    </div>

                    {{-- Accounts --}}
                    <div class="flex flex-col justify-center py-1 sm:py-0 px-1 sm:px-3">
                        <label class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider pl-3 mb-0.5">Contas</label>
                        <x-filter-bar.select name="account" @change="submit()">
                            <option value="">Todas as Contas</option>
                            <optgroup label="Por Tipo">
                                @foreach(\App\Enums\FinancialAccountType::cases() as $type)
                                    <option value="type:{{ $type->value }}" @selected($accountId === 'type:'.$type->value)>Todas: {{ $type->label() }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Contas Específicas">
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}" @selected($accountId == $acc->id)>{{ $acc->name }}</option>
                                @endforeach
                            </optgroup>
                        </x-filter-bar.select>
                    </div>

                </div>
            </x-filter-bar>
        </div>

        <!-- Sankey Chart -->
        <x-card class="hidden lg:block mb-6">
            <h3 class="text-lg font-bold text-neutral-900 mb-4">Fluxo de Caixa</h3>
            @if(count($sankey['links'] ?? []) > 0)
                <div class="relative w-full h-[400px]" role="img" aria-label="Diagrama do fluxo de caixa">
                    <div class="w-full h-full" x-ref="chartSankey"></div>
                </div>
            @else
                <div class="flex h-[400px] flex-col items-center justify-center rounded-xl border border-dashed border-neutral-200 bg-neutral-50/60 px-4 text-center" role="status">
                    <x-heroicon-o-arrows-right-left class="mb-2 size-10 text-neutral-300" />
                    <p class="text-sm font-medium text-neutral-600">Sem fluxo para os filtros escolhidos</p>
                    <p class="mt-1 text-xs text-neutral-500">Ajuste o período, a conta ou a tag para visualizar o diagrama.</p>
                </div>
            @endif
        </x-card>

        <!-- Evolution Chart -->
        <x-card class="hidden lg:block mb-6">
            <h3 class="text-lg font-bold text-neutral-900 mb-4">Evolução de Saldo</h3>
            <x-finance.evolution-chart-base :data="json_encode($evolution)" />
        </x-card>

        <!-- Net Worth Evolution Chart -->
        <x-card class="hidden lg:block mb-6">
            <h3 class="text-lg font-bold text-neutral-900 mb-4">Evolução do Saldo Líquido</h3>
            <x-finance.evolution-chart-base :data="json_encode($netWorthEvolution)" income-label="Receita (Competência)" expense-label="Despesa (Competência)" />
        </x-card>

        <!-- Tags and Accounts Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 items-start">
            
            <!-- Coluna da Esquerda (2/3) -->
            <div class="lg:col-span-2 flex flex-col gap-4 sm:gap-6">
                <!-- Top Categories -->
                <x-card>
                    <h3 class="text-lg font-bold text-neutral-900 mb-4 px-1">Top Tags</h3>
                    @include('finance.partials.reports-tags')
                </x-card>

                <!-- All Tags -->
                <x-card>
                    <h3 class="text-lg font-bold text-neutral-900 mb-4 px-1">Todas as Tags</h3>
                    @include('finance.partials.reports-all-tags')
                </x-card>
            </div>

            <!-- Coluna da Direita (1/3) -->
            <div class="lg:col-span-1 flex flex-col gap-4 sm:gap-6">
                <!-- Net Balance -->
                <x-card>
                    <h3 class="text-lg font-bold text-neutral-900 mb-4 px-1">Saldo Líquido por Tag</h3>
                    @include('finance.partials.reports-net-tags')
                </x-card>

                @if(count($accountBalancesChart) > 0)
                <!-- Account Balances -->
                <x-card class="hidden lg:flex flex-col h-full min-h-[400px]">
                    <h3 class="text-lg font-bold text-neutral-900 mb-4 px-1">Saldos por Conta</h3>
                    <x-finance.account-balances-chart :chartData="$accountBalancesChart" />
                </x-card>
                @endif
            </div>
        </div>

        <!-- Transactions -->
        <div id="transactions-table" class="mb-6 pt-4">
            <div class="flex items-center justify-between mb-4 px-1">
                <h3 class="text-lg font-bold text-neutral-900">Transações do Período</h3>
                
                <button class="cursor-pointer text-xs font-bold text-neutral-500 hover:text-neutral-900 bg-neutral-100 hover:bg-neutral-200 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5"
                        x-cloak
                        x-show="selectedTagId !== null"
                        @click="clearTagFilter()">
                    <x-heroicon-s-x-mark class="size-3.5" />
                    Limpar Filtro
                </button>
            </div>
            @include('finance.partials.reports-transactions')
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('reportsPage', () => ({
            period: @json($period),
            selectedTagId: @js($selectedTagId),
            echarts: null,
            sankeyChart: null,
            resizeHandler: null,

            filterByTag(tagId) {
                const url = new URL(window.location.href);
                url.searchParams.set('tag_id', tagId);
                url.searchParams.delete('page');
                url.searchParams.delete('virtual_page');
                url.hash = 'transactions-table';
                window.location.assign(url);
            },

            clearTagFilter() {
                const url = new URL(window.location.href);
                url.searchParams.delete('tag_id');
                url.searchParams.delete('page');
                url.searchParams.delete('virtual_page');
                url.hash = 'transactions-table';
                window.location.assign(url);
            },

            submitIfNotCustom() {
                if (this.period !== 'custom') {
                    this.submit();
                }
            },

            submit() {
                this.$root.querySelector('form').submit();
            },

            async initCharts() {
                if (!this.$root.offsetParent || !this.$refs.chartSankey) {
                    return;
                }

                this.echarts = await window.loadEcharts();
                this.renderSankey();
            },

            destroy() {
                window.removeEventListener('resize', this.resizeHandler);
                this.sankeyChart?.dispose();
            },

            renderSankey() {
                this.sankeyChart = this.echarts.init(this.$refs.chartSankey);
                const data = @json($sankey);

                this.sankeyChart.setOption({
                    tooltip: { trigger: 'item', triggerOn: 'mousemove' },
                    series: [{
                        type: 'sankey',
                        data: data.nodes,
                        links: data.links,
                        emphasis: { focus: 'adjacency' },
                        lineStyle: { color: 'gradient', curveness: 0.5 },
                        label: { color: 'rgba(0,0,0,0.7)', fontFamily: 'sans-serif' }
                    }]
                });

                this.resizeHandler = () => this.sankeyChart?.resize();
                window.addEventListener('resize', this.resizeHandler);
            }
        }));
    });
</script>
</x-layouts.financial>
