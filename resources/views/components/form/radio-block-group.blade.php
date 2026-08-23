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
                init() {
                    this.$nextTick(() => {
                        this.updateIndicator();
                        this.resizeObserver = new ResizeObserver(() => this.updateIndicator());
                        this.resizeObserver.observe(this.$el);
                    });
                },
                updateIndicator() {
                    const labels = [...this.$el.querySelectorAll(':scope > label')];
                    const selected = labels.find((label) => label.querySelector('input:checked'));
                    const indicator = this.$refs.indicator;

                    if (!selected || !indicator) {
                        return;
                    }

                    const groupRect = this.$el.getBoundingClientRect();
                    const selectedRect = selected.getBoundingClientRect();
                    const indicatorStyle = getComputedStyle(indicator);
                    const indicatorLeft = Number.parseFloat(indicatorStyle.left) || 0;
                    const indicatorTop = Number.parseFloat(indicatorStyle.top) || 0;

                    if (!selectedRect.width || !selectedRect.height) {
                        return;
                    }

                    indicator.style.width = `${selectedRect.width}px`;
                    indicator.style.height = `${selectedRect.height}px`;
                    indicator.style.transform = `translate(${selectedRect.left - groupRect.left - indicatorLeft}px, ${selectedRect.top - groupRect.top - indicatorTop}px)`;
                }
            }"
            @change="updateIndicator()"
            @resize.window="updateIndicator()"
        @endif
        {{ $attributes->merge(['class' => 'grid grid-cols-2 sm:flex sm:flex-row gap-2 items-stretch bg-neutral-200 rounded-xl p-1 ' . ($sliding ? 'radio-block-group--sliding' : '')]) }}>
        @if($sliding)
            <span x-ref="indicator" class="radio-block-indicator bg-white" aria-hidden="true"></span>
        @endif
        {{ $slot }}
    </div>
</fieldset>
