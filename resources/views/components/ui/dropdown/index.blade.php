@props([
    'position' => 'top', // Valor padrão: abre para cima
    'contentClass' => '',
    'accent' => false,
])

@php
    $posClasses = [
        'top' => 'bottom-full mb-2 left-1/2 -translate-x-1/2',
        'top-start' => 'bottom-full mb-2 left-0',
        'top-end' => 'bottom-full mb-2 right-0',
        'bottom' => 'top-full mt-2 left-1/2 -translate-x-1/2',
        'bottom-start' => 'top-full mt-2 left-0',
        'bottom-end' => 'top-full mt-2 right-0',
    ];
    $dropdownPosition = $posClasses[$position] ?? $posClasses['top'];
    $dropdownOrigin = match ($position) {
        'top-start' => 'bottom-left',
        'top-end' => 'bottom-right',
        'bottom-start' => 'top-left',
        'bottom-end' => 'top-right',
        'bottom' => 'top-center',
        default => 'bottom-center',
    };
    $dropdownId = 'dropdown-' . \Illuminate\Support\Str::uuid();
@endphp

<div
    x-data="{
        open: false,
        closing: false,
        rendered: false,
        closeTimer: null,
        syncTrigger() {
            const trigger = this.$root.querySelector('button, a');
            if (!trigger) {
                return;
            }

            trigger.setAttribute('aria-haspopup', 'menu');
            trigger.setAttribute('aria-expanded', this.open ? 'true' : 'false');
            trigger.setAttribute('aria-controls', '{{ $dropdownId }}');
        },
        openMenu() {
            window.clearTimeout(this.closeTimer);
            this.closing = false;
            this.open = true;
            this.rendered = false;

            this.$nextTick(() => {
                void this.$refs.menu.offsetWidth;
                window.requestAnimationFrame(() => {
                    if (this.open) {
                        this.rendered = true;
                    }
                });
            });
        },
        closeMenu() {
            if (!this.open) {
                return;
            }

            this.open = false;
            this.rendered = false;
            this.closing = true;
            window.clearTimeout(this.closeTimer);

            const duration = Number.parseFloat(
                getComputedStyle(this.$refs.menu).getPropertyValue('--dropdown-close-dur')
            ) || 150;

            this.closeTimer = window.setTimeout(() => {
                this.closing = false;
            }, duration);
        },
        toggleMenu() {
            this.open ? this.closeMenu() : this.openMenu();
        },
    }"
    x-init="syncTrigger()"
    x-effect="syncTrigger()"
    @click.outside="closeMenu()"
    @keydown.escape.prevent.stop="closeMenu()"
    {{ $attributes->merge(['class' => 'relative']) }}
>

    {{-- Slot para o gatilho (Botão) --}}
    <div @click="toggleMenu()">
        {{ $trigger }}
    </div>

    {{-- Slot para o conteudo --}}
    <div
        x-ref="menu"
        id="{{ $dropdownId }}"
        x-show="open || closing"
        x-cloak
        data-origin="{{ $dropdownOrigin }}"
        :class="{
            'is-opening': open && !rendered,
            'is-open': open && rendered,
            'is-closing': closing,
        }"
        @class([
        't-dropdown absolute z-50 rounded-lg border bg-white p-1 shadow-lg',
        $dropdownPosition,
        'border-accent' => $accent,
        'border-neutral-300' => !$accent,
        $contentClass,
    ])>
        {{ $content }}
    </div>

</div>
