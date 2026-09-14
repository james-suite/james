<x-card class="w-full mb-6" x-data="netWorthChart()" x-init="initChart()">
    <!-- Header: Title and value + Periods -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
        <div>
            <h3 class="text-sm font-medium text-gray-500 mb-1">Evolução do Saldo</h3>
            <div class="text-3xl font-bold text-gray-900" x-text="currentFormattedValue">
                R$ 0,00
            </div>
            <!-- Diff subtitle -->
            <div class="text-sm mt-1 flex items-center gap-1 min-h-[20px]" :class="diff >= 0 ? 'text-emerald-500' : 'text-red-500'">
                <template x-if="!loading">
                    <div class="flex items-center gap-1">
                        <span x-text="diff >= 0 ? '↑' : '↓'"></span>
                        <span x-text="diffFormatted"></span>
                        <span class="text-gray-400 font-normal">no período</span>
                    </div>
                </template>
            </div>
        </div>
        
        <!-- Period Selector -->
        <div class="mt-4 sm:mt-0 flex bg-gray-50 rounded-lg p-1 border border-gray-200">
            <template x-for="p in periods" :key="p">
                <button 
                    @click="setPeriod(p)"
                    class="px-3 py-1 text-sm font-medium rounded-md transition-colors duration-200 uppercase cursor-pointer"
                    :class="period === p ? 'bg-white text-gray-900 shadow-sm border border-gray-200' : 'text-gray-500 hover:text-gray-700'"
                    x-text="p">
                </button>
            </template>
        </div>
    </div>

    <!-- Chart Container -->
    <div x-ref="chartWrapper">
        <x-finance.evolution-chart-base heightClass="h-[300px]" deferred />
    </div>
</x-card>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('netWorthChart', () => ({
        period: '1m',
        periods: ['1m', '3m', '6m', '1y', 'all'],
        loading: true,
        chartData: null,
        currentValue: 0,
        diff: 0,
        
        get currentFormattedValue() {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(this.currentValue);
        },
        
        get diffFormatted() {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Math.abs(this.diff));
        },

        initChart() {
            this.fetchData();
        },

        setPeriod(p) {
            if (this.period === p) return;
            this.period = p;
            this.fetchData();
        },

        async fetchData() {
            this.loading = true;
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const includeInvestments = urlParams.get('include_investments');
                
                let url = `/financial/dashboard/chart-data?period=${this.period}`;
                if (includeInvestments !== null) {
                    url += `&include_investments=${includeInvestments}`;
                }

                const response = await fetch(url);
                const data = await response.json();
                this.updateChart(data);
            } catch (error) {
                console.error("Error fetching chart data", error);
            } finally {
                this.loading = false;
            }
        },

        updateChart(data) {
            if (!data || data.length === 0) return;

            const values = data.map(item => item.value);

            // Find index of today's date
            const todayRaw = new Date();
            const todayYMD = todayRaw.getFullYear() + '-' + String(todayRaw.getMonth() + 1).padStart(2, '0') + '-' + String(todayRaw.getDate()).padStart(2, '0');
            
            let todayIndex = data.findIndex(item => item.date >= todayYMD);
            if (todayIndex === -1) {
                todayIndex = data.length > 0 ? data.length - 1 : 0;
            }

            this.currentValue = values[todayIndex !== -1 ? todayIndex : values.length - 1] || values[values.length - 1] || 0;
            const startValue = values[0] || 0;
            this.diff = this.currentValue - startValue;

            // Call render on the child evolution-chart-base component
            this.$nextTick(() => {
                const chartEl = this.$refs.chartWrapper?.querySelector('[x-data^="evolutionChartBase"]');
                if (chartEl) {
                    Alpine.$data(chartEl).render(data);
                }
            });
        }
    }));
});
</script>
