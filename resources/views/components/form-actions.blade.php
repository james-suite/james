@props([
    'fallback', 
    'form', 
    'submitText' => 'Salvar', 
    'mobile' => false
])

@if ($mobile)
    {{-- Botões mobile (final da página) --}}
    <div class="flex md:hidden items-center justify-between mt-6" x-data='{ loading: false, init() { const form = document.getElementById(@js($form)); if (form) { form.addEventListener("submit", () => { this.loading = true; }); } } }'>
        <x-back-button :fallback="$fallback" text="Cancelar" x-bind:class="loading && 'pointer-events-none opacity-50'" />

        <x-button type="submit" :form="$form" x-bind:aria-busy="loading">
            <x-heroicon-o-check class="size-4" />
            {{ $submitText }}
        </x-button>
    </div>
@else
    {{-- Botões desktop (dentro do header) --}}
    <div class="contents" x-data='{ loading: false, init() { const form = document.getElementById(@js($form)); if (form) { form.addEventListener("submit", () => { this.loading = true; }); } } }'>
        <x-back-button :fallback="$fallback" text="Cancelar" x-bind:class="loading && 'pointer-events-none opacity-50'" />

        <x-button type="submit" :form="$form" x-bind:aria-busy="loading">
            <x-heroicon-o-check class="size-4" />
            {{ $submitText }}
        </x-button>
    </div>
@endif
