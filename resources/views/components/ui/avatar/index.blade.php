@props([
    'model' => null,
    'size' => 'md',
    'icon' => null,
    'variant' => 'default',
    'radius' => 'md',
    'image' => null,
])

@php
    $sizeClasses = match($size) {
        'sm' => 'w-6 h-6 text-xs',
        'md' => 'w-8 h-8 text-sm',
        'lg' => 'w-12 h-12 text-lg',
        'xl' => 'w-16 h-16 text-2xl',
        '2xl' => 'w-24 h-24 text-4xl',
        '3xl' => 'w-32 h-32 text-5xl',
        default => 'w-8 h-8 text-sm',
    };

    $radiusClasses = match($radius) {
        'none' => 'rounded-none',
        'sm' => 'rounded-sm',
        'md' => 'rounded-md',
        'lg' => 'rounded-lg',
        'xl' => 'rounded-xl',
        '2xl' => 'rounded-2xl',
        'full' => 'rounded-full',
        default => 'rounded-md',
    };

    $variantClasses = match($variant) {
        'default' => 'bg-neutral-200 border-neutral-300 text-neutral-600',
        'white' => 'bg-white border-neutral-200 text-neutral-600 shadow-sm',
        'soft' => 'bg-neutral-100 border-transparent text-neutral-600',
        'accent' => 'bg-accent/10 border-accent/20 text-accent',
        'solid-accent' => 'bg-accent border-transparent text-white shadow-sm',
        default => 'bg-neutral-200 border-neutral-300 text-neutral-600',
    };

    $avatarUrl = $image ?? ($model->avatar ?? null);
    $initials = $model && method_exists($model, 'initials') ? $model->initials() : '';
    
    $imageClasses = match($variant) {
        'white' => 'border-neutral-200',
        default => 'border-[var(--color-accent)]',
    };
    
    $baseClasses = "shrink-0 flex items-center justify-center font-medium border {$sizeClasses} {$radiusClasses} {$variantClasses}";
    $imgBaseClasses = "shrink-0 object-cover border bg-neutral-200 {$sizeClasses} {$radiusClasses} {$imageClasses}";
@endphp

@if($icon)
    <div {{ $attributes->merge(['class' => $baseClasses]) }}>
        <x-dynamic-component :component="$icon" class="w-[65%] h-[65%]" />
    </div>
@elseif($avatarUrl)
    <img src="{{ $avatarUrl }}" alt="{{ $model->name ?? 'Avatar' }}" onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');" {{ $attributes->merge(['class' => $imgBaseClasses]) }}>
    <div {{ $attributes->merge(['class' => $baseClasses . ' hidden']) }} aria-hidden="true">
        @if(!empty($initials))
            {{ $initials }}
        @else
            <x-heroicon-o-user class="w-[65%] h-[65%]" />
        @endif
    </div>
@elseif(!empty($initials))
    <div {{ $attributes->merge(['class' => $baseClasses]) }}>
        {{ $initials }}
    </div>
@else
    <div {{ $attributes->merge(['class' => $baseClasses]) }}>
        <x-heroicon-o-user class="w-[65%] h-[65%]" />
    </div>
@endif
