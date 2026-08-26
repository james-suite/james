<x-layouts.app>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('contacts.index') }}">Contatos</x-breadcrumbs.item>
            <x-breadcrumbs.item href="{{ route('contacts.show', $contact) }}">{{ $contact->name }}</x-breadcrumbs.item>
            <x-breadcrumbs.item>Editar</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header title="Editar Contato" mobileBottom>
        <x-form-actions fallback="{{ route('contacts.show', $contact) }}" form="edit-contact-form" />
    </x-page-header>

    <form id="edit-contact-form" action="{{ route('contacts.update', $contact) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <x-card class="mb-4">
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6">

                <x-form-image-cropper
                    name="avatar"
                    :model="$contact"
                    :preview-url="$contact->avatar"
                    label-add="Adicionar foto"
                    label-change="Alterar foto"
                    :remove-modal-name="$contact->getFirstMedia('avatar') ? 'remove-avatar-' . $contact->id : null"
                />
                
                <div class="flex-1 w-full flex flex-col gap-4">
                    <div>
                        <x-form-input name="name" label="Nome" :value="old('name', $contact->name)" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-form-combobox 
                                name="relationship_category" 
                                label="Categoria" 
                                :value="old('relationship_category', $contact->relationship_category)" 
                                :options="$categories" 
                            />
                        </div>
                        <div>
                            <x-form-input type="date" name="birthdate" label="Data de Nascimento" :value="old('birthdate', $contact->birthdate?->format('Y-m-d'))" />
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <x-form-key-value-repeater 
                name="phones" 
                title="Telefones" 
                :items="$phones" 
                value-placeholder="Número" 
                empty-message="Nenhum telefone cadastrado." 
            />

            <x-form-key-value-repeater 
                name="emails" 
                title="E-mails" 
                :items="$emails" 
                value-placeholder="Endereço de e-mail" 
                empty-message="Nenhum e-mail cadastrado." 
            />
        </div>

        <x-form-markdown-editor
            name="notes"
            label="Notas"
            placeholder="Anotações sobre o contato..."
            :value="old('notes', $contact->notes)"
        />
    </form>

    <x-form-actions fallback="{{ route('contacts.show', $contact) }}" form="edit-contact-form" mobile />

    <x-modal 
        name="remove-avatar-{{ $contact->id }}"
        title="Remover foto de perfil" 
        message="Tem certeza que deseja remover a foto atual? Esta ação apagará a imagem permanentemente." 
        confirmVariant="danger">
        <form action="{{ route('contacts.destroy-avatar', $contact) }}" method="POST" class="m-0">
            @csrf
            @method('DELETE')
            <x-button type="submit" color="red" class="w-full sm:w-auto cursor-pointer">
                Sim, remover
            </x-button>
        </form>
    </x-modal>
</x-layouts.app>
