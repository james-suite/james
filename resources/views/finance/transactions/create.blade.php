<x-layouts.financial>
    <form action="{{ route('financial.transactions.store') }}" method="POST" id="transaction-form" enctype="multipart/form-data" x-data="{
        mode: {{ Js::from(old('mode', 'single')) }},
        type: {{ Js::from(old('type', 'expense')) }},
        targetType: {{ Js::from(old('targetType', 'account')) }},
        amount: {{ Js::from(old('amount')) }},
        date: {{ Js::from(old('date', \Carbon\Carbon::today()->format('Y-m-d'))) }},
        items: {{ Js::from(array_values(old('items', []))) }},
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
    }" x-effect="if (items.length > 0) amount = itemsTotal.toFixed(2); if (date) { let d = new Date(date + 'T00:00:00'); let t = new Date(); t.setHours(0,0,0,0); if (d > t) $dispatch('uncheck-posted') }">
        @csrf
        <input type="hidden" name="targetType" x-model="targetType">

        <div class="flex justify-between items-center mb-6">
            <x-breadcrumbs>
                <x-breadcrumbs.item href="{{ route('financial.transactions.index') }}">Transações</x-breadcrumbs.item>
                <x-breadcrumbs.item>Nova Transação</x-breadcrumbs.item>
            </x-breadcrumbs>
        </div>

        <x-page-header title="Nova Transação" mobileBottom>
            <x-modal.trigger name="nfce-import-modal">
                <x-button type="button" color="outline" class="w-full sm:w-auto">
                    <x-heroicon-o-document-arrow-down class="size-4" />
                    <span>Importar NFC-e</span>
                </x-button>
            </x-modal.trigger>
            <x-form-actions fallback="{{ route('financial.transactions.index') }}" form="transaction-form" />
        </x-page-header>

        @include('finance.transactions.partials.form')

        <div class="flex md:hidden mt-6">
            <x-modal.trigger name="nfce-import-modal" class="w-full">
                <x-button type="button" color="outline" class="w-full">
                    <x-heroicon-o-document-arrow-down class="size-4" />
                    <span>Importar NFC-e</span>
                </x-button>
            </x-modal.trigger>
        </div>

        <x-form-actions fallback="{{ route('financial.transactions.index') }}" form="transaction-form" mobile />
    </form>

    <x-modal name="nfce-import-modal" title="Importar NFC-e" confirmVariant="none" size="lg">
        <form action="{{ route('financial.transactions.nfce.import') }}" method="POST" class="mt-4 space-y-4"
            x-data="nfceImport(@js(old('url', '')))"
            @modal-closed.window="if ($event.detail === 'nfce-import-modal') stopScanner()"
            @submit="if (loading) { $event.preventDefault(); return; } loading = true">
            @csrf

            <div class="space-y-4" x-show="!scanMode">
                <x-form-input
                    label="URL pública da NFC-e"
                    name="url"
                    type="url"
                    placeholder="https://..."
                    autocomplete="url"
                    x-model="url"
                />

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <x-button type="button" color="outline" class="w-full" @click="pasteUrl()">
                        <x-heroicon-o-clipboard-document class="size-4" />
                        Colar URL
                    </x-button>

                    <fieldset :disabled="scanStarting">
                        <x-button type="button" color="outline" class="w-full" @click="startScanner()">
                            <x-heroicon-o-camera class="size-4" x-show="!scanStarting" />
                            <x-heroicon-o-arrow-path class="size-4 animate-spin" style="display: none;" x-show="scanStarting" />
                            <span x-text="scanStarting ? 'Abrindo câmera...' : 'Ler QR Code'"></span>
                        </x-button>
                    </fieldset>
                </div>

                <p class="text-xs text-red-600" role="alert" x-show="pasteError" x-text="pasteError"></p>

                <p class="text-sm text-neutral-500">Cole a URL exibida no portal da nota fiscal ou leia o QR Code pela câmera.</p>
            </div>

            <div class="space-y-4" style="display: none;" x-show="scanMode">
                <p class="text-sm text-neutral-600">Aponte a câmera para o QR Code impresso na nota fiscal.</p>

                <div class="relative min-h-64 overflow-hidden rounded-xl bg-neutral-950">
                    <div id="nfce-qr-reader" class="min-h-64"></div>

                    <div class="absolute inset-0 flex items-center justify-center gap-2 bg-neutral-950 text-sm text-white"
                        x-show="scanStarting">
                        <x-heroicon-o-arrow-path class="size-5 animate-spin" />
                        Preparando a câmera...
                    </div>
                </div>

                <x-button type="button" color="outline" class="w-full" @click="stopScanner()">
                    <x-heroicon-o-x-mark class="size-4" />
                    Cancelar leitura
                </x-button>
            </div>

            <p class="text-sm text-red-600" role="alert" aria-live="assertive" x-show="scanError" x-text="scanError"></p>

            <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end" x-show="!scanMode">
                <x-button type="button" color="outline" @click="$dispatch('modal-close', 'nfce-import-modal')">
                    Cancelar
                </x-button>
                <x-button type="submit">
                    <x-heroicon-o-arrow-down-tray class="size-4" />
                    Enviar para importação
                </x-button>
            </div>
        </form>
    </x-modal>

    @if ($errors->has('url'))
        <div class="contents" x-data x-init="$dispatch('modal-open', 'nfce-import-modal')"></div>
    @endif
</x-layouts.financial>
