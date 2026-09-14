<x-layouts.app headerBg="bg-neutral-100">
    <x-module-navbar scrollable>
        <x-module-nav-link :href="route('financial.dashboard')" :current="request()->routeIs('financial.dashboard')">
            Dashboard
        </x-module-nav-link>
        <x-module-nav-link :href="route('financial.transactions.index')" :current="request()->routeIs('financial.transactions.*')">
            Transações
        </x-module-nav-link>
        <x-module-nav-link :href="route('financial.recurrences.index')" :current="request()->routeIs('financial.recurrences.*')">
            Recorrências
        </x-module-nav-link>
        <x-module-nav-link :href="route('financial.reports')" :current="request()->routeIs('financial.reports.*', 'financial.reports')">
            Relatórios
        </x-module-nav-link>

        <!-- Configurações e Cadastros Base -->
        <x-module-nav-link :href="route('financial.accounts.index')" :current="request()->routeIs('financial.accounts.*')">
            Contas
        </x-module-nav-link>
        <x-module-nav-link :href="route('financial.cards.index')" :current="request()->routeIs('financial.cards.*')">
            Cartões
        </x-module-nav-link>
        <x-module-nav-link :href="route('financial.tags.index')" :current="request()->routeIs('financial.tags.*')">
            Tags
        </x-module-nav-link>
    </x-module-navbar>

    <div class="mt-6">
        {{ $slot }}
    </div>
</x-layouts.app>
