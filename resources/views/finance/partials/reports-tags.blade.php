<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Expenses -->
    <div>
        <h4 class="text-sm font-bold text-neutral-900 mb-4 uppercase tracking-wider flex items-center gap-2">
            <x-heroicon-s-arrow-trending-down class="size-4 text-red-500" />
            Despesas
        </h4>
        <div class="space-y-6">
            @forelse($expenses as $index => $item)
                <x-finance.tag-list-item :index="$index + 1" :item="$item" type="expense" :showBar="true" filterable />
            @empty
                <div class="text-sm text-neutral-500 py-4">Nenhuma despesa no período.</div>
            @endforelse
        </div>
    </div>

    <!-- Incomes -->
    <div>
        <h4 class="text-sm font-bold text-neutral-900 mb-4 uppercase tracking-wider flex items-center gap-2">
            <x-heroicon-s-arrow-trending-up class="size-4 text-emerald-500" />
            Receitas
        </h4>
        <div class="space-y-6">
            @forelse($incomes as $index => $item)
                <x-finance.tag-list-item :index="$index + 1" :item="$item" type="income" :showBar="true" filterable />
            @empty
                <div class="text-sm text-neutral-500 py-4">Nenhuma receita no período.</div>
            @endforelse
        </div>
    </div>
</div>
