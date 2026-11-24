@props(['fallback', 'text' => 'Voltar', 'icon' => 'heroicon-o-arrow-left'])

@php
    $safeBackUrl = app(\App\Support\SafeBackUrl::class);
    $effectiveFallback = $safeBackUrl->resolveFallback(
        $fallback,
        session(\App\Http\Middleware\RememberListingUrl::SESSION_KEY, []),
        url('/'),
    );
    $href = $safeBackUrl->resolve(
        url()->previous(),
        $effectiveFallback,
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
