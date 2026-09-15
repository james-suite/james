<x-layouts.financial>
    <x-page-header title="Dashboard Financeiro" :action="route('financial.transactions.create')" actionText="Nova Transação" icon="heroicon-o-plus">
        <form method="GET" action="{{ route('financial.dashboard') }}" class="flex items-center bg-white border border-neutral-200 px-3 min-h-11 rounded-lg shadow-sm" x-data x-ref="filterForm">
            <div @click="setTimeout(() => $refs.filterForm.submit(), 150)">
                <x-switch name="include_investments" :checked="$includeInvestments" label="Investimentos" color="accent" />
            </div>
        </form>
    </x-page-header>

    <!-- 1. Linha de Destaque: Os Grandes Números -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-6">
        <x-finance.kpi-card
            title="Total de Despesas"
            :value="formatCurrency($kpi['expense'])"
            icon="heroicon-o-arrow-trending-down"
            color="red"
            :href="route('financial.transactions.index', ['type' => 'expense'])"
        />

        <x-finance.kpi-card
            title="Total de Receitas"
            :value="formatCurrency($kpi['income'])"
            icon="heroicon-o-arrow-trending-up"
            color="green"
            :href="route('financial.transactions.index', ['type' => 'income'])"
        />

        <x-finance.kpi-card
            title="Saldo Líquido"
            :value="formatCurrency($kpi['netBalance'])"
            icon="heroicon-o-building-library"
            :color="$kpi['netBalance'] >= 0 ? 'green' : 'red'"
        />

        <x-finance.kpi-card 
            title="Saldo Atual" 
            :value="formatCurrency($kpi['currentBalance'])" 
            icon="heroicon-o-scale" 
            :color="$kpi['currentBalance'] >= 0 ? 'green' : 'red'" 
            :href="route('financial.transactions.index', ['status' => 'posted'])"
        />
    </div>

    <!-- Gráfico de Evolução do Saldo (Net Worth) -->
    <div class="hidden lg:block">
        <x-financial.net-worth-chart />
    </div>

    <!-- 2. Previsibilidade e Saldos -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-8 mt-8 items-stretch">
        
        <!-- Previsibilidade de Caixa -->
        <div class="lg:col-span-1 flex flex-col h-full">
            <x-card class="bg-white rounded-xl border border-gray-100 shadow-sm h-full flex flex-col">
                <h3 class="text-lg font-bold text-neutral-900 mb-4">Projeção de Caixa</h3>
                
                <div class="flex-1 flex flex-col justify-center gap-2">
                    <!-- Mês Atual -->
                    <div class="bg-neutral-50/70 rounded-xl p-5 border border-neutral-100 relative overflow-hidden transition-all hover:shadow-sm">
                        <div class="absolute right-0 top-0 h-full w-1/3 bg-linear-to-l {{ $projections['currentMonth'] >= 0 ? 'from-green-50' : 'from-red-50' }} to-transparent opacity-50"></div>
                        <div class="relative z-10 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-neutral-500 flex items-center gap-1.5">
                                    <x-heroicon-s-calendar class="w-4 h-4 text-neutral-400" /> Fim do Mês Atual
                                </p>
                                <p class="text-2xl font-bold mt-1 {{ $projections['currentMonth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ formatCurrency($projections['currentMonth']) }}
                                </p>
                            </div>
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shadow-sm {{ $projections['currentMonth'] >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                                @if($projections['currentMonth'] >= 0)
                                    <x-heroicon-o-arrow-trending-up class="w-6 h-6" />
                                @else
                                    <x-heroicon-o-arrow-trending-down class="w-6 h-6" />
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Próximo Mês -->
                    <div class="bg-neutral-50/70 rounded-xl p-5 border border-neutral-100 relative overflow-hidden transition-all hover:shadow-sm">
                        <div class="absolute right-0 top-0 h-full w-1/3 bg-linear-to-l {{ $projections['nextMonth'] >= 0 ? 'from-green-50' : 'from-red-50' }} to-transparent opacity-50"></div>
                        <div class="relative z-10 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-neutral-500 flex items-center gap-1.5">
                                    <x-heroicon-s-calendar class="w-4 h-4 text-neutral-400" /> Fim do Próximo Mês
                                </p>
                                <p class="text-2xl font-bold mt-1 {{ $projections['nextMonth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ formatCurrency($projections['nextMonth']) }}
                                </p>
                            </div>
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shadow-sm {{ $projections['nextMonth'] >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                                @if($projections['nextMonth'] >= 0)
                                    <x-heroicon-o-arrow-trending-up class="w-6 h-6" />
                                @else
                                    <x-heroicon-o-arrow-trending-down class="w-6 h-6" />
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Dois Meses à Frente -->
                    <div class="bg-neutral-50/70 rounded-xl p-5 border border-neutral-100 relative overflow-hidden transition-all hover:shadow-sm">
                        <div class="absolute right-0 top-0 h-full w-1/3 bg-linear-to-l {{ $projections['afterNextMonth'] >= 0 ? 'from-green-50' : 'from-red-50' }} to-transparent opacity-50"></div>
                        <div class="relative z-10 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-neutral-500 flex items-center gap-1.5">
                                    <x-heroicon-s-calendar class="w-4 h-4 text-neutral-400" /> Fim de {{ formatMonthYear(now()->addMonths(2)) }}
                                </p>
                                <p class="text-2xl font-bold mt-1 {{ $projections['afterNextMonth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ formatCurrency($projections['afterNextMonth']) }}
                                </p>
                            </div>
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shadow-sm {{ $projections['afterNextMonth'] >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                                @if($projections['afterNextMonth'] >= 0)
                                    <x-heroicon-o-arrow-trending-up class="w-6 h-6" />
                                @else
                                    <x-heroicon-o-arrow-trending-down class="w-6 h-6" />
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Lado Direito (lg:col-span-1): "Saldos por Conta" -->
        <div class="hidden lg:flex lg:col-span-1 flex-col h-full">
            <x-card class="bg-white rounded-xl border border-gray-100 shadow-sm h-full flex flex-col">
                <h3 class="text-lg font-bold text-neutral-900 mb-4">Saldos por Conta</h3>
                <x-finance.account-balances-chart :chartData="$accountBalancesChart" />
            </x-card>
        </div>

        <!-- Top 5 Despesas -->
        <div class="lg:col-span-1 flex flex-col h-full">
            <x-card class="bg-white rounded-xl border border-gray-100 shadow-sm h-full flex flex-col">
                <h3 class="text-lg font-bold text-neutral-900 mb-4">Top Despesas (30 Dias)</h3>
                @if(count($topExpenseTags) > 0)
                    <div class="space-y-6 flex-1 mt-2">
                        @foreach($topExpenseTags as $index => $tag)
                            <x-finance.tag-list-item :index="$index + 1" :item="$tag" type="expense" :showBar="true" />
                        @endforeach
                    </div>
                @else
                    <div class="w-full flex-1 min-h-48 flex flex-col items-center justify-center text-neutral-500">
                        <x-heroicon-o-tag class="w-12 h-12 mb-2 text-neutral-300" />
                        <p class="text-sm">Nenhuma despesa registrada.</p>
                    </div>
                @endif
            </x-card>
        </div>
    </div>

    <!-- 4. Cartões de Crédito -->
    @if($cardsWidget->isNotEmpty())
        <div class="mb-8">
            <h3 class="text-lg font-bold text-neutral-900 mb-4">Cartões de Crédito</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($cardsWidget as $card)
                    <x-finance.credit-card :card="$card" />
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-8 mt-8">
        
        <!-- Radar -->
        <div class="lg:col-span-3">
            <div class="flex items-center gap-2 mb-4">
                <h3 class="text-lg font-bold text-neutral-900">Próximo Mês</h3>
                <span class="text-sm font-medium text-neutral-500 bg-neutral-100 px-2 py-0.5 rounded-md">até {{ now()->addMonthNoOverflow()->format('d/m') }}</span>
            </div>
            <x-finance.transaction-table :transactions="$radar" />
        </div>


    </div>

    <!-- 4. Últimas Transações -->
    @if($recentTransactions->isNotEmpty())
        <div class="flex justify-between items-center mb-4 mt-8">
            <h3 class="text-lg font-bold text-neutral-900">Últimas Transações</h3>
            <a href="{{ route('financial.transactions.index') }}" class="t-learn text-sm font-medium text-brand-600 hover:text-brand-700 flex items-center gap-1">
                Ver todas <x-heroicon-m-arrow-right class="t-learn-chevron size-4" />
            </a>
        </div>

        <x-finance.transaction-table :transactions="$recentTransactions" class="lg:mb-8" />
    @endif

    <p class="text-xs text-neutral-400 text-center mt-8 lg:hidden">
        Gráficos e relatórios detalhados estão disponíveis na versão desktop.
    </p>
</x-layouts.financial>
