<div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-start">
    
    <!-- Left Column: Main Data & Items -->
    <div class="lg:col-span-8 flex flex-col gap-4 sm:gap-6 order-last lg:order-first">
        <!-- Main Data Card -->
        <x-card>
            <div class="flex flex-col gap-4 sm:gap-6">
                <x-form-input label="Descrição" name="description" :value="old('description', $transaction->description ?? '')" placeholder="Descrição" autofocus />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 items-start">
                    <div>
                        <x-form-input label="Valor (R$)" name="amount" :currency="true" placeholder="0,00" ::readonly="items.length > 0" ::class="items.length > 0 ? 'bg-neutral-100 text-neutral-500! font-medium' : ''" x-model="amount" />
                        <div class="h-5 mt-1">
                            <p class="text-xs text-primary-600 flex items-center gap-1 font-medium m-0" x-show="items.length > 0"><x-heroicon-o-calculator class="size-3.5"/> Calculado via itens</p>
                        </div>
                    </div>
                    <div>
                        <x-form-input label="Data da Transação" name="date" type="date" :value="old('date', isset($transaction) ? $transaction->date->format('Y-m-d') : \Carbon\Carbon::today()->format('Y-m-d'))" x-model="date" />
                    </div>
                </div>

                @if(!isset($transaction))
                <div x-show="mode === 'installment'" x-transition:enter="transition motion-ease-smooth-out motion-duration-slow" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition motion-ease-smooth-out motion-duration-medium" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2" style="display: none;">
                    <x-form-input label="Número de Parcelas" name="installments" type="number" min="2" :value="old('installments', 2)" />
                </div>
                @endif
                
                <div>
                    <x-tags-selector name="tags[]" :options="$tags" label="Tags (Opcional)" :value="old('tags', $defaultTags ?? [])" :primaryValue="old('primary_tag_id', $defaultPrimaryTag ?? null)" xDisablePrimary="items.length > 0" />
                </div>
            </div>
        </x-card>

        <x-media.manager :model="isset($transaction) ? $transaction : null" class="mb-6" />

        <!-- Seção de Itens da Transação -->
        <x-finance.transaction-items :tags="$tags" />
    </div>

    <!-- Right Column: Configurações -->
    <div class="lg:col-span-4 flex flex-col order-first lg:order-last">
        <x-card class="space-y-6">
            <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-4">Configurações</h3>

            @if(!isset($transaction))
            {{-- Tipo de Movimento --}}
            <x-radio-block-group legend="Tipo">
                <x-radio-block name="mode" x-model="mode" value="single" icon="heroicon-o-currency-dollar" label="Única" />
                <x-radio-block name="mode" x-model="mode" value="installment" icon="heroicon-o-calendar-days" label="Parcelada" />
            </x-radio-block-group>
            @endif

            {{-- Receita ou Despesa --}}
            <x-radio-block-group legend="Classificação">
                <x-radio-block name="type" x-model="type" value="expense" icon="heroicon-o-arrow-trending-down" label="Despesa" activeClass="peer-checked:text-red-600" inactiveClass="text-red-600 hover:text-red-700" />
                <x-radio-block name="type" x-model="type" value="income" icon="heroicon-o-arrow-trending-up" label="Receita" activeClass="peer-checked:text-green-600" inactiveClass="text-green-600 hover:text-green-700" />
            </x-radio-block-group>

            {{-- Conta ou Cartão --}}
            <div class="space-y-4 pt-2">
                <x-radio-block-group legend="Onde">
                    <x-radio-block name="targetType_dummy" x-model="targetType" value="account" icon="heroicon-o-building-library" label="Conta" />
                    <x-radio-block name="targetType_dummy" x-model="targetType" value="card" icon="heroicon-o-credit-card" label="Cartão" />
                </x-radio-block-group>
                
                <div>
                    <div x-show="targetType === 'account'">
                        <x-form-select name="financial_account_id">
                            <option value="">Selecione uma conta...</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ old('financial_account_id', $transaction->financial_account_id ?? '') == $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                            @endforeach
                        </x-form-select>
                    </div>
                    <div x-show="targetType === 'card'" style="display: none;">
                        <x-form-select name="financial_credit_card_id">
                            <option value="">Selecione um cartão...</option>
                            @foreach($cards as $card)
                                <option value="{{ $card->id }}" {{ old('financial_credit_card_id', isset($transaction) ? optional($transaction->invoice)->financial_credit_card_id : '') == $card->id ? 'selected' : '' }}>{{ $card->name }}</option>
                            @endforeach
                        </x-form-select>
                    </div>
                </div>
                
                @php
                    $isPosted = old('status', isset($transaction) ? $transaction->status?->value : 'posted') === 'posted';
                @endphp
                <div class="pt-4 border-t border-neutral-100" x-show="targetType === 'account'" x-data="{ isPosted: {{ $isPosted ? 'true' : 'false' }} }">
                    <x-switch
                        name="_status_switch"
                        value="1"
                        :checked="$isPosted"
                        label="Transação Efetivada?"
                        color="accent"
                        x-model="isPosted"
                        @uncheck-posted.window="isPosted = false"
                        @uncheck-posted-edit.window="isPosted = false"
                    />
                    <input type="hidden" name="status" :value="isPosted ? 'posted' : 'pending'">
                    <p class="text-xs text-neutral-500 mt-1 ml-14">Se desmarcado, a transação ficará como pendente.</p>
                </div>
            </div>
        </x-card>
    </div>
</div>
