@props([
    'color' => 'default',
    'href' => null,
    'type' => 'button',
    'icon' => '',
])

@php
    $baseClasses =
        'button-scale cursor-pointer inline-flex items-center justify-center font-semibold px-3 py-2 min-h-11 text-base lg:text-sm [&>svg]:size-5 lg:[&>svg]:size-4 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed gap-1.5 lg:gap-1';

    $colorClasses = match ($color) {
        'red' => 'bg-red-600 not-disabled:hover:bg-red-700 text-white border-transparent',
        'success' => 'bg-green-600 not-disabled:hover:bg-green-700 text-white border-transparent',
        'accent' => 'bg-accent not-disabled:hover:bg-accent-hover text-neutral-950 border-transparent',
        'outline' => 'bg-white border border-neutral-200 not-disabled:hover:border-neutral-300 not-disabled:hover:bg-neutral-100 text-neutral-700 not-disabled:hover:text-neutral-950',
        'danger-outline' => 'bg-white text-red-600 not-disabled:hover:text-red-700 border border-red-200 not-disabled:hover:border-red-300 not-disabled:hover:bg-red-50',
        'ghost' => 'bg-transparent text-neutral-600 not-disabled:hover:text-neutral-900 not-disabled:hover:bg-neutral-100 border-transparent',
        'danger-ghost' => 'bg-transparent text-red-600 not-disabled:hover:text-red-700 not-disabled:hover:bg-red-50 border-transparent',
        'accent-ghost' => 'bg-transparent text-accent-ink not-disabled:hover:bg-accent/10 border-transparent',
        'none' => '',
        default => 'bg-neutral-800 not-disabled:hover:bg-neutral-700 text-white border border-transparent',
    };

    $shadowClasses = match ($color) {
        'none', 'outline', 'danger-outline', 'ghost', 'danger-ghost', 'accent-ghost' => '',
        'red', 'success', 'accent' => 'shadow-xs',
        default => 'button-default-surface',
    };

    $finalClasses = implode(' ', [$baseClasses, $colorClasses, $shadowClasses]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $finalClasses]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $finalClasses]) }} :disabled="typeof loading !== 'undefined' && loading">
        {{-- O spinner é mostrado quando 'loading' é true --}}
        <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin" style="display: none;" x-show="typeof loading !== 'undefined' && loading" />

        {{-- O conteúdo original é mostrado quando 'loading' é false --}}
        <span class="inline-flex items-center gap-1" x-show="typeof loading === 'undefined' || !loading">
            {{ $slot }}
        </span>
    </button>
@endif
