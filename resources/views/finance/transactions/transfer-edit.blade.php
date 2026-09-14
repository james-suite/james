<x-layouts.financial>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('financial.transactions.index') }}">Transações</x-breadcrumbs.item>
            <x-breadcrumbs.item href="{{ route('financial.transactions.show', $expense) }}">Transferência</x-breadcrumbs.item>
            <x-breadcrumbs.item>Editar</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <form action="{{ route('financial.transactions.transfer.update', $expense) }}" method="POST" id="transfer-form" x-data="{ isPosted: {{ $isPosted ? 'true' : 'false' }} }">
        @csrf
        @method('PUT')

        <x-page-header title="Editar Transferência" mobileBottom>
            <x-form-actions fallback="{{ route('financial.transactions.show', $expense) }}" form="transfer-form" submitText="Salvar Alterações" />
        </x-page-header>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-start mt-6">
            <div class="lg:col-span-8">
                <x-card>
                    <div class="flex flex-col gap-4 sm:gap-6">
                        <x-form-input
                            label="Descrição"
                            name="description"
                            :value="old('description', $expense->description)"
                            placeholder="Descrição da transferência"
                            required
                            autofocus
                        />

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                            <x-form-input
                                label="Valor (R$)"
                                name="amount"
                                :value="old('amount', $expense->amount)"
                                :currency="true"
                                placeholder="0,00"
                                required
                            />
                            <x-form-input
                                label="Data"
                                name="date"
                                type="date"
                                :value="old('date', $expense->date->format('Y-m-d'))"
                                required
                            />
                        </div>

                        <div class="border-t border-neutral-100 pt-4">
                            <x-switch
                                name="_status_switch"
                                value="1"
                                :checked="$isPosted"
                                label="Transferência efetivada?"
                                color="accent"
                                x-model="isPosted"
                            />
                            <input type="hidden" name="status" :value="isPosted ? 'posted' : 'pending'">
                            <p class="text-xs text-neutral-500 mt-1 ml-14">Se desmarcada, os dois lançamentos ficarão pendentes.</p>
                        </div>
                    </div>
                </x-card>
            </div>

            <div class="lg:col-span-4">
                <x-card class="space-y-6">
                    <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest">Contas</h3>

                    <x-form-select name="from_account_id" label="Conta de origem" required>
                        <option value="">Selecione a conta de origem...</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected(old('from_account_id', $expense->financial_account_id) == $account->id)>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </x-form-select>

                    <div class="flex items-center gap-3">
                        <div class="flex-1 h-px bg-neutral-200"></div>
                        <x-heroicon-o-arrow-down class="size-4 text-neutral-400" />
                        <div class="flex-1 h-px bg-neutral-200"></div>
                    </div>

                    <x-form-select name="to_account_id" label="Conta de destino" required>
                        <option value="">Selecione a conta de destino...</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected(old('to_account_id', $income->financial_account_id) == $account->id)>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </x-form-select>

                    <div class="border-t border-neutral-100 pt-4">
                        <x-form-input
                            label="Taxa / IOF / Imposto (opcional)"
                            name="fee_amount"
                            :value="old('fee_amount', $fee?->amount)"
                            :currency="true"
                            placeholder="0,00"
                        />

                        <x-form-select name="fee_tag_id" label="Categoria da taxa" class="mt-3">
                            <option value="">Usar Juros</option>
                            @foreach($feeTags as $tag)
                                <option value="{{ $tag->id }}" @selected(old('fee_tag_id', $feeTagId) == $tag->id)>
                                    {{ $tag->name }}
                                </option>
                            @endforeach
                        </x-form-select>
                        <p class="text-xs text-neutral-500 mt-1">Limpe o valor para remover a taxa existente.</p>
                    </div>
                </x-card>
            </div>
        </div>

        <x-form-actions fallback="{{ route('financial.transactions.show', $expense) }}" form="transfer-form" submitText="Salvar Alterações" mobile />
    </form>
</x-layouts.financial>
