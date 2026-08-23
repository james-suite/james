<x-layouts.app>
    <x-page-header title="Logs do Sistema" />

    <div class="mt-6">
        <x-filter-bar action="{{ route('audit.index') }}" :showSearch="false" :filters="['module', 'action', 'user', 'date_start', 'date_end', 'sort']">
            <div class="flex flex-col sm:flex-row w-full sm:w-auto divide-y sm:divide-y-0 sm:divide-x divide-neutral-200">
                <x-filter-bar.select name="module">
                    <option value="">Todos os models</option>
                    @foreach($modules as $module)
                        <option value="{{ $module }}" @selected(request('module') == $module)>{{ class_basename($module) }}</option>
                    @endforeach
                </x-filter-bar.select>
                
                <x-filter-bar.select name="action">
                    <option value="">Todas as ações</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') == $action)>
                            {{ ['created' => 'Criado', 'updated' => 'Atualizado', 'deleted' => 'Excluído', 'item_deleted' => 'Item excluído', 'restored' => 'Restaurado', 'forceDeleted' => 'Excluído Permanentemente'][$action] ?? ucfirst($action) }}
                        </option>
                    @endforeach
                </x-filter-bar.select>
                
                <x-filter-bar.select name="user">
                    <option value="">Todos os causadores</option>
                    <option value="system" @selected(request('user') === 'system')>Sistema</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected(request('user') == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </x-filter-bar.select>
                
                <x-filter-bar.date-range
                    name-start="date_start" value-start="{{ request('date_start') }}" title-start="Data Inicial"
                    name-end="date_end"     value-end="{{ request('date_end') }}"   title-end="Data Final"
                />
                
                <x-filter-bar.select name="sort">
                    <option value="newest" @selected(request('sort', 'newest') == 'newest')>Mais recentes</option>
                    <option value="oldest" @selected(request('sort') == 'oldest')>Mais antigos</option>
                </x-filter-bar.select>
            </div>
        </x-filter-bar>

        <x-table>
            <x-table.header class="grid-cols-[200px_1.5fr_1fr_220px] hidden sm:grid">
                <x-table.column>DATA/HORA</x-table.column>
                <x-table.column>CAUSADOR</x-table.column>
                <x-table.column>MODEL</x-table.column>
                <x-table.column>AÇÃO</x-table.column>
            </x-table.header>
            <div class="divide-y divide-neutral-100">
                @forelse($activities as $activity)
                    <x-table.row href="{{ route('audit.show', $activity) }}" class="grid-cols-[200px_1.5fr_1fr_220px] hidden sm:grid items-center">
                        <x-table.cell class="text-neutral-600 text-sm font-medium">{{ formatDateTime($activity->created_at) }}</x-table.cell>
                        <x-table.cell>
                            @if($activity->causer)
                                <div class="flex items-center gap-2">
                                    <x-avatar :model="$activity->causer" size="sm" />
                                    <span class="font-medium text-neutral-900">{{ $activity->causer->name }}</span>
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <x-avatar icon="heroicon-o-cpu-chip" size="sm" />
                                    <span class="text-neutral-500 font-medium">Sistema</span>
                                </div>
                            @endif
                        </x-table.cell>
                        <x-table.cell class="font-medium text-neutral-700">{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</x-table.cell>
                        <x-table.cell>
                            @php
                                $actionTranslations = ['created' => 'Criado', 'updated' => 'Atualizado', 'deleted' => 'Excluído', 'item_deleted' => 'Item excluído', 'restored' => 'Restaurado', 'forceDeleted' => 'Excluído Permanentemente'];
                                $actionName = $actionTranslations[$activity->description] ?? ucfirst($activity->description);
                                $actionColors = ['created' => 'green', 'updated' => 'blue', 'deleted' => 'red', 'item_deleted' => 'red', 'restored' => 'yellow', 'forceDeleted' => 'rose'];
                                $color = $actionColors[$activity->description] ?? 'neutral';
                            @endphp
                            <x-badge :color="$color" size="sm">{{ $actionName }}</x-badge>
                        </x-table.cell>
                        
                        <x-slot:mobile>
                            <div class="flex justify-between items-start">
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        @php
                                            $actionTranslations = ['created' => 'Criado', 'updated' => 'Atualizado', 'deleted' => 'Excluído', 'item_deleted' => 'Item excluído', 'restored' => 'Restaurado', 'forceDeleted' => 'Excluído Permanentemente'];
                                            $actionName = $actionTranslations[$activity->description] ?? ucfirst($activity->description);
                                            $actionColors = ['created' => 'green', 'updated' => 'blue', 'deleted' => 'red', 'item_deleted' => 'red', 'restored' => 'yellow', 'forceDeleted' => 'rose'];
                                            $color = $actionColors[$activity->description] ?? 'neutral';
                                        @endphp
                                        <x-badge :color="$color" size="sm">{{ $actionName }}</x-badge>
                                        <span class="text-sm font-medium text-neutral-900">{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</span>
                                    </div>
                                    <div class="text-xs text-neutral-500">
                                        @if($activity->causer)
                                            {{ $activity->causer->name }}
                                        @else
                                            Sistema
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right text-sm">
                                    <div class="text-neutral-900 font-medium">{{ formatDateTime($activity->created_at) }}</div>
                                </div>
                            </div>
                        </x-slot:mobile>
                    </x-table.row>
                @empty
                    <x-empty-state 
                        icon="heroicon-o-document-text" 
                        title="Nenhum log encontrado" 
                        description="Não há registros de auditoria no sistema no momento." 
                    />
                @endforelse
            </div>
        </x-table>
        
        @if(method_exists($activities, 'links'))
            <div class="mt-4">
                {{ $activities->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
