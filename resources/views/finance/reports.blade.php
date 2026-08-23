<x-layouts.financial>
    <x-page-header title="Relatórios Financeiros" icon="heroicon-o-chart-pie"></x-page-header>

    <div class="lg:hidden">
        <x-card class="mx-auto max-w-md text-center">
            <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-accent/10 text-accent">
                <x-heroicon-o-computer-desktop class="size-7" />
            </div>
            <h2 class="mt-5 text-lg font-bold text-neutral-900">Relatórios disponíveis no computador</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-500">
                Para visualizar os gráficos e analisar os relatórios financeiros, acesse esta página pelo computador.
            </p>
            <x-back-button fallback="{{ route('financial.dashboard') }}" class="mt-6 w-full justify-center" />
        </x-card>
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
            <div class="relative w-full h-[400px]">
                <div class="w-full h-full" x-ref="chartSankey"></div>
            </div>
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

            initCharts() {
                if (!window.echarts) {
                    console.error('ECharts not loaded.');
                    return;
                }

                this.renderSankey();
            },

            renderSankey() {
                const chart = window.echarts.init(this.$refs.chartSankey);
                const data = @json($sankey);

                chart.setOption({
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

                window.addEventListener('resize', () => chart.resize());
            }
        }));
    });
</script>
</x-layouts.financial>
