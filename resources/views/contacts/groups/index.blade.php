<x-layouts.app>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('contacts.index') }}">Contatos</x-breadcrumbs.item>
            <x-breadcrumbs.item>Grupos de Contato</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header title="Grupos de Contato" icon="heroicon-o-tag">
        <x-button href="{{ route('contacts.groups.create') }}">
            <x-heroicon-o-plus class="size-4" />
            Novo Grupo
        </x-button>
    </x-page-header>

    <x-filter-bar 
        action="{{ route('contacts.groups.index') }}" 
        searchPlaceholder="Buscar grupo..." 
        :filters="['search']">
    </x-filter-bar>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($groups as $group)
            <x-card href="{{ route('contacts.groups.show', $group) }}" size="sm" class="t-learn flex items-center gap-4 relative group">
                <div class="shrink-0 flex items-center justify-center border rounded-md font-medium bg-neutral-200 border-neutral-300 text-neutral-700 w-12 h-12 text-lg">
                    <x-heroicon-o-tag class="w-[65%] h-[65%] text-neutral-400" />
                </div>
                
                <div class="overflow-hidden flex-1">
                    <h3 class="font-semibold text-neutral-900 truncate">{{ $group->name }}</h3>
                    <div class="mt-1 text-sm text-neutral-500">
                        {{ $group->contacts_count }} {{ $group->contacts_count === 1 ? 'membro' : 'membros' }}
                    </div>
                </div>

                <div class="text-neutral-400 group-hover:text-accent transition-colors">
                    <x-heroicon-o-chevron-right class="t-learn-chevron size-5" />
                </div>
            </x-card>
        @empty
            <div class="col-span-full bg-white rounded-xl border border-neutral-200">
                <x-empty-state 
                    icon="heroicon-o-tag" 
                    message="Nenhum grupo encontrado." 
                />
            </div>
        @endforelse
    </div>

    @if ($groups->hasPages())
        <div class="mt-6 pb-6">
            {{ $groups->links() }}
        </div>
    @endif
</x-layouts.app>
