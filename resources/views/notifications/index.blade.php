<x-layouts.app>
    <x-page-header title="Notificações">
        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.markAllAsRead') }}">
                @csrf
                <x-button type="submit" color="secondary" class="w-full sm:w-auto">
                    <x-heroicon-o-check-badge class="size-5!" />
                    Marcar todas como lidas
                </x-button>
            </form>
        @endif
    </x-page-header>

    <div class="mt-6">
        <x-filter-bar action="{{ route('notifications.index') }}" :filters="['status', 'date_start', 'date_end', 'sort']">
            <div class="flex flex-col sm:flex-row w-full sm:w-auto divide-y sm:divide-y-0 sm:divide-x divide-neutral-200">
                <x-filter-bar.select name="status">
                    <option value="">Todos os status</option>
                    <option value="unread" @selected(request('status') === 'unread')>Não lidas</option>
                    <option value="read" @selected(request('status') === 'read')>Lidas</option>
                </x-filter-bar.select>

                <x-filter-bar.date-range
                    name-start="date_start" value-start="{{ request('date_start') }}" title-start="Data Inicial"
                    name-end="date_end"     value-end="{{ request('date_end') }}"   title-end="Data Final"
                />

                <x-filter-bar.select name="sort">
                    <option value="newest" @selected(request('sort', 'newest') === 'newest')>Mais recentes</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Mais antigas</option>
                </x-filter-bar.select>
            </div>
        </x-filter-bar>

        <x-table>
            <x-table.header class="grid-cols-[140px_1fr_200px] hidden sm:grid">
                <x-table.column>STATUS</x-table.column>
                <x-table.column>TÍTULO &amp; MENSAGEM</x-table.column>
                <x-table.column>DATA/HORA</x-table.column>
            </x-table.header>
            <div class="divide-y divide-neutral-100">
                @forelse($notifications as $notification)
                    @php
                        $isUnread = is_null($notification->read_at);
                        $title = $notification->data['title'] ?? 'Sem título';
                        $message = $notification->data['message'] ?? '';
                        $actionUrl = $notification->data['action_url'] ?? null;
                        $levelEnum = \App\Enums\NotificationLevel::tryFrom($notification->data['level'] ?? 'info') ?? \App\Enums\NotificationLevel::Info;
                    @endphp
                    <x-table.row href="{{ route('notifications.show', $notification) }}" class="grid-cols-[140px_1fr_200px] hidden sm:grid items-center {{ $isUnread ? 'bg-blue-50/40' : '' }}">
                        <x-table.cell>
                            @if($isUnread)
                                <x-badge :color="$levelEnum->color()" size="sm">{{ $levelEnum->label() }}</x-badge>
                            @else
                                <x-badge color="neutral" size="sm">Lida</x-badge>
                            @endif
                        </x-table.cell>
                        <x-table.cell>
                            <div class="flex items-center gap-2">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium {{ $isUnread ? 'text-neutral-900 font-semibold' : 'text-neutral-700' }} truncate">
                                        {{ $title }}
                                    </div>
                                    @if($message)
                                        <div class="text-xs text-neutral-500 truncate mt-0.5">
                                            {{ $message }}
                                        </div>
                                    @endif
                                </div>
                                @if($actionUrl)
                                    <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5 text-neutral-400 shrink-0" />
                                @endif
                            </div>
                        </x-table.cell>
                        <x-table.cell class="text-neutral-600 text-sm font-medium">
                            {{ formatDateTime($notification->created_at) }}
                        </x-table.cell>

                        <x-slot:mobile>
                            <div class="flex justify-between items-start">
                                <div class="flex flex-col gap-1.5 min-w-0 pr-2">
                                    <div class="flex items-center gap-2">
                                        @if($isUnread)
                                            <x-badge :color="$levelEnum->color()" size="sm">{{ $levelEnum->label() }}</x-badge>
                                        @else
                                            <x-badge color="neutral" size="sm">Lida</x-badge>
                                        @endif
                                        <span class="text-sm font-medium {{ $isUnread ? 'text-neutral-900 font-semibold' : 'text-neutral-700' }} truncate">
                                            {{ $title }}
                                        </span>
                                    </div>
                                    @if($message)
                                        <div class="text-xs text-neutral-500 line-clamp-2">
                                            {{ $message }}
                                        </div>
                                    @endif
                                </div>
                                <div class="text-right text-xs text-neutral-500 shrink-0">
                                    {{ formatDateTime($notification->created_at) }}
                                </div>
                            </div>
                        </x-slot:mobile>
                    </x-table.row>
                @empty
                    <x-empty-state
                        icon="heroicon-o-bell"
                        title="Nenhuma notificação encontrada"
                        description="Não há notificações registradas para você no momento."
                    />
                @endforelse
            </div>
        </x-table>

        @if($notifications->hasPages())
            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>

