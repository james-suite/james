@props([
    'label' => '',
    'name' => '',
    'type' => 'text',
    'value' => '',
    'placeholder' => '',
    'viewable' => false,
    'labelClass' => '',
    'numeric' => false,
    'currency' => false,
    'allowNegative' => false,
    'bag' => 'default',
    'help' => null,
])

@php
    $hasValidationError = $name && $errors->getBag($bag)->has($name);
    $fieldId = $currency && $name ? $name . '_display' : $name;
    $describedBy = collect([
        $hasValidationError ? $name . '-error' : null,
        $help ? $name . '-help' : null,
    ])->filter()->implode(' ');
@endphp

<x-field>
    @if ($label)
        <x-label :for="$fieldId" class="{{ $labelClass }}">
            {{ $label }}
        </x-label>
    @endif

    <x-input
        :name="$name"
        :type="$type"
        :value="$value"
        :placeholder="$placeholder"
        :viewable="$viewable"
        :numeric="$numeric"
        :currency="$currency"
        :allow-negative="$allowNegative"
        :bag="$bag"
        :has-error="$hasValidationError"
        @if ($hasValidationError) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['help']) }}
    />

    @if ($help)
        <p id="{{ $name }}-help" class="mt-1.5 text-xs text-neutral-500">{{ $help }}</p>
    @endif

    <x-error :name="$name" :bag="$bag" />
</x-field>
