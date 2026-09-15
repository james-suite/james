@props(['fallback', 'text' => 'Voltar', 'icon' => 'heroicon-o-arrow-left'])

@php
    $href = app(\App\Support\SafeBackUrl::class)->resolve(
        url()->previous(),
        $fallback,
        request()->fullUrl(),
        url('/'),
    );
@endphp

<x-button color="outline" href="{{ $href }}" {{ $attributes->merge(['class' => 'bg-white']) }}>
    @if($icon)
        <x-dynamic-component :component="$icon" class="size-4" />
    @endif
    <span>{{ $text }}</span>
</x-button>
