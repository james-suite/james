@props([
    'tag' => 'div',
    'href' => null,
    'size' => null,
])

@php
    $tag = $href ? 'a' : $tag;
    $isInteractive = (bool) $href;

    $baseClasses = 'bg-white border border-neutral-200 transition-colors motion-duration-quick motion-ease-smooth-out';
    
    $sizeClasses = match ($size) {
        'sm' => 'p-3 sm:p-4 rounded-lg',
        default => 'p-4 sm:p-6 rounded-xl',
    };

    $interactiveClasses = $isInteractive 
        ? 'hover:border-neutral-300 hover:bg-neutral-50/50' 
        : '';

    $classes = trim("$baseClasses $sizeClasses $interactiveClasses");
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</{{ $tag }}>
