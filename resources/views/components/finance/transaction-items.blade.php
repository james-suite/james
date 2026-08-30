@props(['tags'])

<x-card class="flex flex-col">
    <div class="flex justify-between items-center mb-6 shrink-0">
        <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest">Itens da Transação (Opcional)</h3>
        <x-button type="button" @click="addItem" color="accent-ghost" class="text-xs! py-1! px-2!">
            <x-heroicon-o-plus class="size-3" /> Adicionar
        </x-button>
    </div>
    
    <div class="flex flex-col gap-3">
        <!-- Table Header -->
        <div class="hidden sm:grid sm:grid-cols-[minmax(0,1fr)_6rem_7rem_7rem_6.25rem] gap-2 items-center text-xs font-bold text-neutral-400 uppercase tracking-widest px-1 mb-1" x-show="items.length > 0" style="display: none;">
            <div>Descrição do Item</div>
            <div>Qtd</div>
            <div>Valor (R$)</div>
            <div class="text-right">Total</div>
            <div></div>
        </div>
        
        <template x-for="(item, index) in items" :key="item._key">
            <div class="flex flex-col gap-2 sm:grid sm:grid-cols-[minmax(0,1fr)_6rem_7rem_7rem_6.25rem] sm:px-1 sm:items-start py-2 sm:py-1 border-b border-neutral-100 sm:border-0 pb-3 sm:pb-1 mb-1 sm:mb-0 last:border-0">
                <template x-if="item.id">
                    <input type="hidden" x-bind:name="'items['+index+'][id]'" x-bind:value="item.id" />
                </template>
                <div class="w-full sm:min-w-0">
                    <x-form-input x-model="item.description" ::name="'items['+index+'][description]'" placeholder="Descrição do item" />
                </div>
                <div class="flex gap-2 w-full items-start sm:contents">
                    <div class="w-24 shrink-0">
                        <x-form-input x-data @input="$event.target.value = $event.target.value.replace(/[^0-9.,]/g, '')" inputmode="decimal" x-model="item.quantity" ::name="'items['+index+'][quantity]'" placeholder="Qtd" />
                    </div>
                    <div class="flex-1 sm:w-28 shrink-0">
                        <x-form-input :currency="true" :allow-negative="true" x-model="item.unit_price" ::name="'items['+index+'][unit_price]'" placeholder="R$ 0,00" />
                    </div>
                    <div class="hidden sm:flex sm:w-28 h-11 shrink-0 items-center justify-end text-sm font-semibold text-neutral-900 tabular-nums" x-text="'R$ ' + formatMoney(itemTotal(item))"></div>
                    <div class="flex items-center gap-1 shrink-0 px-1">
                    <x-tags-selector 
                        x-name="`items[${index}][tags][]`" 
                        :options="$tags" 
                        x-value="item.tags ? Object.values(item.tags).map(Number) : []"
                        x-primary-value="item.primary_tag_id ? Number(item.primary_tag_id) : null"
                        @tags-changed="item.tags = $event.detail.ids; item.primary_tag_id = $event.detail.primaryId; items = [...items]"
                    >
                        <x-slot:trigger>
                            <x-tooltip text="Gerenciar tags">
                                <button type="button" class="relative flex size-11 cursor-pointer items-center justify-center rounded-xl text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-accent" aria-label="Gerenciar tags">
                                    <x-heroicon-o-tag class="size-5" />
                                    <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-accent text-[9px] font-bold text-white shadow-sm ring-2 ring-white" x-text="selectedIds.length" x-show="selectedIds.length > 0"></span>
                                </button>
                            </x-tooltip>
                        </x-slot:trigger>
                    </x-tags-selector>

                    <x-tooltip text="Remover item" class="shrink-0">
                        <x-button type="button" color="danger-ghost" class="size-11! shrink-0 p-0! hover:bg-red-50" aria-label="Remover item" @click="removeItem(index)">
                            <x-heroicon-o-trash class="size-5!" />
                        </x-button>
                    </x-tooltip>
                    </div>
                </div>
                <p class="sm:hidden text-right text-sm font-semibold text-neutral-700 tabular-nums" aria-live="polite">
                    Total: <span x-text="'R$ ' + formatMoney(itemTotal(item))"></span>
                </p>
            </div>
        </template>
        <p class="text-sm text-neutral-400 italic mb-2" x-show="items.length === 0">Nenhum item adicionado nesta transação.</p>
    </div>
</x-card>
