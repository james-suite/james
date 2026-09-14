@props([
    'action' => '',
    'searchPlaceholder' => 'Buscar...',
    'searchName' => 'search',
    'searchValue' => request('search'),
    'filters' => ['search'],
    'showSearch' => true,
    'showMobileToggle' => true,
    'buttonClass' => 'sm:w-8 h-8',
    'align' => 'center',
])

@php
    $hasFilters = collect($filters)->contains(fn ($filter) => request()->filled($filter));
    $alignClass = match($align) {
        'end' => 'sm:items-end',
        'start' => 'sm:items-start',
        default => 'sm:items-center',
    };
@endphp

<form
    {{ $attributes->merge(['action' => $action, 'method' => 'GET', 'class' => "t-acc min-w-0 max-w-full flex flex-col sm:flex-row items-stretch {$alignClass} gap-0 sm:gap-1 mb-8 bg-white p-1 rounded-xl border border-neutral-200 shadow-sm w-full sm:w-fit transition-all focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/40"]) }}
    x-data="{ loading: false, expanded: @js(!$showMobileToggle) }"
    data-open="{{ $showMobileToggle ? 'false' : 'true' }}"
    x-bind:data-open="expanded"
    @submit="loading = true"
>
    <!-- Top Bar (Mobile + Desktop) -->
    <div class="flex min-w-0 items-center w-full sm:w-auto">
        @if($showSearch)
            <div class="relative min-w-0 flex-1 sm:w-80 flex items-center">
                <div class="absolute left-3 flex items-center pointer-events-none">
                    <x-heroicon-m-magnifying-glass class="h-4 w-4 text-neutral-400" />
                </div>
                <input type="text" name="{{ $searchName }}" value="{{ $searchValue }}" placeholder="{{ $searchPlaceholder }}"
                       class="w-full pl-9 pr-3 py-2 sm:py-1.5 bg-transparent border-0 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:ring-0">
            </div>
        @endif

        @if($showMobileToggle && ($slot->isNotEmpty() || $hasFilters))
            <button
                type="button"
                class="sm:hidden inline-flex shrink-0 items-center px-3 py-2 text-neutral-400 hover:text-neutral-600 focus:outline-none"
                x-bind:class="{ 'bg-neutral-100 rounded-lg text-neutral-700': expanded }"
                x-bind:aria-expanded="expanded"
                @click="expanded = !expanded"
            >
                <x-heroicon-o-chevron-down class="t-acc-chevron size-4" aria-hidden="true" />
                <span class="sr-only">Mostrar filtros</span>
            </button>
        @endif

        @if($slot->isEmpty() && !$hasFilters)
            <div class="sm:hidden px-1">
                <button type="submit" class="flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-neutral-200 hover:bg-neutral-50 text-neutral-500 shadow-sm transition-colors">
                    <x-heroicon-m-magnifying-glass class="w-4 h-4" />
                </button>
            </div>
        @endif
    </div>

    <!-- Collapsible Area (Mobile) / Inline Area (Desktop) -->
    <div class="t-acc-panel t-acc-panel--desktop-open">
        <div
            class="t-acc-panel-inner mt-2 flex min-w-0 max-w-full flex-col items-stretch border-t border-neutral-100 pt-2 sm:mt-0 sm:!flex sm:flex-row sm:border-t-0 sm:pt-0 {{ $alignClass }}"
            x-bind:class="{ '!mt-0 !border-t-0 !pt-0': !expanded }"
        >
            @if($slot->isNotEmpty())
                @if($showSearch)
                    <div class="hidden sm:block w-px h-6 bg-neutral-200 mx-1"></div>
                @endif
                {{ $slot }}
            @endif

            @if($hasFilters)
                <div class="hidden sm:block w-px h-6 bg-neutral-200 mx-1"></div>
                <a href="{{ $action }}" class="flex items-center justify-center text-xs font-semibold text-neutral-500 hover:text-neutral-800 px-3 whitespace-nowrap py-3 sm:py-0 border-t sm:border-t-0 border-neutral-100 w-full sm:w-auto mt-1 sm:mt-0">
                    Limpar Filtros
                </a>
            @endif

            <div class="w-full sm:w-auto mt-1 sm:mt-0 sm:ml-1">
                <button type="submit" aria-label="Buscar/Filtrar" class="flex items-center justify-center gap-2 w-full {{ $buttonClass }} py-2 sm:py-0 rounded-lg bg-white border border-neutral-200 hover:bg-neutral-50 text-neutral-500 hover:text-neutral-900 shadow-sm transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-accent/50 focus:ring-offset-1">
                    @if($showSearch)
                        <x-heroicon-m-magnifying-glass class="w-4 h-4" />
                    @else
                        <x-heroicon-m-funnel class="w-4 h-4" />
                    @endif
                    <span class="sm:hidden text-sm font-medium">Aplicar Filtros</span>
                </button>
            </div>
        </div>
    </div>
</form>
