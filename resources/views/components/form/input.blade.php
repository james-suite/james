@props([
    'name' => '',
    'type' => 'text',
    'value' => '',
    'placeholder' => '',
    'viewable' => false,
    'numeric' => false,
    'currency' => false,
    'allowNegative' => false,
    'bag' => 'default',
    'hasError' => false, // Override para forçar estado de erro
])

@php
    $baseClasses = 'h-11 w-full border appearance-none text-sm rounded-xl block py-2.5 px-4 bg-white disabled:shadow-none shadow-xs focus:shadow-lg text-neutral-700 disabled:text-neutral-400 placeholder-neutral-400 disabled:placeholder-neutral-400/70 outline-none focus:border-accent focus:ring-2 focus:ring-accent/40 transition-colors duration-300';
    $isError = $hasError || ($name && $errors->getBag($bag)->has($name));
    $errorClasses = $isError
        ? 'border-red-500 focus:border-red-500 focus:ring-red-400/30'
        : 'border-neutral-200';
    $classes = $baseClasses . ' ' . $errorClasses;
@endphp

<div class="relative w-full" 
    @if ($viewable) x-data="{ show: false }" @endif
    @if ($currency) x-data="{
        rawValue: '{{ $value }}',
        allowNegative: {{ $allowNegative ? 'true' : 'false' }},
        formatCurrency(val) {
            let num = Number(String(val ?? '').replace(',', '.'));
            if (!Number.isFinite(num)) num = 0;
            if (!this.allowNegative) num = Math.abs(num);

            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(num);
        },
        normalizeValue(val) {
            let input = String(val ?? '').trim();
            let digits = input.replace(/\D/g, '');
            if (digits === '') digits = '0';

            let cents = parseInt(digits, 10);
            let sign = this.allowNegative && input.includes('-') ? -1 : 1;

            return ((sign * cents) / 100).toFixed(2);
        },
        updateValue(e) {
            let floatVal = this.normalizeValue(e.target.value);
            if (this.rawValue !== floatVal) {
                this.rawValue = floatVal;
            }
            e.target.value = this.formatCurrency(floatVal);
        },
        init() {
            this.$watch('rawValue', (val) => {
                this.$refs.display.value = this.formatCurrency(val);
            });

            let parsed = Number(String(this.rawValue || 0).replace(',', '.'));
            if (!Number.isFinite(parsed)) parsed = 0;
            if (!this.allowNegative) parsed = Math.abs(parsed);

            let floatVal = parsed.toFixed(2);
            this.$refs.display.value = this.formatCurrency(floatVal);

            if (this.rawValue !== floatVal) {
                this.rawValue = floatVal;
            }
        }
    }" 
    x-modelable="rawValue" 
    {{ $attributes->only('x-model') }} 
    @endif>

    @if ($currency)
        <input type="hidden" @if ($name) name="{{ $name }}" @endif {{ $attributes->filter(fn($v, $k) => in_array($k, [':name', '::name', 'x-bind:name'])) }} x-model="rawValue">
        <input type="text" @if ($name) id="{{ $name }}_display" @endif inputmode="numeric" x-ref="display" @input="updateValue($event)" placeholder="{{ $placeholder }}" {{ $attributes->whereDoesntStartWith('x-model')->filter(fn($v, $k) => !in_array($k, [':name', '::name', 'x-bind:name']))->merge(['class' => $classes]) }} />
    @else
        <input
            @if ($viewable) :type="show ? 'text' : 'password'"
            @else type="{{ $numeric ? 'text' : $type }}" @endif
            @if ($name) id="{{ $name }}" name="{{ $name }}" @endif
            placeholder="{{ $placeholder }}"
            value="{{ $value }}" {{ $attributes->merge(['class' => $classes]) }}
            @if ($numeric) x-data
                @input="$event.target.value = $event.target.value.replace(/[^0-9.,]/g, '')"
                inputmode="numeric" @endif />
    @endif

    @if ($viewable)
        <div class="absolute top-0 bottom-0 flex items-center gap-x-1.5 pe-3 inset-e-0 text-xs text-neutral-400">
            <button type="button" x-on:click="show = !show" tabindex="-1"
                class="relative items-center font-medium justify-center gap-2 whitespace-nowrap disabled:opacity-75 disabled:cursor-default disabled:pointer-events-none h-8 text-sm rounded-md w-8 inline-flex -ms-1.5 -me-1.5 bg-transparent hover:bg-neutral-800/5 text-neutral-500 hover:text-neutral-800 transition-colors duration-300 cursor-pointer">
                {{-- Ícone de "escondido" (olho cortado) --}}
                <x-heroicon-o-eye-slash class="size-4" x-show="!show" />

                {{-- Ícone de "visível" (olho aberto) --}}
                <x-heroicon-o-eye class="size-4" style="display: none;" x-show="show" />
            </button>
        </div>
    @endif
</div>
