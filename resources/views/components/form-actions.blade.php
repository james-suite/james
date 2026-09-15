@props([
    'fallback', 
    'form', 
    'submitText' => 'Salvar', 
    'mobile' => false
])

@if ($mobile)
    {{-- Botões mobile (final da página) --}}
    <div
        class="flex md:hidden items-center justify-between mt-6"
        data-form-id="{{ $form }}"
        x-data="{ loading: false }"
        x-on:submit.window="if ($event.target.id === '{{ $form }}') loading = true"
    >
        <x-back-button :fallback="$fallback" text="Cancelar" x-bind:class="loading && 'pointer-events-none opacity-50'" />

        <x-button type="submit" :form="$form" x-bind:aria-busy="loading">
            <x-heroicon-o-check class="size-4" />
            {{ $submitText }}
        </x-button>
    </div>
@else
    {{-- Botões desktop (dentro do header) --}}
    <div
        class="contents"
        data-form-id="{{ $form }}"
        x-data="{ loading: false }"
        x-on:submit.window="if ($event.target.id === '{{ $form }}') loading = true"
    >
        <x-back-button :fallback="$fallback" text="Cancelar" x-bind:class="loading && 'pointer-events-none opacity-50'" />

        <x-button type="submit" :form="$form" x-bind:aria-busy="loading">
            <x-heroicon-o-check class="size-4" />
            {{ $submitText }}
        </x-button>
    </div>
@endif
