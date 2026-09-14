@props([
    'index',
    'item',
    'type' => 'expense', // 'expense', 'income', 'net'
    'showBar' => true,
    'filterable' => false,
])

@php
    $value = $item['value'] ?? 0;
    
    if ($type === 'expense') {
        $valueStr = '- ' . formatCurrency($value);
        $valueColor = 'text-red-600';
    } elseif ($type === 'income') {
        $valueStr = '+ ' . formatCurrency($value);
        $valueColor = 'text-green-600';
    } else { // net
        if ($value >= 0) {
            $valueStr = '+ ' . formatCurrency($value);
            $valueColor = 'text-green-600';
        } else {
            $valueStr = '- ' . formatCurrency(abs($value));
            $valueColor = 'text-red-600';
        }
    }
    
    $color = $item['color'] ?? '#9ca3af';
    $icon = $item['icon'] ?? 'heroicon-o-tag';
    $name = $item['name'] ?? 'Sem Categoria';
    $wrapperClasses = 'flex items-start gap-3 p-2 -mx-2 rounded-lg transition-colors';

    if ($filterable) {
        $wrapperClasses .= ' w-full cursor-pointer text-left hover:bg-neutral-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40';
    }
@endphp

@if($filterable)
    <button type="button" class="{{ $wrapperClasses }}" @click="filterByTag({{ $item['id'] ?? 0 }})">
@else
    <div class="{{ $wrapperClasses }}">
@endif
    <div class="shrink-0 w-6 h-6 rounded flex items-center justify-center text-xs font-bold" 
         style="background-color: {{ $color }}20; color: {{ $color }}">
        {{ $index }}
    </div>
    <div class="flex-1 min-w-0">
        <div class="flex justify-between items-center {{ $showBar ? 'mb-1' : '' }}">
            <div class="flex items-center gap-2 truncate">
                <x-dynamic-component :component="$icon" class="size-4 shrink-0" style="color: {{ $color }}" />
                <span class="font-semibold text-neutral-900 text-sm truncate">{{ $name }}</span>
            </div>
            <span class="font-bold text-sm {{ $valueColor }} shrink-0">{{ $valueStr }}</span>
        </div>
        
        @if($showBar && isset($item['percentage']))
            <div class="flex justify-between items-center mb-1.5">
                <span class="text-xs text-neutral-500">{{ $item['percentage'] }}% do total</span>
            </div>
            <div class="w-full bg-neutral-100 rounded-full h-2 overflow-hidden" x-data="{ width: 0 }" x-init="setTimeout(() => width = {{ $item['percentage'] }}, 100)">
                <div class="h-full rounded-full transition-all duration-1000 ease-out" 
                     :style="`width: ${width}%; background-color: {{ $color }}`"></div>
            </div>
        @endif
    </div>
@if($filterable)
    </button>
@else
    </div>
@endif
