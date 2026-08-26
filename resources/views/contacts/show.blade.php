<x-layouts.app>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('contacts.index') }}">Contatos</x-breadcrumbs.item>
            <x-breadcrumbs.item>{{ $contact->name }}</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header title="Detalhes do Contato">
        <x-back-button fallback="{{ route('contacts.index') }}" />

        <x-modal.delete
            action="{{ route('contacts.destroy', $contact) }}"
            item-name="o contato"
            item-desc="{{ $contact->name }}"
            title="Excluir Contato"
        />

        <x-button color="outline" href="{{ route('contacts.edit', $contact) }}" class="bg-white flex-1 sm:flex-initial">
            <x-heroicon-o-pencil-square class="size-4" />
            <span class="whitespace-nowrap">Editar</span>
        </x-button>
    </x-page-header>

    <x-card class="mb-4">
        <div class="flex items-center gap-4 sm:gap-6">
            <x-avatar :model="$contact" size="2xl"/>
            
            <div class="flex flex-col gap-2">
                <h2 class="text-2xl font-bold text-neutral-900">{{ $contact->name }}</h2>
                @if($contact->relationship_category)
                    <div>
                        <x-badge color="accent" size="sm">
                            {{ $contact->relationship_category }}
                        </x-badge>
                    </div>
                @endif
            </div>
        </div>

        @if($contact->birthdate)
            <div class="flex items-center gap-2 text-sm text-neutral-600 mt-2">
                {{ $contact->birthdate->translatedFormat('d \d\e F \d\e Y') }}
            </div>
        @endif
    </x-card>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <x-card class="h-full flex flex-col">
            <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-6 shrink-0">Telefones</h3>
            @if(!empty($contact->phones))
                <div class="overflow-y-auto max-h-[300px] pr-2 -mr-2">
                    <div class="divide-y divide-neutral-100">
                        @foreach($contact->phones as $phone)
                            @php
                                $label = is_array($phone) && !empty($phone['label']) ? $phone['label'] : 'Principal';
                                $value = is_array($phone) ? ($phone['value'] ?? '') : $phone;
                            @endphp
                            <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4 sm:gap-6 py-3 first:pt-0 last:pb-0">
                                <span class="text-sm font-medium text-neutral-400 sm:w-24 shrink-0">{{ $label }}</span>
                                <span class="text-[15px] text-neutral-800 break-all">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="text-sm text-neutral-400 italic">Nenhum telefone cadastrado.</p>
            @endif
        </x-card>

        <x-card class="h-full flex flex-col">
            <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-6 shrink-0">E-mails</h3>
            @if(!empty($contact->emails))
                <div class="overflow-y-auto max-h-[300px] pr-2 -mr-2">
                    <div class="divide-y divide-neutral-100">
                        @foreach($contact->emails as $email)
                            @php
                                $label = is_array($email) && !empty($email['label']) ? $email['label'] : 'Principal';
                                $value = is_array($email) ? ($email['value'] ?? '') : $email;
                            @endphp
                            <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4 sm:gap-6 py-3 first:pt-0 last:pb-0">
                                <span class="text-sm font-medium text-neutral-400 sm:w-24 shrink-0">{{ $label }}</span>
                                <span class="text-[15px] text-neutral-800 break-all">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="text-sm text-neutral-400 italic">Nenhum e-mail cadastrado.</p>
            @endif
        </x-card>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <x-contacts.balance-card :contact="$contact" />

        <!-- Grupos -->
        <x-card class="h-full">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest shrink-0">Grupos</h3>
                <x-modal.trigger name="sync-groups">
                    <x-button type="button" color="outline" class="bg-white">
                        Gerenciar
                    </x-button>
                </x-modal.trigger>
            </div>
            
            @if($contact->groups->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach($contact->groups as $group)
                        <x-badge color="accent">{{ $group->name }}</x-badge>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-neutral-400 italic">Não pertence a nenhum grupo.</p>
            @endif

            <x-modal name="sync-groups" :title="'Grupos de ' . $contact->name" confirmVariant="">
                <form action="{{ route('contacts.groups.sync', $contact) }}" method="POST">
                    @csrf
                    <div class="space-y-2 mb-6 max-h-[400px] overflow-y-auto p-1">
                        @forelse($allGroups as $group)
                            <label class="flex items-center gap-3 p-3 rounded-lg border border-neutral-200 hover:bg-neutral-50 cursor-pointer transition-colors">
                                <input type="checkbox" name="group_ids[]" value="{{ $group->id }}" 
                                    @checked($contact->groups->contains($group->id))
                                    class="rounded border-neutral-300 text-accent focus:ring-accent">
                                <span class="text-sm font-medium text-neutral-700">{{ $group->name }}</span>
                            </label>
                        @empty
                            <div class="flex flex-col items-center justify-center py-6 gap-3">
                                <p class="text-sm text-neutral-500 text-center">Nenhum grupo criado ainda.</p>
                                <x-button color="outline" href="{{ route('contacts.groups.index') }}" size="sm">
                                    <x-heroicon-o-plus class="size-4" />
                                    Criar Grupo
                                </x-button>
                            </div>
                        @endforelse
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-button type="submit">Salvar</x-button>
                    </div>
                </form>
            </x-modal>
        </x-card>
    </div>


    <x-card class="mb-4">
        <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-6">Notas</h3>
        @if($contact->notes)
            <div class="markdown-content text-[15px] text-neutral-700">
                <x-markdown :content="$contact->notes" />
            </div>
        @else
            <p class="text-sm text-neutral-400 italic">Nenhuma anotação.</p>
        @endif
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_350px] gap-6 items-start mt-8">
        <x-activity-log :model="$contact" class="!mt-0" />
        <x-metadata-card :model="$contact" class="w-full mb-0" />
    </div>
</x-layouts.app>
