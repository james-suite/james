@php
    $singleAccountId = $accounts->count() === 1 ? $accounts->first()->id : null;
    $singleCardId = $cards->count() === 1 ? $cards->first()->id : null;
    $existingAccountId = isset($settlement) && $settlement->financialTransaction
        ? $settlement->financialTransaction->financial_account_id
        : null;
    $existingCardId = isset($settlement) && $settlement->financialTransaction
        ? optional($settlement->financialTransaction->invoice)->financial_credit_card_id
        : null;
    $selectedAccountId = old('financial_account_id', $existingAccountId ?? $singleAccountId);
    $selectedCardId = old('financial_credit_card_id', $existingCardId ?? $singleCardId);
@endphp

<div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-start">
    
    <!-- Left Column: Main Data -->
    <div class="lg:col-span-8 flex flex-col gap-4 sm:gap-6 order-last lg:order-first">
        <x-card>
            <div class="flex flex-col gap-4 sm:gap-6">
                <x-form-input label="Descrição" name="description" x-model="description" placeholder="Descrição" autofocus />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 items-start">
                    <div>
                        <x-form-input label="Valor (R$)" name="amount" :currency="true" placeholder="0,00" :value="old('amount', isset($settlement) ? number_format(abs($settlement->amount), 2, '.', '') : '')" />
                    </div>
                    <div>
                        <x-form-input label="Data" name="date" type="date" :value="old('date', isset($settlement) ? $settlement->date->format('Y-m-d') : \Carbon\Carbon::today()->format('Y-m-d'))" />
                    </div>
                </div>
            </div>
        </x-card>

        <x-media.manager :model="isset($settlement) ? $settlement : null" class="mt-4 sm:mt-6" />
    </div>

    <!-- Right Column: Configurações -->
    <div class="lg:col-span-4 flex flex-col order-first lg:order-last">
        <x-card class="space-y-6">
            <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-4">Configurações</h3>

            {{-- Tipo de Acerto --}}
            <x-radio-block-group legend="Tipo do Acerto">
                @foreach(\App\Enums\SettlementType::cases() as $stype)
                    @php
                        $activeColor = $stype->color() === 'green' ? 'peer-checked:text-emerald-600' : 'peer-checked:text-red-600';
                        $inactiveColor = $stype->color() === 'green' ? 'text-emerald-600 hover:text-emerald-700' : 'text-red-600 hover:text-red-700';
                    @endphp
                    <x-radio-block 
                        name="type" 
                        x-model="type" 
                        value="{{ $stype->value }}" 
                        icon="{{ $stype->icon() }}" 
                        label="{{ $stype->label() }}" 
                        activeClass="{{ $activeColor }}" 
                        inactiveClass="{{ $inactiveColor }}" 
                    />
                @endforeach
            </x-radio-block-group>

            {{-- Transação Financeira (Hide on 'Eu Devo' - i_owe) --}}
            <div class="space-y-4 pt-4 border-t border-neutral-100" x-show="type !== 'i_owe'" x-transition>
                <input type="hidden" name="create_transaction" value="0">
                <x-switch name="create_transaction" x-model="createTransaction" label="Criar Transação?" value="1" color="accent" />

                @if (isset($settlement) && $settlement->financial_transaction_id)
                    <p x-show="!createTransaction" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        A transação já criada será desvinculada deste acerto e continuará disponível em Finanças. Ela não será excluída.
                    </p>
                @endif
                
                <div class="space-y-4 pt-2" x-show="createTransaction" x-transition>
                    <x-radio-block-group legend="Onde">
                        <x-radio-block name="targetType_dummy" x-model="targetType" value="account" icon="heroicon-o-building-library" label="Conta" />
                        <x-radio-block name="targetType_dummy" x-model="targetType" value="card" icon="heroicon-o-credit-card" label="Cartão" />
                    </x-radio-block-group>

                    <input type="hidden" name="targetType" :value="targetType">
                    
                    <div>
                        <div x-show="targetType === 'account'">
                            <x-form-select name="financial_account_id" ::disabled="targetType !== 'account'">
                                <option value="">Selecione uma conta...</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @selected($selectedAccountId == $account->id)>{{ $account->name }}</option>
                                @endforeach
                            </x-form-select>
                        </div>
                        <div x-show="targetType === 'card'" style="display: none;">
                            <x-form-select name="financial_credit_card_id" ::disabled="targetType !== 'card'">
                                <option value="">Selecione um cartão...</option>
                                @foreach($cards as $card)
                                    <option value="{{ $card->id }}" @selected($selectedCardId == $card->id)>{{ $card->name }}</option>
                                @endforeach
                            </x-form-select>
                        </div>
                    </div>

                    <div class="pt-2" x-show="type === 'i_paid'" x-transition>
                        <x-tags-selector name="tags[]" :options="$tags" label="Tags (Opcional)" :value="old('tags', $defaultTags ?? [])" :primaryValue="old('primary_tag_id', $defaultPrimaryTag ?? null)" />
                    </div>
                </div>
            </div>
        </x-card>
    </div>
</div>
