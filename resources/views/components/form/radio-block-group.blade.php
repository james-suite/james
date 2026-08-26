@props([
    'legend' => '',
    'sliding' => true,
])

<fieldset>
    @if($legend)
        <legend class="text-sm font-semibold text-neutral-700 mb-3">{{ $legend }}</legend>
    @endif
    <div
        @if($sliding)
            x-data="{
                resizeObserver: null,
                visibilityObserver: null,
                indicatorFrame: null,
                init() {
                    this.$nextTick(() => {
                        this.updateIndicator();
                        this.resizeObserver = new ResizeObserver(() => this.scheduleIndicatorUpdate());
                        this.resizeObserver.observe(this.$el);

                        const visibilityTargets = new Map();
                        let visibilityTarget = this.$el;

                        while (visibilityTarget) {
                            if (visibilityTarget.hasAttribute('x-show')) {
                                visibilityTargets.set(
                                    visibilityTarget,
                                    getComputedStyle(visibilityTarget).display !== 'none',
                                );
                            }

                            visibilityTarget = visibilityTarget.parentElement;
                        }

                        if (visibilityTargets.size) {
                            this.visibilityObserver = new MutationObserver((mutations) => {
                                const becameVisible = mutations.some(({ target }) => {
                                    const isVisible = getComputedStyle(target).display !== 'none';
                                    const wasVisible = visibilityTargets.get(target);

                                    visibilityTargets.set(target, isVisible);

                                    return isVisible && !wasVisible;
                                });

                                if (becameVisible) {
                                    this.$refs.indicator?.style.setProperty('visibility', 'hidden');
                                    this.scheduleIndicatorUpdate();
                                }
                            });
                            visibilityTargets.forEach((_, target) => {
                                this.visibilityObserver.observe(target, {
                                    attributes: true,
                                    attributeFilter: ['style'],
                                });
                            });
                        }
                    });
                },
                scheduleIndicatorUpdate(animate = false, attempts = 0) {
                    cancelAnimationFrame(this.indicatorFrame);

                    this.indicatorFrame = requestAnimationFrame(() => {
                        this.indicatorFrame = null;

                        if (!this.updateIndicator(animate) && attempts < 2) {
                            this.scheduleIndicatorUpdate(animate, attempts + 1);
                        }
                    });
                },
                updateIndicator(animate = false) {
                    const labels = [...this.$el.querySelectorAll(':scope > label')];
                    const selected = labels.find((label) => label.querySelector('input:checked'));
                    const indicator = this.$refs.indicator;

                    if (!selected || !indicator) {
                        return false;
                    }

                    const indicatorStyle = getComputedStyle(indicator);
                    const indicatorLeft = Number.parseFloat(indicatorStyle.left) || 0;
                    const indicatorTop = Number.parseFloat(indicatorStyle.top) || 0;

                    if (!this.$el.offsetWidth || !this.$el.offsetHeight || !selected.offsetWidth || !selected.offsetHeight) {
                        return false;
                    }

                    const transition = indicator.style.transition;

                    if (!animate) {
                        indicator.style.transition = 'none';
                    }

                    indicator.style.width = `${selected.offsetWidth}px`;
                    indicator.style.height = `${selected.offsetHeight}px`;
                    indicator.style.transform = `translate(${selected.offsetLeft - indicatorLeft}px, ${selected.offsetTop - indicatorTop}px)`;
                    indicator.style.visibility = 'visible';

                    if (!animate) {
                        void indicator.offsetWidth;
                        indicator.style.transition = transition;
                    }

                    return true;
                },
                destroy() {
                    cancelAnimationFrame(this.indicatorFrame);
                    this.resizeObserver?.disconnect();
                    this.visibilityObserver?.disconnect();
                }
            }"
            @change="scheduleIndicatorUpdate(true)"
            @resize.window="scheduleIndicatorUpdate()"
        @endif
        {{ $attributes->merge(['class' => 'grid grid-cols-2 sm:flex sm:flex-row gap-2 items-stretch bg-neutral-200 rounded-xl p-1 ' . ($sliding ? 'radio-block-group--sliding' : '')]) }}>
        @if($sliding)
            <span class="radio-block-indicator bg-white" style="visibility: hidden" x-ref="indicator" aria-hidden="true"></span>
        @endif
        {{ $slot }}
    </div>
</fieldset>
