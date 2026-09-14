@props(['headerBg' => null])

<div
    x-data="{
        open: false,
        isDesktop: window.matchMedia('(min-width: 1024px)').matches,
        init() {
            window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
                this.isDesktop = event.matches;
                if (event.matches) {
                    this.open = false;
                }
            });
        },
        openMenu() {
            this.open = true;
            this.$nextTick(() => this.$refs.sidebar.querySelector('nav a')?.focus());
        },
        closeMenu() {
            if (this.isDesktop || !this.open) {
                return;
            }

            this.open = false;
            this.$nextTick(() => this.$refs.menuButton?.focus());
        },
    }"
    x-effect="document.documentElement.classList.toggle('t-sidebar-open', open && !isDesktop)"
    @keydown.escape.window="closeMenu()"
>
    <aside
        x-ref="sidebar"
        id="main-navigation"
        class="fixed top-0 left-0 z-40 flex h-screen w-64 flex-col gap-4 border-e border-neutral-300 bg-neutral-100 p-4 transition-transform motion-duration-fast motion-ease-smooth-out lg:translate-x-0"
        :class="{ '-translate-x-full': !open && !isDesktop }"
        :aria-hidden="(!open && !isDesktop).toString()"
        :inert="!open && !isDesktop"
        x-show="open || isDesktop"
        x-cloak
    >
        <div class="flex items-center">
            <a href="{{ route('dashboard') }}">
                <x-app-logo />
            </a>

            <button
                type="button"
                class="ms-auto cursor-pointer rounded-md p-1 hover:bg-neutral-200 lg:hidden"
                aria-label="Fechar menu"
                @click="closeMenu()"
            >
                <x-heroicon-o-x-mark class="w-6 h-6" />
            </button>
        </div>

        <nav class="flex min-h-auto flex-col space-y-0.5" @click="if (!isDesktop) closeMenu()">
            {{ $slot }}
        </nav>

        <x-dropdown position="top" class="mt-auto hidden lg:block" accent contentClass="w-full">
            <x-slot name="trigger">
                <button class="w-full flex items-center rounded-lg p-1 hover:bg-neutral-800/5 group cursor-pointer">
                    <x-avatar :model="auth()->user()" />
                    <span
                        class="mx-2 text-sm font-medium truncate text-neutral-800/80 group-hover:text-neutral-800">{{ auth()->user()->name }}</span>
                    <div class="ms-auto text-neutral-800/80 group-hover:text-neutral-800">
                        <x-heroicon-m-chevron-down class="h-6 w-6 transition-transform duration-200 ease-out" x-bind:class="{ 'rotate-180': open }" />
                    </div>
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="flex items-center gap-2 p-2">
                    <x-avatar :model="auth()->user()" />
                    <div class="truncate">
                        <div class="text-sm font-semibold text-neutral-800 truncate">
                            {{ auth()->user()->name }}</div>
                        <div class="text-xs text-neutral-500 truncate">
                            {{ auth()->user()->email }}</div>
                    </div>
                </div>

                <hr class="my-1 border-neutral-300">

                <a href="{{ route('settings') }}" @click="closeMenu()"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm text-neutral-700 hover:bg-neutral-200">
                    <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                    Configurações
                </a>
                <form method="POST" id="logout" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" @click="closeMenu()"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm text-red-500 hover:bg-neutral-200 cursor-pointer">
                        <x-heroicon-m-arrow-right-start-on-rectangle class="w-5 h-5" />
                        Sair
                    </button>
                </form>

            </x-slot>
        </x-dropdown>

    </aside>

    <div class="fixed inset-0 z-30 bg-black/10 lg:hidden" x-cloak x-show="open && !isDesktop"
        x-transition:enter="transition-opacity motion-duration-fast motion-ease-smooth-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity motion-duration-quick motion-ease-smooth-out"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="closeMenu()"></div>

    <header class="flex items-center px-6 w-full min-h-14 lg:hidden {{ $headerBg }}">
        <button
            x-ref="menuButton"
            type="button"
            class="cursor-pointer rounded-lg p-1 hover:bg-neutral-200"
            aria-label="Abrir menu"
            aria-controls="main-navigation"
            :aria-expanded="open.toString()"
            @click="open ? closeMenu() : openMenu()"
        >
            <x-heroicon-o-bars-3 class="w-6 h-6" />
        </button>

        <x-dropdown position="bottom-end" class="ms-auto" accent contentClass="w-60">
            <x-slot name="trigger">
                <button
                    class="w-full flex items-center rounded-lg p-1 hover:bg-neutral-800/5 group cursor-pointer gap-2">
                    <x-avatar :model="auth()->user()" />

                    <div class="ms-auto text-neutral-800/80 group-hover:text-neutral-800">
                        <x-heroicon-m-chevron-up class="h-4 w-4 transition-transform duration-200 ease-out" x-bind:class="{ 'rotate-180': open }" />
                    </div>
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="flex items-center gap-2 p-2">
                    <x-avatar :model="auth()->user()" />
                    <div class="truncate">
                        <div class="text-sm font-semibold text-neutral-800 truncate">
                            {{ auth()->user()->name }}</div>
                        <div class="text-xs text-neutral-500 truncate">
                            {{ auth()->user()->email }}</div>
                    </div>
                </div>

                <hr class="my-1 border-neutral-300">

                <a href="{{ route('settings') }}" @click="closeMenu()"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm text-neutral-700 hover:bg-neutral-200">
                    <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                    Configurações
                </a>

                <button type="submit" form="logout" @click="closeMenu()"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm text-red-500 hover:bg-neutral-200 cursor-pointer">
                    <x-heroicon-m-arrow-right-start-on-rectangle class="w-5 h-5" />
                    Sair
                </button>

            </x-slot>
        </x-dropdown>
    </header>
</div>
