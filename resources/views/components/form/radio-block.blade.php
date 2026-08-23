@props([
    'name',
    'value',
    'icon' => null,
    'label' => '',
    'activeClass' => 'peer-checked:text-neutral-900',
    'inactiveClass' => 'text-neutral-500 hover:text-neutral-700',
    'sliding' => true,
])

<label class="flex-1 h-full">
    <input type="radio" name="{{ $name }}" value="{{ $value }}" {{ $attributes->except(['class']) }} class="peer sr-only" />
    <div class="flex flex-col h-full items-center justify-center py-3 rounded-lg transition-all cursor-pointer peer-checked:bg-white peer-checked:shadow-sm {{ $sliding ? 'radio-block--sliding !bg-transparent !shadow-none' : '' }} {{ $activeClass }} {{ $inactiveClass }} {{ $attributes->get('class') }}">
        @if($icon)
            <x-dynamic-component :component="$icon" class="w-6 h-6 mb-1" />
        @endif
        @if($label)
            <span class="text-sm font-medium">{{ $label }}</span>
        @endif
    </div>
</label>
