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
        tooltipId: '{{ $tooltipId }}',
        positionFrame: null,
        showTooltip() {
            window.dispatchEvent(new CustomEvent('james-tooltip-show', {
                detail: { id: this.tooltipId },
            }));

            this.show = true;
            this.schedulePosition();
        },
        hideTooltip() {
            this.show = false;

            if (this.positionFrame !== null) {
                cancelAnimationFrame(this.positionFrame);
                this.positionFrame = null;
            }
        },
        hideIfAnotherTooltip(event) {
            if (event.detail?.id !== this.tooltipId) {
                this.hideTooltip();
            }
        },
        schedulePosition() {
            this.$nextTick(() => {
                if (!this.show) return;

                if (this.positionFrame !== null) {
                    cancelAnimationFrame(this.positionFrame);
                }

                this.positionFrame = requestAnimationFrame(() => {
                    this.positionFrame = null;
                    this.updatePosition();
                });
            });
        },
        updatePosition() {
            if (!this.show) return;
            const tooltip = this.$refs.tt;
            const trigger = this.$el.getBoundingClientRect();
            const viewportPadding = 8;
            const offset = 8;
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const availableWidth = Math.max(viewportWidth - (viewportPadding * 2), 0);

            tooltip.style.maxWidth = Math.min(256, availableWidth) + 'px';
            tooltip.style.whiteSpace = availableWidth < 256 ? 'normal' : 'nowrap';

            const tooltipWidth = tooltip.offsetWidth;
            const tooltipHeight = tooltip.offsetHeight;
            const clamp = (value, min, max) => Math.min(Math.max(value, min), Math.max(min, max));
            const requestedPosition = '{{ $tooltipPosition }}';
            const oppositePosition = {
                top: 'bottom',
                bottom: 'top',
                left: 'right',
                right: 'left',
            };
            const fits = {
                top: trigger.top - tooltipHeight - offset >= viewportPadding,
                bottom: trigger.bottom + tooltipHeight + offset <= viewportHeight - viewportPadding,
                left: trigger.left - tooltipWidth - offset >= viewportPadding,
                right: trigger.right + tooltipWidth + offset <= viewportWidth - viewportPadding,
            };
            const position = fits[requestedPosition]
                ? requestedPosition
                : (fits[oppositePosition[requestedPosition]] ? oppositePosition[requestedPosition] : requestedPosition);
            const maxLeft = viewportWidth - tooltipWidth - viewportPadding;
            const maxTop = viewportHeight - tooltipHeight - viewportPadding;

            let top;
            let left;

            if (position === 'top' || position === 'bottom') {
                top = position === 'top'
                    ? trigger.top - tooltipHeight - offset
                    : trigger.bottom + offset;
                left = trigger.left + (trigger.width / 2) - (tooltipWidth / 2);
                left = clamp(left, viewportPadding, maxLeft);
                top = clamp(top, viewportPadding, maxTop);
            } else {
                top = trigger.top + (trigger.height / 2) - (tooltipHeight / 2);
                left = position === 'left'
                    ? trigger.left - tooltipWidth - offset
                    : trigger.right + offset;
                top = clamp(top, viewportPadding, maxTop);
                left = clamp(left, viewportPadding, maxLeft);
            }

            tooltip.dataset.position = position;
            tooltip.style.top = top + 'px';
            tooltip.style.left = left + 'px';
        }
    }"
    @james-tooltip-show.window="hideIfAnotherTooltip($event)"
    @mouseenter="showTooltip()"
    @mouseleave="hideTooltip()"
    @focusin="showTooltip()"
    @focusout="hideTooltip()"
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
