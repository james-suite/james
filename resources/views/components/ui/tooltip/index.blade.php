@props([
    'text' => '',
    'id' => null,
    'position' => 'top',
    'contentClass' => '',
])

@php
    $tooltipId = $id ?? 'tooltip-' . \Illuminate\Support\Str::uuid();
    $tooltipPosition = in_array($position, ['top', 'bottom', 'left', 'right'], true) ? $position : 'top';
@endphp

<span {{ $attributes->merge(['class' => 't-tt-wrap']) }}
    x-data="{
        show: false,
        updatePosition() {
            if (!this.show) return;
            const trigger = this.$el.getBoundingClientRect();
            const tooltipWidth = this.$refs.tt.offsetWidth;
            const tooltipHeight = this.$refs.tt.offsetHeight;
            
            let top, left;
            if ('{{ $tooltipPosition }}' === 'top') {
                top = trigger.top - tooltipHeight - 8;
                left = trigger.left + (trigger.width / 2) - (tooltipWidth / 2);
            } else if ('{{ $tooltipPosition }}' === 'bottom') {
                top = trigger.bottom + 8;
                left = trigger.left + (trigger.width / 2) - (tooltipWidth / 2);
            } else if ('{{ $tooltipPosition }}' === 'left') {
                top = trigger.top + (trigger.height / 2) - (tooltipHeight / 2);
                left = trigger.left - tooltipWidth - 8;
            } else if ('{{ $tooltipPosition }}' === 'right') {
                top = trigger.top + (trigger.height / 2) - (tooltipHeight / 2);
                left = trigger.right + 8;
            }
            
            this.$refs.tt.style.top = top + 'px';
            this.$refs.tt.style.left = left + 'px';
        }
    }"
    @mouseenter="show = true; $nextTick(() => updatePosition())"
    @mouseleave="show = false"
    @focusin="show = true; $nextTick(() => updatePosition())"
    @focusout="show = false"
    @scroll.window="updatePosition()"
    @resize.window="updatePosition()"
>
    {{ $slot }}

    <template x-teleport="body">
        <span
            x-ref="tt"
            x-show="show"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            id="{{ $tooltipId }}"
            role="tooltip"
            data-position="{{ $tooltipPosition }}"
            class="t-tt-teleported {{ $contentClass }}"
            style="position: fixed; z-index: 99999;"
        >
            {{ $text }}
        </span>
    </template>
</span>
