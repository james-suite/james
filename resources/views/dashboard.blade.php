<x-layouts.app>
    <x-page-header title="Dashboard" :description="formatDate($today)" />

    <section class="mb-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-finance.kpi-card
            title="Finanças"
            :value="formatCurrency($financialSummary['currentBalance'])"
            icon="heroicon-o-banknotes"
            :color="$financialSummary['currentBalance'] > 0 ? 'green' : ($financialSummary['currentBalance'] < 0 ? 'red' : 'neutral')"
            :href="route('financial.dashboard')"
        >
            Saldo atual
        </x-finance.kpi-card>

        <x-finance.kpi-card
            title="Acertos"
            :value="formatCurrency(abs($settlementSummary['netBalance']))"
            icon="heroicon-o-scale"
            :color="$settlementSummary['netBalance'] > 0 ? 'green' : ($settlementSummary['netBalance'] < 0 ? 'red' : 'neutral')"
            :href="route('settlements.index')"
        >
            {{ $settlementSummary['netBalance'] > 0 ? 'Você tem a receber' : ($settlementSummary['netBalance'] < 0 ? 'Você tem a pagar' : 'Tudo quitado') }}
        </x-finance.kpi-card>

        <x-finance.kpi-card
            title="Contatos"
            :value="$contactCount"
            icon="heroicon-o-users"
            color="neutral"
            :href="route('contacts.index')"
        >
            Pessoas cadastradas
        </x-finance.kpi-card>

        <x-finance.kpi-card
            title="Notificações"
            :value="$unreadNotificationCount"
            icon="heroicon-o-bell"
            :color="$unreadNotificationCount > 0 ? 'red' : 'neutral'"
            :href="route('notifications.index')"
        >
            {{ $unreadNotificationCount === 1 ? 'Não lida' : 'Não lidas' }}
        </x-finance.kpi-card>
    </section>

    @if($settlements->isNotEmpty() || $recentNotifications->isNotEmpty())
        <section class="grid grid-cols-1 gap-4 lg:gap-6 {{ $settlements->isNotEmpty() && $recentNotifications->isNotEmpty() ? 'lg:grid-cols-2' : '' }}">
            @if($settlements->isNotEmpty())
                <x-card>
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-neutral-900">Acertos pendentes</h2>
                            <p class="mt-1 text-sm text-neutral-500">Saldos que ainda precisam ser resolvidos.</p>
                        </div>
                        <a href="{{ route('settlements.index') }}" class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-brand-700 hover:text-brand-800">
                            Ver todos
                            <x-heroicon-m-arrow-right class="size-4" />
                        </a>
                    </div>

                    @foreach($settlements as $contact)
                        <a href="{{ route('settlements.contact.show', $contact) }}" class="group flex items-center gap-3 border-t border-neutral-100 py-3 first:border-0 first:pt-0 last:pb-0">
                            <x-avatar :model="$contact" size="md" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-neutral-900 group-hover:text-brand-700">{{ $contact->name }}</span>
                                <span class="mt-0.5 block text-xs text-neutral-500">{{ $contact->net_balance > 0 ? 'Você tem a receber' : 'Você tem a pagar' }}</span>
                            </span>
                            <span class="shrink-0 text-sm font-bold {{ $contact->net_balance > 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ formatCurrency(abs($contact->net_balance)) }}
                            </span>
                        </a>
                    @endforeach
                </x-card>
            @endif

            @if($recentNotifications->isNotEmpty())
                <x-card>
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-neutral-900">Notificações não lidas</h2>
                            <p class="mt-1 text-sm text-neutral-500">Atualizações recentes dos seus módulos.</p>
                        </div>
                        <a href="{{ route('notifications.index') }}" class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-brand-700 hover:text-brand-800">
                            Ver todas
                            <x-heroicon-m-arrow-right class="size-4" />
                        </a>
                    </div>

                    @foreach($recentNotifications as $notification)
                        <a href="{{ route('notifications.show', $notification) }}" class="group flex items-start gap-3 border-t border-neutral-100 py-3 first:border-0 first:pt-0 last:pb-0">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                                <x-heroicon-o-bell class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-neutral-900 group-hover:text-brand-700">{{ $notification->data['title'] ?? 'Notificação' }}</span>
                                @if(filled($notification->data['message'] ?? null))
                                    <span class="mt-0.5 block line-clamp-2 text-xs text-neutral-500">{{ $notification->data['message'] }}</span>
                                @endif
                                <span class="mt-1 block text-xxs text-neutral-400">{{ formatDateTime($notification->created_at) }}</span>
                            </span>
                        </a>
                    @endforeach
                </x-card>
            @endif
        </section>
    @endif
</x-layouts.app>
