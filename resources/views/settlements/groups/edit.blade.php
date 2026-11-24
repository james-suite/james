<x-layouts.app>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('settlements.index') }}">Acertos</x-breadcrumbs.item>
            <x-breadcrumbs.item>Editar Divisão</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header title="Editar Divisão de Conta" mobileBottom>
        <x-form-actions fallback="{{ route('settlements.index') }}" form="split-form" />
    </x-page-header>



    @php
        $existingTagIds = [];
        $existingPrimaryTagId = null;
        if ($settlementGroup->financialTransaction) {
            $myItem = $settlementGroup->financialTransaction->items->firstWhere('description', 'Minha Parte');
            if ($myItem) {
                $existingTagIds = $myItem->tags->pluck('id')->toArray();
                $existingPrimaryTagId = $myItem->tags->firstWhere('pivot.is_primary', true)?->id;
            }
        }
        
        $existingContacts = $settlementGroup->settlements->map(fn($s) => [
            'id' => $s->contact->id,
            'name' => $s->contact->name,
            'avatar_url' => $s->contact->avatar,
            'initials' => $s->contact->initials(),
            'amount' => number_format($s->amount, 2, '.', ''),
        ])->values();
        
        $existingMyAmount = $settlementGroup->total_amount - $settlementGroup->settlements->sum('amount');

        $alpineContacts = old('contacts') ? collect(old('contacts'))->map(fn($c, $i) => [
            'id' => $c['id'],
            'name' => $existingContacts[$i]['name'] ?? '',
            'avatar_url' => $existingContacts[$i]['avatar_url'] ?? null,
            'initials' => $existingContacts[$i]['initials'] ?? '',
            'amount' => $c['amount'] ?? '',
        ])->values() : $existingContacts;
    @endphp

    <div class="mt-6">
        <form action="{{ route('settlements.groups.update', $settlementGroup) }}" method="POST" enctype="multipart/form-data" id="split-form" x-data="{
            mode: {{ Js::from(old('mode', $settlementGroup->mode)) }},
            includeMe: {{ Js::from(filter_var(old('include_me', $existingMyAmount > 0), FILTER_VALIDATE_BOOLEAN)) }},
            totalAmount: {{ Js::from(old('total_amount', number_format($settlementGroup->total_amount, 2, '.', ''))) }},
            myAmount: {{ Js::from(old('my_amount', number_format($existingMyAmount, 2, '.', ''))) }},
            createTransaction: {{ Js::from(old('create_transaction', $settlementGroup->financial_transaction_id ? 'true' : 'false') == '1' || old('create_transaction', $settlementGroup->financial_transaction_id ? 'true' : 'false') === 'true') }},
            targetType: {{ Js::from(old('targetType', optional($settlementGroup->financialTransaction)->invoice ? 'card' : 'account')) }},
            contacts: {{ Js::from($alpineContacts) }},

            get totalPeople() {
                return this.mode === 'equal' && !this.includeMe
                    ? this.contacts.length
                    : this.contacts.length + 1;
            },

            get sharePerPerson() {
                let total = parseFloat(this.totalAmount) || 0;
                if (this.totalPeople <= 0 || total <= 0) return 0;
                return Math.floor((total * 100) / this.totalPeople) / 100;
            },

            get calculatedMyAmount() {
                if (this.mode === 'equal') {
                    if (!this.includeMe) return 0;
                    let total = parseFloat(this.totalAmount) || 0;
                    let contactsShare = this.sharePerPerson * this.contacts.length;
                    return Math.round((total - contactsShare) * 100) / 100;
                }
                return parseFloat(this.myAmount) || 0;
            },

            get calculatedTotal() {
                if (this.mode === 'exact') {
                    let sum = parseFloat(this.myAmount) || 0;
                    this.contacts.forEach(c => {
                        sum += parseFloat(c.amount) || 0;
                    });
                    return Math.round(sum * 100) / 100;
                }
                return parseFloat(this.totalAmount) || 0;
            },

            formatMoney(value) {
                return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
            },

            init() {
                this.$watch('mode', () => {
                    if (this.mode === 'equal') {
                        this.recalcEqualShares();
                    }
                });
                this.$watch('includeMe', () => {
                    if (this.mode === 'equal') {
                        this.recalcEqualShares();
                    }
                });
                this.$watch('totalAmount', () => {
                    if (this.mode === 'equal') {
                        this.recalcEqualShares();
                    }
                });
            },

            recalcEqualShares() {
                let total = parseFloat(this.totalAmount) || 0;
                if (total <= 0 || this.totalPeople <= 0) {
                    this.contacts.forEach(c => c.amount = '');
                    this.myAmount = '';
                    return;
                }
                if (this.includeMe) {
                    let share = this.sharePerPerson;
                    this.contacts.forEach(c => c.amount = share.toFixed(2));
                    this.myAmount = this.calculatedMyAmount.toFixed(2);
                    return;
                }
                let totalCents = Math.round(total * 100);
                let baseCents = Math.floor(totalCents / this.contacts.length);
                let remainderCents = totalCents % this.contacts.length;
                this.contacts.forEach((c, index) => {
                    c.amount = ((baseCents + (index < remainderCents ? 1 : 0)) / 100).toFixed(2);
                });
                this.myAmount = '0.00';
            }
        }">
            @csrf
            @method('PUT')
            @include('settlements.groups.partials.form')
        </form>
    </div>

    <x-form-actions fallback="{{ route('settlements.index') }}" form="split-form" mobile />
</x-layouts.app>
