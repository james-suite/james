<div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-start">


    <!-- Left Column: Main Data -->
    <div class="lg:col-span-8 flex flex-col gap-4 sm:gap-6 order-last lg:order-first">
        <!-- Dados Gerais -->
        <x-card>
            <div class="flex flex-col gap-4 sm:gap-6">
                <x-form-input label="Descrição" name="description" :value="old('description', isset($settlementGroup) ? $settlementGroup->description : '')" placeholder="Descrição" autofocus />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 items-start">
                    <div>
                        <div x-show="mode === 'equal'">
                            <x-form-input label="Valor Total (R$)" name="total_amount" :currency="true" placeholder="0,00" x-model="totalAmount" />
                        </div>
                        <div x-show="mode === 'exact'" style="display: none;">
                            <label class="block text-sm font-medium text-neutral-700 mb-1">Valor Total (R$)</label>
                            <div class="w-full border border-neutral-200 text-sm rounded-xl block py-2.5 px-4 bg-neutral-100 text-neutral-500 font-medium">
                                <span x-text="formatMoney(calculatedTotal)"></span>
                            </div>
                            <input type="hidden" name="total_amount" :value="calculatedTotal.toFixed(2)">
                        </div>
                    </div>
                    <div>
                        <x-form-input label="Data" name="date" type="date" :value="old('date', isset($settlementGroup) ? $settlementGroup->date->format('Y-m-d') : \Carbon\Carbon::today()->format('Y-m-d'))" />
                    </div>
                </div>
            </div>
        </x-card>

        <!-- Participantes -->
        <x-card>
            <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-6">Participantes</h3>

            <div class="space-y-3">
                <!-- Minha Parte -->
                <div class="flex items-center gap-4 p-4 rounded-xl border border-accent/30 bg-accent/5">
                    <div class="shrink-0 flex items-center justify-center w-10 h-10 rounded-md bg-accent/20 text-accent font-bold text-sm">
                        EU
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-semibold text-neutral-900">Minha Parte</span>
                        <template x-if="mode === 'equal'">
                            <p class="text-xs text-neutral-500 mt-0.5">Absorve o resto dos centavos</p>
                        </template>
                    </div>
                    <div class="w-36 shrink-0">
                        <template x-if="mode === 'equal'">
                            <div class="w-full border border-neutral-200 text-sm rounded-xl block py-2.5 px-4 bg-neutral-100 text-neutral-700 font-medium text-right">
                                <span x-text="formatMoney(calculatedMyAmount)"></span>
                            </div>
                        </template>
                        <template x-if="mode === 'exact'">
                            <x-form-input name="my_amount" :currency="true" placeholder="0,00" x-model="myAmount" />
                        </template>
                        <input x-show="mode === 'equal'" type="hidden" name="my_amount" :value="calculatedMyAmount.toFixed(2)">
                    </div>
                </div>

                <!-- Contatos -->
                <template x-for="(contact, index) in contacts" :key="contact.id">
                    <div class="flex items-center gap-4 p-4 rounded-xl border border-neutral-200 bg-white">
                        <div class="shrink-0">
                            <template x-if="contact.avatar_url">
                                <img :src="contact.avatar_url" :alt="contact.name" class="w-10 h-10 rounded-md object-cover border border-neutral-200">
                            </template>
                            <template x-if="!contact.avatar_url">
                                <div class="flex items-center justify-center w-10 h-10 rounded-md bg-neutral-200 border border-neutral-300 text-neutral-700 font-medium text-sm" x-text="contact.initials"></div>
                            </template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-semibold text-neutral-900 truncate block" x-text="contact.name"></span>
                        </div>
                        <div class="w-36 shrink-0">
                            <input type="hidden" :name="'contacts[' + index + '][id]'" :value="contact.id">
                            <template x-if="mode === 'equal'">
                                <div class="w-full border border-neutral-200 text-sm rounded-xl block py-2.5 px-4 bg-neutral-100 text-neutral-700 font-medium text-right">
                                    <span x-text="formatMoney(parseFloat(contact.amount) || 0)"></span>
                                    <input type="hidden" :name="'contacts[' + index + '][amount]'" :value="contact.amount">
                                </div>
                            </template>
                            <template x-if="mode === 'exact'">
                                <x-form-input :name="''" x-bind:name="'contacts[' + index + '][amount]'" :currency="true" placeholder="0,00" x-model="contact.amount" />
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Resumo -->
            <div class="mt-6 pt-4 border-t border-neutral-100 flex justify-between items-center">
                <span class="text-sm font-medium text-neutral-500">
                    <span x-text="totalPeople"></span> participante(s)
                </span>
                <div class="text-right">
                    <span class="text-xs text-neutral-400 uppercase tracking-wider">Total</span>
                    <div class="text-lg font-bold text-neutral-900" x-text="formatMoney(calculatedTotal)"></div>
                </div>
            </div>

        </x-card>

        <x-media.manager :model="isset($settlementGroup) ? $settlementGroup : null" class="mt-4 sm:mt-6" />
    </div>

    <!-- Right Column: Configurações -->
    <div class="lg:col-span-4 flex flex-col order-first lg:order-last">
        <x-card class="space-y-6">
            <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-4">Configurações</h3>

            {{-- Modo de Divisão --}}
            <x-radio-block-group legend="Modo de Divisão">
                <x-radio-block name="mode" x-model="mode" value="equal" icon="heroicon-o-equals" label="Partes Iguais" />
                <x-radio-block name="mode" x-model="mode" value="exact" icon="heroicon-o-pencil" label="Valores Exatos" />
            </x-radio-block-group>

            {{-- Transação Financeira --}}
            <div class="space-y-4 pt-4 border-t border-neutral-100">
                <input type="hidden" name="create_transaction" value="0">
                <x-switch name="create_transaction" x-model="createTransaction" label="Criar Transação?" value="1" color="accent" />

                <div class="space-y-4 pt-2" x-show="createTransaction" x-transition>
                    <x-radio-block-group legend="Onde">
                        <x-radio-block name="targetType_dummy" x-model="targetType" value="account" icon="heroicon-o-building-library" label="Conta" />
                        <x-radio-block name="targetType_dummy" x-model="targetType" value="card" icon="heroicon-o-credit-card" label="Cartão" />
                    </x-radio-block-group>

                    <input type="hidden" name="targetType" :value="targetType">
                    <x-error name="targetType" />

                    <div>
                        <div x-show="targetType === 'account'">
                            <x-form-select name="financial_account_id">
                                <option value="">Selecione uma conta...</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" {{ old('financial_account_id', isset($settlementGroup) && $settlementGroup->financialTransaction ? $settlementGroup->financialTransaction->financial_account_id : null) == $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                @endforeach
                            </x-form-select>
                        </div>
                        <div x-show="targetType === 'card'" style="display: none;">
                            <x-form-select name="financial_credit_card_id">
                                <option value="">Selecione um cartão...</option>
                                @foreach($cards as $card)
                                    <option value="{{ $card->id }}" {{ old('financial_credit_card_id', isset($settlementGroup) && $settlementGroup->financialTransaction && $settlementGroup->financialTransaction->invoice ? $settlementGroup->financialTransaction->invoice->financial_credit_card_id : null) == $card->id ? 'selected' : '' }}>{{ $card->name }}</option>
                                @endforeach
                            </x-form-select>
                        </div>
                    </div>

                    <div class="pt-2">
                        <x-tags-selector name="tags[]" :options="$tags" label="Categoria (Minha Parte)" :value="old('tags', isset($existingTagIds) ? $existingTagIds : [])" :primaryValue="old('primary_tag_id', isset($existingPrimaryTagId) ? $existingPrimaryTagId : null)" />
                    </div>
                </div>
            </div>
        </x-card>
    </div>
</div>
