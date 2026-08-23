<x-layouts.app>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('contacts.index') }}">Contatos</x-breadcrumbs.item>
            <x-breadcrumbs.item>Lixeira</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header 
        title="Lixeira" 
        description="Contatos excluídos. Eles podem ser restaurados ou excluídos permanentemente." 
    >
        <x-back-button fallback="{{ route('contacts.index') }}" class="w-full sm:w-auto" />
    </x-page-header>

    <x-filter-bar 
        action="{{ route('contacts.trashed') }}" 
        searchPlaceholder="Buscar na lixeira..." 
        :filters="['search', 'category']">
        
        <div class="w-full sm:w-auto">
            <select name="category" onchange="this.form.submit()" 
                    class="w-full sm:w-auto bg-transparent border-0 py-2 sm:py-1.5 pl-3 pr-8 text-sm text-neutral-600 focus:outline-none focus:ring-0 cursor-pointer">
                <option value="">Todas categorias</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
    </x-filter-bar>

    <x-table class="lg:mb-8"
         x-data="{
             selectedContactId: null,
             selectedContactName: '',
             openRestore(id, name) {
                 this.selectedContactId = id;
                 this.selectedContactName = name;
                 $dispatch('modal-open', 'restore-contact');
             },
             openForceDelete(id, name) {
                 this.selectedContactId = id;
                 this.selectedContactName = name;
                 $dispatch('modal-open', 'force-delete-contact');
             }
         }">
        @if($contacts->isNotEmpty())
            {{-- Header - Desktop --}}
            <x-table.header class="hidden sm:grid sm:grid-cols-[2fr_1fr_1.5fr]">
                <x-table.column>Contato</x-table.column>
                <x-table.column>Data da Exclusão</x-table.column>
                <x-table.column align="right">Ações</x-table.column>
            </x-table.header>
        @endif

        <x-table.body>
            @forelse($contacts as $contact)
                <x-table.row class="hidden sm:grid sm:grid-cols-[2fr_1fr_1.5fr]">
                    <x-table.cell>
                        <div class="flex items-center gap-3 w-full">
                            <x-avatar :model="$contact" size="lg" class="shrink-0 grayscale opacity-80" />
                            <div class="overflow-hidden">
                                <div class="font-medium text-neutral-900 truncate">{{ $contact->name }}</div>
                                @if($contact->relationship_category)
                                    <div class="mt-1">
                                        <x-badge color="accent" size="sm">{{ $contact->relationship_category }}</x-badge>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </x-table.cell>

                    <x-table.cell>
                        <span class="text-sm text-neutral-500">
                            {{ $contact->deleted_at->formatDateTime() }}
                        </span>
                    </x-table.cell>

                    <x-table.cell align="right">
                        <div class="flex justify-end gap-2 w-full">
                            <x-button type="button" color="outline" class="bg-white hover:bg-neutral-50 text-neutral-600 border-neutral-300" @click="openRestore({{ $contact->id }}, {{ Js::from($contact->name) }})">
                                <x-heroicon-o-arrow-uturn-left class="size-4" />
                                Restaurar
                            </x-button>

                            <x-button type="button" color="outline" class="bg-white hover:bg-red-50 text-red-600 border-red-200 interaction-delete" @click="openForceDelete({{ $contact->id }}, {{ Js::from($contact->name) }})">
                                <x-heroicon-o-trash class="size-4" />
                                Excluir
                            </x-button>
                        </div>
                    </x-table.cell>

                    <x-slot name="mobile">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0 flex items-center gap-3">
                                <x-avatar :model="$contact" size="lg" class="shrink-0 grayscale opacity-80" />
                                <div class="overflow-hidden">
                                    <h3 class="text-base font-semibold text-neutral-900 leading-tight mb-1 truncate">
                                        {{ $contact->name }}
                                    </h3>
                                    <div class="flex flex-col gap-1 text-sm text-neutral-500 mt-1">
                                        @if($contact->relationship_category)
                                            <div class="truncate">
                                                <x-badge color="accent" size="sm">{{ $contact->relationship_category }}</x-badge>
                                            </div>
                                        @endif
                                        <span class="text-xs">Excluído em {{ $contact->deleted_at->formatShort() }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0">
                                <x-dropdown position="bottom-end" accent contentClass="min-w-max">
                                    <x-slot name="trigger">
                                        <button type="button" class="cursor-pointer rounded-md border border-neutral-300 p-2 transition duration-150 ease-in-out hover:bg-neutral-100">
                                            <x-heroicon-o-ellipsis-horizontal class="size-5" />
                                        </button>
                                    </x-slot>

                                    <x-slot name="content">
                                        <button type="button" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100 cursor-pointer" @click="openRestore({{ $contact->id }}, {{ Js::from($contact->name) }})">
                                            <x-heroicon-o-arrow-uturn-left class="size-5 shrink-0" />
                                            <span class="whitespace-nowrap">Restaurar</span>
                                        </button>

                                        <button type="button" class="button-scale interaction-delete w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-neutral-100 cursor-pointer" @click="openForceDelete({{ $contact->id }}, {{ Js::from($contact->name) }})">
                                            <x-heroicon-o-trash class="size-5 shrink-0" />
                                            <span class="whitespace-nowrap">Excluir Permanentemente</span>
                                        </button>
                                    </x-slot>
                                </x-dropdown>
                            </div>
                        </div>
                    </x-slot>
                </x-table.row>
            @empty
                <x-empty-state 
                    icon="heroicon-o-trash" 
                    title="Nenhum contato excluído" 
                    description="Não há contatos excluídos recentemente." 
                />
            @endforelse
        </x-table.body>

        <x-modal.restore
            modal-name="restore-contact"
            item-name="o contato"
            dynamic-item-name="selectedContactName"
            alpine-action="'{{ route('contacts.restore', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedContactId)"
        />

        <x-modal.delete
            modal-name="force-delete-contact"
            item-name="o contato"
            dynamic-item-name="selectedContactName"
            permanent="true"
            alpine-action="'{{ route('contacts.force', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedContactId)"
        />
    </x-table>

    @if($contacts->hasPages())
        <div class="mt-6">
            {{ $contacts->links() }}
        </div>
    @endif
</x-layouts.app>
