<x-layouts.app>
    <x-page-header :title="'Detalhes do Log #' . $activity->id">
        <x-back-button fallback="{{ route('audit.index') }}" />
    </x-page-header>

    <div class="mt-6">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <!-- Painel de Informações -->
            <div class="xl:col-span-1 space-y-6">
                <x-card class="!p-0 overflow-hidden">
                    <div class="divide-y divide-neutral-100">
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider mb-1">MODEL AFETADO</p>
                            <div class="text-sm font-medium text-neutral-900">
                                @php
                                    $modelName = class_basename($activity->subject_type);
                                @endphp
                                @if(isset($subjectUrl) && $subjectUrl)
                                    <a href="{{ $subjectUrl }}" class="text-accent hover:underline flex items-center gap-1">
                                        {{ $modelName }} #{{ $activity->subject_id }}
                                        <x-heroicon-m-arrow-top-right-on-square class="w-3 h-3" />
                                    </a>
                                @else
                                    {{ $modelName }} #{{ $activity->subject_id }}
                                @endif
                            </div>
                        </div>
                        
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider mb-1">AÇÃO REALIZADA</p>
                            <div>
                                @php
                                    $actionTranslations = ['created' => 'Criado', 'updated' => 'Atualizado', 'deleted' => 'Excluído', 'item_deleted' => 'Item excluído', 'restored' => 'Restaurado', 'forceDeleted' => 'Excluído Permanentemente'];
                                    $actionName = $actionTranslations[$activity->description] ?? ucfirst($activity->description);
                                    $actionColors = ['created' => 'green', 'updated' => 'blue', 'deleted' => 'red', 'item_deleted' => 'red', 'restored' => 'yellow', 'forceDeleted' => 'rose'];
                                    $color = $actionColors[$activity->description] ?? 'neutral';
                                @endphp
                                <x-badge :color="$color" size="sm">{{ $actionName }}</x-badge>
                            </div>
                        </div>
                        
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider mb-1">DATA / HORA</p>
                            <div class="text-sm font-medium text-neutral-900">{{ formatDateTime($activity->created_at) }}</div>
                        </div>
                        
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider mb-3">CAUSADOR</p>
                            <div>
                                @if($activity->causer)
                                    <div class="flex items-center gap-3">
                                        <x-avatar :model="$activity->causer" size="md" />
                                        <div>
                                            <div class="text-sm font-medium text-neutral-900">{{ $activity->causer->name }}</div>
                                            <div class="text-xs text-neutral-500">{{ $activity->causer->email ?? 'Usuário #'.$activity->causer_id }}</div>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-3">
                                        <x-avatar icon="heroicon-o-cpu-chip" size="md" />
                                        <div>
                                            <div class="text-sm font-medium text-neutral-900">Sistema</div>
                                            <div class="text-xs text-neutral-500">Rotina Automática</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-card>
            </div>

            <!-- Painel de Alterações -->
            <div class="xl:col-span-2">
                
                @if($parsedChanges->isEmpty())
                    <x-empty-state 
                        icon="heroicon-o-code-bracket" 
                        title="Nenhum dado alterado"
                        description="Nenhum dado específico alterado ou registrado." 
                    />
                @else
                    <x-table>
                        <x-table.header class="{{ $gridClass }} hidden sm:grid">
                            <x-table.column>Campo</x-table.column>
                            @if($isDeleted)
                                <x-table.column>Dados do Registro</x-table.column>
                            @else
                                @if($hasOld)
                                    <x-table.column>Anterior</x-table.column>
                                @endif
                                <x-table.column>Atual</x-table.column>
                            @endif
                        </x-table.header>
                        <div class="divide-y divide-neutral-100">
                            @foreach($parsedChanges as $change)
                                <x-table.row class="{{ $gridClass }} hidden sm:grid">
                                    <x-table.cell class="font-medium text-neutral-900 whitespace-normal break-words">{{ $change['key'] }}</x-table.cell>
                                    @if($isDeleted)
                                        <x-table.cell class="text-neutral-600 font-medium break-all">
                                            @if($change['old'] !== '-')
                                                {{ $change['old'] }}
                                            @else
                                                <span class="text-neutral-400 font-normal">-</span>
                                            @endif
                                        </x-table.cell>
                                    @else
                                        @if($hasOld)
                                            <x-table.cell class="text-red-600 font-medium break-all">
                                                @if($change['old'] !== '-')
                                                    {{ $change['old'] }}
                                                @else
                                                    <span class="text-neutral-400 font-normal">-</span>
                                                @endif
                                            </x-table.cell>
                                        @endif
                                        <x-table.cell class="text-green-600 font-medium break-all">
                                            @if($change['new'] !== '-')
                                                {{ $change['new'] }}
                                            @else
                                                <span class="text-neutral-400 font-normal">-</span>
                                            @endif
                                        </x-table.cell>
                                    @endif
                                    
                                    <x-slot:mobile>
                                        <div class="font-medium text-neutral-900 mb-2 whitespace-normal break-words">{{ $change['key'] }}</div>
                                        @if($isDeleted)
                                            <div class="text-neutral-600 font-medium break-all text-sm">
                                                <span class="text-neutral-400 font-normal block text-xs mb-0.5">Dado:</span>
                                                @if($change['old'] !== '-')
                                                    {{ $change['old'] }}
                                                @else
                                                    <span class="text-neutral-400 font-normal">-</span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="grid {{ $hasOld ? 'grid-cols-2' : 'grid-cols-1' }} gap-2 text-sm">
                                                @if($hasOld)
                                                    <div class="text-red-600 font-medium break-all">
                                                        <span class="text-neutral-400 font-normal block text-xs mb-0.5">Anterior:</span>
                                                        @if($change['old'] !== '-')
                                                            {{ $change['old'] }}
                                                        @else
                                                            <span class="text-neutral-400 font-normal">-</span>
                                                        @endif
                                                    </div>
                                                @endif
                                                <div class="text-green-600 font-medium break-all">
                                                    <span class="text-neutral-400 font-normal block text-xs mb-0.5">Atual:</span>
                                                    @if($change['new'] !== '-')
                                                        {{ $change['new'] }}
                                                    @else
                                                        <span class="text-neutral-400 font-normal">-</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </x-slot:mobile>
                                </x-table.row>
                            @endforeach
                        </div>
                    </x-table>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
