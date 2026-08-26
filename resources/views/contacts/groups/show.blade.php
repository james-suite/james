<x-layouts.app>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('contacts.index') }}">Contatos</x-breadcrumbs.item>
            <x-breadcrumbs.item href="{{ route('contacts.groups.index') }}">Grupos de Contato</x-breadcrumbs.item>
            <x-breadcrumbs.item>{{ $group->name }}</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header :title="$group->name" icon="heroicon-o-tag">
        <x-modal.trigger name="delete-group-{{ $group->id }}">
            <x-button color="danger-outline" class="bg-white">
                <x-heroicon-o-trash class="size-4" />
                <span class="hidden sm:inline">Excluir</span>
            </x-button>
        </x-modal.trigger>

        <x-button color="outline" href="{{ route('contacts.groups.edit', $group) }}" class="bg-white flex-1 sm:flex-initial">
            <x-heroicon-o-pencil class="size-4" />
            <span class="whitespace-nowrap">Editar</span>
        </x-button>
    </x-page-header>

    <!-- Delete Modal -->
    <x-modal name="delete-group-{{ $group->id }}" title="Excluir Grupo" :message="'Tem certeza que deseja excluir o grupo \'' . $group->name . '\'? Os contatos não serão excluídos.'" confirmVariant="danger">
        <form action="{{ route('contacts.groups.destroy', $group) }}" method="POST" class="m-0">
            @csrf
            @method('DELETE')
            <x-button type="submit" color="red" class="w-full sm:w-auto">Excluir</x-button>
        </form>
    </x-modal>

    <div class="space-y-6">
        @if($group->notes)
            <x-card>
                <div class="px-6 py-4 border-b border-neutral-100">
                    <h3 class="text-lg font-semibold text-neutral-900">Notas</h3>
                </div>
                <div class="p-6 prose prose-neutral max-w-none prose-sm">
                    <x-markdown :content="$group->notes" />
                </div>
            </x-card>
        @endif

        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-neutral-900">Membros do Grupo</h3>
                <span class="text-sm text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full">
                    {{ $group->contacts->count() }} {{ $group->contacts->count() === 1 ? 'membro' : 'membros' }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($group->contacts as $contact)
                    <x-card href="{{ route('contacts.show', $contact) }}" size="sm" class="flex items-center gap-4 relative group/card">
                        <x-avatar :model="$contact" size="lg" />
                        
                        <div class="overflow-hidden flex-1">
                            <h3 class="font-semibold text-neutral-900 truncate">{{ $contact->name }}</h3>
                            <div class="mt-1">
                                <x-badge color="accent" size="sm">
                                    {{ $contact->relationship_category ?: 'Sem categoria' }}
                                </x-badge>
                            </div>
                        </div>

                        <div class="text-neutral-400 group-hover/card:text-accent transition-colors">
                            <x-heroicon-o-chevron-right class="size-5" />
                        </div>
                    </x-card>
                @empty
                    <div class="col-span-full bg-white rounded-xl border border-neutral-200">
                        <x-empty-state 
                            icon="heroicon-o-users" 
                            message="Este grupo ainda não possui membros." 
                        />
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_350px] gap-6 items-start mt-8">
        <x-activity-log :model="$group" class="!mt-0" />
        <x-metadata-card :model="$group" class="w-full mb-0" />
    </div>
</x-layouts.app>
