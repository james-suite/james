@props([
    'name',
    'label' => '',
    'value' => '',
    'placeholder' => '',
    'rows' => 6,
])

@php
    $baseClasses = 'w-full border border-neutral-200 appearance-none text-sm rounded-xl block py-2.5 px-4 bg-white disabled:shadow-none shadow-xs focus:shadow-lg text-neutral-700 disabled:text-neutral-400 placeholder-neutral-400 disabled:placeholder-neutral-400/70 outline-none focus:border-accent focus:ring-2 focus:ring-accent/40 transition-colors duration-300';
    $errorClasses = $errors->has($name) ? 'border-red-500 focus:border-red-500 focus:ring-red-400/30' : '';
@endphp

<x-card {{ $attributes->merge(['class' => 'mb-4 p-6']) }}>
    @if($label)
        <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-6">{{ $label }}</h3>
    @endif

    <div class="grid w-full items-center gap-1.5"
        x-data="{
            mde: null,
            async initEditor() {
                const EasyMDE = await window.loadEasyMDE();
                this.mde = new EasyMDE({ element: this.$refs.editor, forceSync: true, status: false, spellChecker: false });
            },
        }"
        x-init="initEditor()">
        <textarea
            x-ref="editor"
            id="{{ $name }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}"
            class="{{ $baseClasses }} {{ $errorClasses }}">{{ $value }}</textarea>
        <x-error name="{{ $name }}" />
    </div>
</x-card>
