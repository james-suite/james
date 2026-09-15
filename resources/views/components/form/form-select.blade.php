@props([
    'label' => '',
    'name' => '',
    'labelClass' => '',
    'bag' => 'default',
])

@php
    $hasValidationError = $name && $errors->getBag($bag)->has($name);
    $describedBy = $hasValidationError ? $name . '-error' : null;
    $attributes = $attributes->merge(array_filter([
        'aria-invalid' => $hasValidationError ? 'true' : null,
        'aria-describedby' => $describedBy,
    ]));
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
        {{ $attributes }}
    >
        {{ $slot }}
    </x-select>

    <x-error :name="$name" :bag="$bag" />
</x-field>
