<x-layouts.app>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('contacts.index') }}">Contatos</x-breadcrumbs.item>
            <x-breadcrumbs.item href="{{ route('contacts.groups.index') }}">Grupos de Contato</x-breadcrumbs.item>
            <x-breadcrumbs.item>Novo Grupo</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header title="Novo Grupo" mobileBottom>
        <x-form-actions fallback="{{ route('contacts.groups.index') }}" form="create-group-form" />
    </x-page-header>

    <form id="create-group-form" action="{{ route('contacts.groups.store') }}" method="POST">
        @csrf
        <x-card class="mb-4">
            <div class="mb-6">
                <x-form-input name="name" label="Nome do Grupo" :value="old('name')" placeholder="Ex: Futebol, Família, Trabalho..." required />
            </div>

            <div class="mb-6">
                <x-form-markdown-editor
                    name="notes"
                    label="Notas"
                    placeholder="Anotações sobre o grupo..."
                    :value="old('notes')"
                />
            </div>

        </x-card>

        <div x-data="{
            search: '',
            selectedIds: {{ Js::from(old('contact_ids', [])) }},
            toggleAll() {
                const visibleInputs = Array.from(document.querySelectorAll('input[name=\'contact_ids[]\']'))
                    .filter(el => el.closest('[x-show]').style.display !== 'none');
                    
                if (this.selectedIds.length === visibleInputs.length && visibleInputs.length > 0) {
                    this.selectedIds = [];
                } else {
                    this.selectedIds = visibleInputs.map(el => String(el.value));
                }
            }
        }">
            <h3 class="text-sm font-semibold text-neutral-700 mb-3 px-1">Membros do Grupo</h3>
            
            <div class="mb-4">
                <x-contacts.selection-bar search-placeholder="Buscar contatos pelo nome..." />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 ">
                @foreach($allContacts as $contact)
                    <x-contacts.selectable-card :contact="$contact" selected-model="selectedIds" x-show="search === '' || {{ Js::from(strtolower($contact->name)) }}.includes(search.toLowerCase())" />
                @endforeach
                
                <div class="col-span-full p-8 text-center text-neutral-500 bg-white rounded-xl border border-dashed border-neutral-200 shadow-sm" style="display: none;" x-show="!Array.from(document.querySelectorAll('[x-show]')).some(el => el.style.display !== 'none')">
                    Nenhum contato encontrado.
                </div>
            </div>
        </div>
    </form>

    <x-form-actions fallback="{{ route('contacts.groups.index') }}" form="create-group-form" mobile />
</x-layouts.app>
