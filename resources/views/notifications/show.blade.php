<x-layouts.app>
    <x-page-header title="Detalhes da Notificação">
        <div class="flex items-center gap-2">
            <x-back-button fallback="{{ route('notifications.index') }}" />

            <x-modal.delete
                action="{{ route('notifications.destroy', $notification) }}"
                item-name="esta notificação"
                title="Excluir Notificação"
            />
        </div>
    </x-page-header>

    <div class="mt-6">
        @php
            $isUnread = is_null($notification->read_at);
            $title = $notification->data['title'] ?? 'Sem título';
            $message = $notification->data['message'] ?? '';
            $actionUrl = $notification->data['action_url'] ?? null;
            $actionLabel = $notification->data['action_label'] ?? 'Acessar no Sistema';
            $levelEnum = \App\Enums\NotificationLevel::tryFrom($notification->data['level'] ?? 'info') ?? \App\Enums\NotificationLevel::Info;
            $details = $notification->data['details'] ?? [];
            $items = $notification->data['items'] ?? [];
        @endphp

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <!-- Painel de Informações Laterais -->
            <div class="xl:col-span-1 space-y-6">
                <x-card class="!p-0 overflow-hidden">
                    <div class="divide-y divide-neutral-100">
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider mb-1">STATUS</p>
                            <div>
                                @if($isUnread)
                                    <x-badge color="blue" size="sm">Não lida</x-badge>
                                @else
                                    <x-badge color="neutral" size="sm">Lida</x-badge>
                                @endif
                            </div>
                        </div>

                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider mb-1">NÍVEL</p>
                            <div>
                                <x-badge :color="$levelEnum->color()" size="sm">{{ $levelEnum->label() }}</x-badge>
                            </div>
                        </div>

                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider mb-1">DATA / HORA DO ENVIO</p>
                            <div class="text-sm font-medium text-neutral-900">{{ formatDateTime($notification->created_at) }}</div>
                        </div>

                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider mb-1">LIDA EM</p>
                            <div class="text-sm font-medium text-neutral-900">
                                {{ $notification->read_at ? formatDateTime($notification->read_at) : 'Não lida' }}
                            </div>
                        </div>

                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider mb-1">TIPO DE NOTIFICAÇÃO</p>
                            <div class="text-sm font-medium text-neutral-700">
                                {{ class_basename($notification->type) }}
                            </div>
                        </div>
                    </div>
                </x-card>
            </div>

            <!-- Painel Principal de Conteúdo -->
            <div class="xl:col-span-2 space-y-6">
                <x-card class="space-y-4">
                    <div>
                        <p class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider mb-1">MENSAGEM</p>
                        <h3 class="text-xl font-bold text-neutral-900">{{ $title }}</h3>
                        @if($message)
                            <p class="text-sm text-neutral-700 mt-3 leading-relaxed whitespace-pre-line">{{ $message }}</p>
                        @endif
                    </div>

                    @if($actionUrl)
                        <div class="pt-4 border-t border-neutral-100 flex items-center justify-between">
                            <span class="text-xs text-neutral-500">Ação recomendada:</span>
                            <x-button :href="$actionUrl">
                                <x-heroicon-m-arrow-top-right-on-square class="size-4!" />
                                {{ $actionLabel }}
                            </x-button>
                        </div>
                    @endif
                </x-card>

                <!-- Informações Estruturadas (Detalhes) -->
                @if(!empty($details))
                    <x-card class="!p-0 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-neutral-100 bg-neutral-50">
                            <p class="text-xs font-bold text-neutral-700 uppercase tracking-wider">Informações Adicionais</p>
                        </div>
                        <div class="divide-y divide-neutral-100">
                            @foreach($details as $key => $val)
                                <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-sm">
                                    <span class="text-neutral-500 font-medium">{{ $key }}</span>
                                    <span class="text-neutral-900 font-semibold">
                                        {{ is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : $val }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endif

                @if(!empty($items))
                    <x-card class="!p-0 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-neutral-100 bg-neutral-50">
                            <p class="text-xs font-bold text-neutral-700 uppercase tracking-wider">Itens Importados</p>
                        </div>
                        <div class="divide-y divide-neutral-100">
                            @foreach($items as $item)
                                <div class="px-5 py-4 space-y-3">
                                    <p class="font-semibold text-neutral-900">{{ $item['description'] }}</p>
                                    <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                                        <div>
                                            <dt class="text-xs font-medium text-neutral-500">Quantidade</dt>
                                            <dd class="mt-1 font-semibold text-neutral-900">{{ $item['quantity'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-neutral-500">Valor unitário</dt>
                                            <dd class="mt-1 font-semibold text-neutral-900">{{ $item['unit_price'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-neutral-500">Total</dt>
                                            <dd class="mt-1 font-semibold text-neutral-900">{{ $item['total'] }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
