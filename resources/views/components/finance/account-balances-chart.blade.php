@props(['chartData' => []])

@if(count($chartData) > 0)
    <div 
        class="w-full flex-1 relative min-h-64"
        x-data="{
            chart: null,
            resizeHandler: null,
            async initChart() {
                if (!this.$refs.chartContainer.offsetParent) {
                    return;
                }

                const echarts = await window.loadEcharts();
                this.chart = echarts.init(this.$refs.chartContainer);
                const data = {{ json_encode($chartData) }};
                
                const total = data.reduce((acc, item) => acc + item.value, 0);
                const formattedTotal = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(total);
                
                const option = {
                    color: [
                        '#4F46E5', '#10B981', '#F59E0B', '#EC4899', '#8B5CF6', '#06B6D4', '#F43F5E',
                    ],
                    tooltip: {
                        trigger: 'item',
                        formatter: function (params) {
                            const val = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(params.value);
                            return `${params.name}<br/><strong>${val}</strong> (${params.percent}%)`;
                        },
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        borderColor: '#e5e7eb',
                        textStyle: { color: '#374151' },
                        extraCssText: 'box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-radius: 8px;'
                    },
                    graphic: {
                        type: 'text',
                        left: 'center',
                        top: 'center',
                        style: {
                            text: 'Total\n' + formattedTotal,
                            textAlign: 'center',
                            fill: '#4b5563',
                            fontSize: 14,
                            fontWeight: 'bold'
                        }
                    },
                    series: [
                        {
                            name: 'Saldo',
                            type: 'pie',
                            radius: ['55%', '75%'], 
                            minAngle: 15, 
                            itemStyle: {
                                borderRadius: 8,
                                borderColor: '#ffffff',
                                borderWidth: 3,
                                shadowBlur: 10,
                                shadowColor: 'rgba(0, 0, 0, 0.05)',
                                shadowOffsetX: 0,
                                shadowOffsetY: 4
                            },
                            label: { show: false },
                            data: data
                        }
                    ]
                };
                
                this.chart.setOption(option);
                this.resizeHandler = () => this.chart?.resize();
                window.addEventListener('resize', this.resizeHandler);
            },
            destroy() {
                window.removeEventListener('resize', this.resizeHandler);
                this.chart?.dispose();
            }
        }"
        x-init="initChart"
    >
        <div x-ref="chartContainer" class="w-full h-full absolute inset-0"></div>
    </div>
@else
    <div class="w-full flex-1 min-h-64 flex flex-col items-center justify-center text-neutral-500">
        <x-heroicon-o-building-library class="w-12 h-12 mb-2 text-neutral-300" />
        <p class="text-sm">Nenhuma conta com saldo positivo.</p>
    </div>
@endif
