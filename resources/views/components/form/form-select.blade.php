@props([
    'label' => '',
    'name' => '',
    'labelClass' => '',
    'bag' => 'default',
])

@php
    $hasValidationError = $name && $errors->getBag($bag)->has($name);
    $describedBy = $hasValidationError ? $name . '-error' : null;
@endphp

<x-field>
    @if ($label)
        <x-label :for="$name" class="{{ $labelClass }}">
            {{ $label }}
        </x-label>
    @endif

    <x-select
        :name="$name"
        :has-error="$hasValidationError"
        @if ($hasValidationError) aria-invalid="true" aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes }}
    >
        {{ $slot }}
    </x-select>

    <x-error :name="$name" :bag="$bag" />
</x-field>
