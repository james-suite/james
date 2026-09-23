{{--
    Module topbar navbar — use directly inside any view that needs sub-navigation.

    Usage:
        <x-module-navbar>
            <x-module-nav-link :href="route('financial.revenues')" :current="request()->routeIs('financial.revenues')">
                Receitas
            </x-module-nav-link>
            <x-module-nav-link :href="route('financial.expenses')" :current="request()->routeIs('financial.expenses')">
                Despesas
            </x-module-nav-link>
        </x-module-navbar>

    Props:
        scrollable (bool) — allows horizontal scroll when there are many items
--}}

@props(['scrollable' => false])

<div {{ $attributes->class('-mx-3 sm:-mx-6 lg:-mx-8 px-3 sm:px-6 lg:px-8 mb-6 border-b border-neutral-300 bg-neutral-100 -mt-3 sm:-mt-6 lg:-mt-8') }}>
    <nav
        @class([
            'flex min-w-0 items-center gap-0.5 py-3',
            'scroll-fade-x touch-pan-x overscroll-x-contain overflow-x-auto overflow-y-hidden' => $scrollable,
        ])
        x-data="{
            restoreActiveLink() {
                this.$nextTick(() => {
                    const activeLink = this.$el.querySelector('[aria-current=page]');

                    if (!activeLink) {
                        return;
                    }

                    if (!{{ $scrollable ? 'true' : 'false' }}) {
                        activeLink.scrollIntoView({ block: 'nearest', inline: 'center' });

                        return;
                    }

                    const navBounds = this.$el.getBoundingClientRect();
                    const activeLinkBounds = activeLink.getBoundingClientRect();
                    const activeLinkCenter = activeLinkBounds.left + (activeLinkBounds.width / 2);
                    const navCenter = navBounds.left + (this.$el.clientWidth / 2);
                    const maxScrollLeft = this.$el.scrollWidth - this.$el.clientWidth;
                    const centeredScrollLeft = this.$el.scrollLeft + activeLinkCenter - navCenter;

                    this.$el.scrollLeft = Math.max(0, Math.min(centeredScrollLeft, maxScrollLeft));
                });
            },
        }"
        x-init="restoreActiveLink()"
    >
        {{ $slot }}
    </nav>
</div>
