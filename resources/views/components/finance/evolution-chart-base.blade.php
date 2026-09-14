@props([
    'data' => null,
    'incomeLabel' => 'Receita',
    'expenseLabel' => 'Despesa',
    'heightClass' => 'h-[300px]',
])

<div class="relative w-full {{ $heightClass }}" x-data="evolutionChartBase({
    data: {{ $data ? $data : 'null' }},
    incomeLabel: '{{ $incomeLabel }}',
    expenseLabel: '{{ $expenseLabel }}'
})" x-init="initChart()">
    <div x-ref="chartContainer" class="w-full h-full"></div>
</div>

@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('evolutionChartBase', (config) => ({
        chartInstance: null,
        resizeHandler: null,
        data: config.data,
        incomeLabel: config.incomeLabel,
        expenseLabel: config.expenseLabel,
        
        async initChart() {
            if (!this.$refs.chartContainer.offsetParent) {
                return;
            }

            const echarts = await window.loadEcharts();
            this.chartInstance = echarts.init(this.$refs.chartContainer);
            
            this.resizeHandler = () => {
                if (this.chartInstance) {
                    this.chartInstance.resize();
                }
            };
            window.addEventListener('resize', this.resizeHandler);

            if (this.data) {
                this.render(this.data);
            }
        },

        destroy() {
            window.removeEventListener('resize', this.resizeHandler);
            this.chartInstance?.dispose();
        },

        updateChart(newData) {
            this.data = newData;
            this.render(this.data);
        },

        render(data) {
            this.data = data;
            if (!this.chartInstance) {
                return;
            }

            if (!data || data.length === 0) {
                this.chartInstance.clear();
                return;
            }

            const dates = data.map(item => {
                const parts = String(item.date).split('-');
                if (parts.length === 1) return parts[0];
                if (parts.length === 2) return parts[1] + '/' + parts[0];
                const d = new Date(parts[0], parts[1] - 1, parts[2]);
                return d.toLocaleDateString('pt-BR', { 
                    month: 'short', 
                    day: 'numeric'
                });
            });
            const values = data.map(item => item.value);

            // Find index of today's date
            const todayRaw = new Date();
            const todayYMD = todayRaw.getFullYear() + '-' + String(todayRaw.getMonth() + 1).padStart(2, '0') + '-' + String(todayRaw.getDate()).padStart(2, '0');
            
            let todayIndex = data.findIndex(item => item.date >= todayYMD);
            if (todayIndex === -1) {
                todayIndex = data.length > 0 ? data.length - 1 : 0;
            }

            const currentValue = values[todayIndex !== -1 ? todayIndex : values.length - 1] || values[values.length - 1] || 0;
            const mainColor = currentValue >= 0 ? '#10b981' : '#ef4444'; // Emerald or Red

            const fmt = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

            const option = {
                grid: {
                    top: 20,
                    right: 20,
                    bottom: 20,
                    left: 20,
                    containLabel: true
                },
                tooltip: {
                    trigger: 'axis',
                    backgroundColor: '#ffffff',
                    padding: [12, 16],
                    textStyle: {
                        color: '#1f2937',
                        fontSize: 14,
                        fontFamily: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'
                    },
                    extraCssText: 'box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border-radius: 0.5rem; border: 1px solid #f3f4f6;',
                    formatter: (params) => {
                        const item = params[0];
                        const date = item.name;
                        const dataIndex = item.dataIndex;
                        const val = fmt(item.value);
                        
                        const inc = fmt(data[dataIndex].income);
                        const exp = fmt(data[dataIndex].expense);
                        const futureExpRaw = data[dataIndex].future_expense || 0;
                        const futureExp = fmt(futureExpRaw);

                        let tooltip = `<div class="font-medium text-gray-500 text-xs mb-2 uppercase">${date}</div>
                                <div class="font-bold text-gray-900 text-sm mb-2">${val}</div>
                                <div class="flex justify-between items-center gap-4 text-xs">
                                    <span class="text-emerald-600 flex items-center gap-1">↑ ${this.incomeLabel}</span>
                                    <span class="font-medium text-emerald-600">${inc}</span>
                                </div>
                                <div class="flex justify-between items-center gap-4 text-xs mt-1">
                                    <span class="text-red-600 flex items-center gap-1">↓ ${this.expenseLabel}</span>
                                    <span class="font-medium text-red-600">${exp}</span>
                                </div>`;

                        if (futureExpRaw > 0) {
                            tooltip += `<div class="flex justify-between items-center gap-4 text-xs mt-2 pt-2 border-t border-gray-100">
                                    <span class="text-amber-600 flex items-center gap-1">Fatura Futura</span>
                                    <span class="font-medium text-amber-600">${futureExp}</span>
                                </div>`;
                        }

                        return tooltip;
                    },
                    axisPointer: {
                        lineStyle: { color: '#d1d5db', type: 'dashed' }
                    }
                },
                xAxis: {
                    type: 'category',
                    boundaryGap: false,
                    data: dates,
                    axisLine: { show: false },
                    axisTick: { show: false },
                    axisLabel: {
                        color: '#9ca3af',
                        fontSize: 11,
                        margin: 16,
                        showMaxLabel: true,
                        showMinLabel: true,
                        align: 'center'
                    },
                    splitLine: { show: false }
                },
                yAxis: {
                    type: 'value',
                    show: true,
                    min: 'dataMin',
                    scale: true,
                    axisLabel: {
                        color: '#9ca3af',
                        fontSize: 11,
                        formatter: (value) => new Intl.NumberFormat('pt-BR', { notation: 'compact', compactDisplay: 'short' }).format(value)
                    },
                    splitLine: { 
                        show: true,
                        lineStyle: { color: '#f3f4f6', type: 'dashed' }
                    }
                },
                series: [
                    {
                        data: values,
                        type: 'line',
                        smooth: false,
                        showSymbol: false,
                        symbolSize: 8,
                        itemStyle: {
                            color: mainColor,
                            borderWidth: 2
                        },
                        lineStyle: {
                            color: mainColor,
                            width: 2
                        },
                        areaStyle: {
                            color: new window.echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                { offset: 0, color: mainColor + '33' },
                                { offset: 1, color: mainColor + '00' }
                            ])
                        },
                        markLine: {
                            data: [
                                { yAxis: 0 },
                                { 
                                    xAxis: todayIndex, 
                                    label: { 
                                        show: true, 
                                        formatter: 'Hoje', 
                                        position: 'insideStartTop', 
                                        color: '#9ca3af',
                                        fontSize: 10
                                    }, 
                                    lineStyle: { 
                                        color: '#9ca3af', 
                                        type: 'dashed',
                                        width: 1
                                    } 
                                }
                            ],
                            symbol: 'none',
                            lineStyle: { color: '#d1d5db', type: 'solid', width: 1 },
                            label: { show: false }
                        }
                    }
                ]
            };

            this.chartInstance.setOption(option);
        }
    }));
});
</script>
@endonce
