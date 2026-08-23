<x-layouts.financial>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('financial.accounts.index') }}">Contas Financeiras</x-breadcrumbs.item>
            <x-breadcrumbs.item>Lixeira</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header 
        title="Lixeira" 
        description="Contas financeiras excluídas. Elas podem ser restauradas ou excluídas permanentemente." 
    >
        <x-back-button fallback="{{ route('financial.accounts.index') }}" class="w-full sm:w-auto" />
    </x-page-header>

    <x-filter-bar 
        action="{{ route('financial.accounts.trashed') }}" 
        searchPlaceholder="Buscar na lixeira..." 
        :filters="['search']">
    </x-filter-bar>

    <x-table class="lg:mb-8"
         x-data="{
             selectedAccountId: null,
             selectedAccountName: '',
             openRestore(id, name) {
                 this.selectedAccountId = id;
                 this.selectedAccountName = name;
                 $dispatch('modal-open', 'restore-account');
             },
             openForceDelete(id, name) {
                 this.selectedAccountId = id;
                 this.selectedAccountName = name;
                 $dispatch('modal-open', 'force-delete-account');
             }
         }">
        @if($accounts->isNotEmpty())
            {{-- Header - Desktop --}}
            <x-table.header class="hidden sm:grid sm:grid-cols-[2fr_1fr_1.5fr]">
                <x-table.column>Conta</x-table.column>
                <x-table.column>Data da Exclusão</x-table.column>
                <x-table.column align="right">Ações</x-table.column>
            </x-table.header>
        @endif

        <x-table.body>
            @forelse($accounts as $account)
                <x-table.row class="hidden sm:grid sm:grid-cols-[2fr_1fr_1.5fr]">
                    <x-table.cell>
                        <div class="flex items-center gap-3 w-full">
                            <div class="shrink-0 flex items-center justify-center size-10 rounded-full bg-neutral-100 text-neutral-400 grayscale opacity-80">
                                <x-heroicon-o-building-library class="size-5" />
                            </div>
                            <div class="overflow-hidden">
                                <div class="font-medium text-neutral-900 truncate">{{ $account->name }}</div>
                                <div class="mt-1">
                                    <x-badge color="accent" size="sm">{{ $account->type->label() }}</x-badge>
                                </div>
                            </div>
                        </div>
                    </x-table.cell>

                    <x-table.cell>
                        <span class="text-sm text-neutral-500">
                            {{ $account->deleted_at->formatDateTime() }}
                        </span>
                    </x-table.cell>

                    <x-table.cell align="right">
                        <div class="flex justify-end gap-2 w-full">
                            <x-button type="button" color="outline" class="bg-white hover:bg-neutral-50 text-neutral-600 border-neutral-300" @click="openRestore({{ $account->id }}, {{ Js::from($account->name) }})">
                                <x-heroicon-o-arrow-uturn-left class="size-4" />
                                Restaurar
                            </x-button>

                            <x-button type="button" color="outline" class="bg-white hover:bg-red-50 text-red-600 border-red-200 interaction-delete" @click="openForceDelete({{ $account->id }}, {{ Js::from($account->name) }})">
                                <x-heroicon-o-trash class="size-4" />
                                Excluir
                            </x-button>
                        </div>
                    </x-table.cell>

                    <x-slot name="mobile">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0 flex items-center gap-3">
                                <div class="shrink-0 flex items-center justify-center size-10 rounded-full bg-neutral-100 text-neutral-400 grayscale opacity-80">
                                    <x-heroicon-o-building-library class="size-5" />
                                </div>
                                <div class="overflow-hidden">
                                    <h3 class="text-base font-semibold text-neutral-900 leading-tight mb-1 truncate">
                                        {{ $account->name }}
                                    </h3>
                                    <div class="flex flex-col gap-1 text-sm text-neutral-500 mt-1">
                                        <div class="truncate">
                                            <x-badge color="accent" size="sm">{{ $account->type->label() }}</x-badge>
                                        </div>
                                        <span class="text-xs">Excluído em {{ $account->deleted_at->formatShort() }}</span>
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
                                        <button type="button" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100 cursor-pointer" @click="openRestore({{ $account->id }}, {{ Js::from($account->name) }})">
                                            <x-heroicon-o-arrow-uturn-left class="size-5 shrink-0" />
                                            <span class="whitespace-nowrap">Restaurar</span>
                                        </button>

                                        <button type="button" class="button-scale interaction-delete w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-neutral-100 cursor-pointer" @click="openForceDelete({{ $account->id }}, {{ Js::from($account->name) }})">
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
                    title="Nenhuma conta excluída" 
                    description="Não há contas excluídas recentemente na lixeira." 
                />
            @endforelse
        </x-table.body>

        <x-modal.restore
            modal-name="restore-account"
            item-name="a conta"
            dynamic-item-name="selectedAccountName"
            alpine-action="'{{ route('financial.accounts.restore', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedAccountId)"
        >
            <x-slot:content>
                Tem certeza que deseja restaurar a conta "<span class="font-medium text-neutral-900" x-text="selectedAccountName"></span>"? Ela voltará a aparecer nos seus saldos e faturamentos.
            </x-slot:content>
        </x-modal.restore>

        <x-modal.delete
            modal-name="force-delete-account"
            item-name="a conta"
            dynamic-item-name="selectedAccountName"
            permanent="true"
            alpine-action="'{{ route('financial.accounts.forceDestroy', 'REPLACE_ID') }}'.replace('REPLACE_ID', selectedAccountId)"
        >
            <x-slot:content>
                <p class="mb-3">Tem certeza que deseja excluir a conta "<span class="font-medium text-neutral-900" x-text="selectedAccountName"></span>" permanentemente? Esta ação é irreversível e todos os dados serão perdidos.</p>
                <div class="rounded-md bg-amber-50 p-3 border border-amber-200 text-left">
                    <div class="flex">
                        <div class="shrink-0">
                            <x-heroicon-m-exclamation-triangle class="size-5 text-amber-400" />
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800">Atenção aos vínculos</h3>
                            <div class="mt-1 text-sm text-amber-700">
                                <p>A exclusão não será permitida se a conta possuir cartões de crédito, transações ou recorrências ativas ou arquivadas.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </x-slot:content>
        </x-modal.delete>
    </x-table>

    @if($accounts->hasPages())
        <div class="mt-6">
            {{ $accounts->links() }}
        </div>
    @endif
</x-layouts.financial>
