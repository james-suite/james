@props([
    'color' => 'default',
    'href' => null,
    'type' => 'button',
    'icon' => '',
])

@php
    $baseClasses =
        'button-scale cursor-pointer inline-flex items-center justify-center font-semibold px-3 py-2 min-h-11 text-base lg:text-sm [&>svg]:size-5 lg:[&>svg]:size-4 rounded-lg disabled:opacity-75 disabled:cursor-default gap-1.5 lg:gap-1';

    $colorClasses = match ($color) {
        'red' => 'bg-red-500 hover:bg-red-700 text-white border-transparent',
        'accent' => 'bg-accent hover:bg-[color-mix(in_srgb,var(--color-accent),#000_10%)] text-white border-transparent',
        'outline' => 'bg-white border border-neutral-200 hover:border-neutral-300 text-neutral-700 hover:bg-neutral-200 hover:text-neutral-900',
        'danger-outline' => 'bg-white text-red-500 hover:text-red-600 border border-red-200 hover:border-red-300 hover:bg-red-50 interaction-delete',
        'ghost' => 'bg-transparent text-neutral-500 hover:text-neutral-900 hover:bg-neutral-100 border-transparent',
        'danger-ghost' => 'bg-transparent text-red-400 hover:text-red-600 hover:bg-red-50 border-transparent interaction-delete',
        'accent-ghost' => 'bg-transparent text-accent hover:text-[color-mix(in_srgb,var(--color-accent),#000_10%)] hover:bg-accent/10 border-transparent',
        'none' => '',
        default => 'bg-neutral-800 hover:bg-neutral-700 text-white border border-black/10',
    };

    $shadowClasses = match ($color) {
        'none', 'outline', 'danger-outline', 'ghost', 'danger-ghost', 'accent-ghost' => '',
        default => 'shadow-[inset_0px_1px_rgba(255,255,255,0.5)]',
    };

    $finalClasses = implode(' ', [$baseClasses, $colorClasses, $shadowClasses]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $finalClasses]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" :disabled="typeof loading !== 'undefined' && loading" {{ $attributes->merge(['class' => $finalClasses]) }}>
        {{-- O spinner é mostrado quando 'loading' é true --}}
        <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin" x-show="typeof loading !== 'undefined' && loading" style="display: none;" />

        {{-- O conteúdo original é mostrado quando 'loading' é false --}}
        <span x-show="typeof loading === 'undefined' || !loading" class="inline-flex items-center gap-1">
            {{ $slot }}
        </span>
    </button>
@endif
