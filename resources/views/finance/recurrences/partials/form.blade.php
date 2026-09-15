@php
    $singleAccountId = $accounts->count() === 1 ? $accounts->first()->id : null;
    $singleCardId = $cards->count() === 1 ? $cards->first()->id : null;
    $existingAccountId = isset($recurrence) ? $recurrence->financial_account_id : null;
    $existingCardId = isset($recurrence) ? $recurrence->financial_credit_card_id : null;
    $selectedAccountId = old('financial_account_id', $existingAccountId ?? $singleAccountId);
    $selectedCardId = old('financial_credit_card_id', $existingCardId ?? $singleCardId);
@endphp

<div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-start">
    <!-- Left Column: Main Data -->
    <div class="lg:col-span-8 flex flex-col gap-4 sm:gap-6 order-last lg:order-first">
        <!-- Main Data Card -->
        <x-card>
            <div class="flex flex-col gap-4 sm:gap-6">
                <x-form-input 
                    name="title" 
                    label="Título" 
                    placeholder="Ex: Assinatura Netflix, Conta de Luz" 
                    value="{{ old('title', $recurrence->title ?? '') }}" 
                    required 
                    autofocus 
                />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 items-start">
                    <div>
                        <x-form-input 
                            name="amount" 
                            label="Valor (R$)" 
                            :currency="true"
                            placeholder="0,00" 
                            value="{{ old('amount', isset($recurrence) ? number_format($recurrence->amount, 2, '.', '') : '') }}" 
                            required 
                        />
                    </div>
                    <div>
                        <x-form-input 
                            name="start_date" 
                            label="Data de Início" 
                            type="date" 
                            value="{{ old('start_date', isset($recurrence) ? $recurrence->start_date->format('Y-m-d') : \Carbon\Carbon::today()->format('Y-m-d')) }}" 
                            required 
                        />
                    </div>
                </div>

                <div>
                    <x-form-input 
                        name="end_date" 
                        label="Data de Fim (Opcional)" 
                        type="date" 
                        value="{{ old('end_date', (isset($recurrence) && $recurrence->end_date) ? $recurrence->end_date->format('Y-m-d') : '') }}" 
                    />
                    <p class="text-xs text-neutral-500 mt-1">Se não preencher, a assinatura cobrará para sempre.</p>
                </div>
                
                <div>
                    <x-tags-selector name="tags[]" :options="$tags" label="Tags (Opcional)" :value="old('tags', isset($recurrence) ? $recurrence->tags->pluck('id')->toArray() : [])" :primaryValue="old('primary_tag_id', isset($recurrence) ? $recurrence->tags->where('pivot.is_primary', true)->first()?->id : null)" />
                </div>
            </div>
        </x-card>
    </div>

    <!-- Right Column: Configurações -->
    <div class="lg:col-span-4 flex flex-col order-first lg:order-last">
        <x-card class="space-y-6">
            <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-4">Configurações</h3>

            {{-- Tipo de Recorrência --}}
            <x-radio-block-group legend="Frequência">
                <x-radio-block name="frequency" x-model="frequency" value="monthly" icon="heroicon-o-calendar-days" label="Mensal" />
                <x-radio-block name="frequency" x-model="frequency" value="yearly" icon="heroicon-o-calendar" label="Anual" />
            </x-radio-block-group>

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

                <input type="hidden" name="targetType" x-model="targetType">
                
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
                
                <div class="pt-4 border-t border-neutral-100">
                    <x-switch name="is_active" :checked="old('is_active', isset($recurrence) ? $recurrence->is_active : true)" label="Recorrência Ativa" color="accent" />
                    <p class="text-xs text-neutral-500 mt-1 ml-14">Se desmarcado, novas transações não serão geradas.</p>
                </div>
            </div>
        </x-card>
    </div>
</div>
