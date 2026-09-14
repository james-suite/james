@if($activities->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'block']) }}>
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-neutral-900">Histórico de Atividades</h2>
            <a href="{{ route('audit.index', ['module' => get_class($model), 'subject_id' => $model->id]) }}" class="text-sm font-medium text-primary-600 hover:text-primary-700 transition-colors">
                Ver histórico completo &rarr;
            </a>
        </div>

        <x-table class="overflow-hidden">
            <x-table.header class="hidden grid-cols-[200px_1fr_120px] sm:grid">
                <x-table.column>DATA/HORA</x-table.column>
                <x-table.column>CAUSADOR</x-table.column>
                <x-table.column>AÇÃO</x-table.column>
            </x-table.header>
            <div class="divide-y divide-neutral-100">
                @foreach($activities as $activity)
                    @php
                        $actionTranslations = ['created' => 'Criado', 'updated' => 'Atualizado', 'deleted' => 'Excluído', 'item_deleted' => 'Item excluído', 'restored' => 'Restaurado', 'forceDeleted' => 'Excluído Permanentemente'];
                        $actionName = $actionTranslations[$activity->description] ?? ucfirst($activity->description);
                        $actionColors = ['created' => 'green', 'updated' => 'blue', 'deleted' => 'red', 'item_deleted' => 'red', 'restored' => 'yellow', 'forceDeleted' => 'rose'];
                        $color = $actionColors[$activity->description] ?? 'neutral';
                    @endphp
                    <x-table.row :href="route('audit.show', $activity)" class="hidden grid-cols-[200px_1fr_120px] items-center transition-colors hover:bg-neutral-50/50 sm:grid">
                        <x-table.cell class="text-neutral-600 text-sm font-medium">{{ formatDateTime($activity->created_at) }}</x-table.cell>
                        <x-table.cell>
                            @if($activity->causer)
                                <div class="flex items-center gap-2">
                                    <x-avatar :model="$activity->causer" size="sm" />
                                    <span class="font-medium text-neutral-900">{{ $activity->causer->name }}</span>
                                </div>
                            @else
                                <div class="flex items-center gap-2 text-neutral-500 font-medium">
                                    <x-heroicon-o-cog-8-tooth class="size-5" />
                                    <span>Sistema / Rotina Automática</span>
                                </div>
                            @endif
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge :color="$color" size="sm">{{ $actionName }}</x-badge>
                        </x-table.cell>

                        <x-slot name="mobile">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <x-badge :color="$color" size="sm">{{ $actionName }}</x-badge>
                                    <div class="mt-2 flex min-w-0 items-center gap-2 text-sm text-neutral-600">
                                        @if($activity->causer)
                                            <x-avatar :model="$activity->causer" size="sm" />
                                            <span class="truncate font-medium text-neutral-900">{{ $activity->causer->name }}</span>
                                        @else
                                            <x-heroicon-o-cog-8-tooth class="size-4 shrink-0" />
                                            <span>Sistema / Rotina Automática</span>
                                        @endif
                                    </div>
                                </div>
                                <time class="shrink-0 text-right text-xs font-medium text-neutral-500" datetime="{{ $activity->created_at->toIso8601String() }}">
                                    {{ formatDateTime($activity->created_at) }}
                                </time>
                            </div>
                        </x-slot>
                    </x-table.row>
                @endforeach
            </div>
        </x-table>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'block']) }}></div>
@endif
