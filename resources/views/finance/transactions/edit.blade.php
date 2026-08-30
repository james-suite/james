<x-layouts.financial>
    @php
        $defaultItems = $transaction->items->map(function ($i) {
            return [
                'id' => $i->id,
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_price' => number_format($i->unit_price, 2, '.', ''),
                'tags' => $i->tags->pluck('id')->toArray(),
                'primary_tag_id' => $i->tags->firstWhere('pivot.is_primary', true)?->id,
            ];
        })->toArray();
        $defaultTags = $transaction->tags->pluck('id')->toArray();
        $defaultPrimaryTag = $transaction->items->isEmpty()
            ? $transaction->tags->firstWhere('pivot.is_primary', true)?->id
            : null;
    @endphp
    <form action="{{ route('financial.transactions.update', $transaction->id) }}" method="POST" id="transaction-form" enctype="multipart/form-data" x-data="{
        type: {{ Js::from(old('type', $transaction->type)) }},
        targetType: {{ Js::from(old('targetType', $transaction->invoice ? 'card' : 'account')) }},
        amount: {{ Js::from(old('amount', number_format(abs($transaction->amount), 2, '.', ''))) }},
        date: {{ Js::from(old('date', $transaction->date->format('Y-m-d'))) }},
        items: {{ Js::from(array_values(old('items', $defaultItems))) }},
        itemKey: 0,
        init() {
            this.items = this.items.map((item) => this.withItemKey(item));
        },
        withItemKey(item) {
            return { ...item, _key: item._key ?? `item-${++this.itemKey}` };
        },
        addItem() {
            this.items.push(this.withItemKey({ description: '', quantity: 1, unit_price: '', tags: [] }));
        },
        removeItem(index) {
            this.items.splice(index, 1);
        },
        parseNumber(value) {
            let normalizedValue = value ? value.toString().replace(',', '.') : '0';
            return parseFloat(normalizedValue) || 0;
        },
        itemTotal(item) {
            return this.parseNumber(item.quantity) * this.parseNumber(item.unit_price);
        },
        get itemsTotal() {
            return this.items.reduce((total, item) => total + this.itemTotal(item), 0);
        },
        formatMoney(value) {
            let options = { minimumFractionDigits: 2, maximumFractionDigits: 2 };
            return value.toLocaleString('pt-BR', options);
        }
    }" x-effect="if (items.length > 0) amount = itemsTotal.toFixed(2); if (date) { let d = new Date(date + 'T00:00:00'); let t = new Date(); t.setHours(0,0,0,0); if (d > t) $dispatch('uncheck-posted-edit') }">
        @csrf
        @method('PUT')
        <input type="hidden" name="targetType" x-model="targetType">
        <input type="hidden" name="items_present" value="1">

        <div class="flex justify-between items-center mb-6">
            <x-breadcrumbs>
                <x-breadcrumbs.item href="{{ route('financial.transactions.index') }}">Transações</x-breadcrumbs.item>
                <x-breadcrumbs.item href="{{ route('financial.transactions.show', $transaction->id) }}">#{{ $transaction->id }}</x-breadcrumbs.item>
                <x-breadcrumbs.item>Editar</x-breadcrumbs.item>
            </x-breadcrumbs>
        </div>

        <x-page-header title="Editar Transação" mobileBottom>
            <x-form-actions fallback="{{ route('financial.transactions.show', $transaction->id) }}" form="transaction-form" submitText="Salvar Alterações" />
        </x-page-header>

        @include('finance.transactions.partials.form', ['transaction' => $transaction])

        <x-form-actions fallback="{{ route('financial.transactions.show', $transaction->id) }}" form="transaction-form" submitText="Salvar Alterações" mobile />
    </form>
</x-layouts.financial>
