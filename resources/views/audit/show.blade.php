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
                                @if($hasRevertibleFields)
                                    <x-table.column align="right">Ação</x-table.column>
                                @endif
                            @endif
                        </x-table.header>
                        <div class="divide-y divide-neutral-100">
                            @foreach($parsedChanges as $change)
                                @php
                                    $revertModalName = 'revert-field-' . $activity->id . '-' . $change['key'];
                                @endphp
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
                                        @if($hasRevertibleFields)
                                            <x-table.cell align="right">
                                                @if($change['revertible'])
                                                    <x-modal.trigger :name="$revertModalName">
                                                        <x-button type="button" color="outline" class="min-h-8! py-1! px-2.5! text-xs! bg-white">
                                                            Reverter
                                                        </x-button>
                                                    </x-modal.trigger>
                                                @elseif($change['already_reverted'])
                                                    <span class="text-xs text-neutral-400">Revertido</span>
                                                @endif
                                            </x-table.cell>
                                        @endif
                                    @endif
                                    
                                    <x-slot:mobile>
                                        <div class="flex items-center justify-between gap-2 mb-2">
                                            <div class="font-medium text-neutral-900 whitespace-normal break-words">{{ $change['key'] }}</div>
                                            @if($change['revertible'])
                                                <x-modal.trigger :name="$revertModalName">
                                                    <x-button type="button" color="outline" class="min-h-8! py-1! px-2.5! text-xs! bg-white">
                                                        Reverter
                                                    </x-button>
                                                </x-modal.trigger>
                                            @elseif($change['already_reverted'])
                                                <span class="text-xs text-neutral-400">Revertido</span>
                                            @endif
                                        </div>
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

                                @if($change['revertible'])
                                    <x-modal
                                        :name="$revertModalName"
                                        :title="'Reverter campo: ' . $change['key']"
                                        confirmVariant="warning">
                                        <x-slot:content>
                                            <div class="space-y-3 text-left">
                                                <p>Deseja restaurar o campo <strong>{{ $change['key'] }}</strong> para o valor anterior registrado neste evento?</p>

                                                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3 space-y-2 text-xs">
                                                    <div>
                                                        <span class="text-neutral-500 block">Valor atual no registro:</span>
                                                        <span class="font-medium text-neutral-900 break-all">{{ $change['current'] }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-neutral-500 block">Será restaurado para:</span>
                                                        <span class="font-medium text-green-700 break-all">{{ $change['old'] }}</span>
                                                    </div>
                                                </div>

                                                @if($change['has_conflict'])
                                                    <div class="rounded-md bg-amber-50 p-3 border border-amber-200 text-amber-800 text-xs">
                                                        <strong>Atenção:</strong> Este campo foi alterado novamente após este evento. Ao confirmar, o valor atual será substituído.
                                                    </div>
                                                @endif
                                            </div>
                                        </x-slot:content>

                                        <form action="{{ route('audit.revert', $activity) }}" method="POST" class="m-0">
                                            @csrf
                                            <input type="hidden" name="fields[]" value="{{ $change['key'] }}">
                                            <x-button type="submit" class="w-full sm:w-auto">
                                                Confirmar Reversão
                                            </x-button>
                                        </form>
                                    </x-modal>
                                @endif
                            @endforeach
                        </div>
                    </x-table>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
