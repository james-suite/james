<x-layouts.app>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('settlements.index') }}">Acertos</x-breadcrumbs.item>
            <x-breadcrumbs.item href="{{ route('settlements.groups.index') }}">Contas Divididas</x-breadcrumbs.item>
            <x-breadcrumbs.item>Detalhes</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header :title="$settlementGroup->description">
            <x-back-button fallback="{{ route('settlements.groups.index') }}" />

            @if(!$settlementGroup->trashed())
                <x-modal.delete
                    action="{{ route('settlements.groups.destroy', $settlementGroup) }}"
                    item-name="a divisão de conta"
                    item-desc="{{ $settlementGroup->description }}"
                    title="Excluir Divisão de Conta"
                    description="Isso removerá todos os acertos vinculados a ela."
                />
                <x-button color="outline" href="{{ route('settlements.groups.edit', $settlementGroup) }}" class="bg-white flex-1 sm:flex-initial">
                    <x-heroicon-o-pencil class="size-4" />
                    <span class="whitespace-nowrap">Editar</span>
                </x-button>
            @endif
    </x-page-header>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-start">
        <!-- Left Column: Items -->
        <div class="lg:col-span-8 flex flex-col gap-4 sm:gap-6">
            <x-card>
                <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-6">Participantes</h3>
                
                <div class="space-y-2">
                    @php
                        // Calculate my share based on total - sum of all contacts
                        $contactsTotal = $settlementGroup->settlements->sum('amount');
                        $myShare = max(0, $settlementGroup->total_amount - $contactsTotal);
                    @endphp

                    <x-card size="sm" class="flex items-center justify-between border-accent/30 bg-accent/5">
                        <div class="flex items-center gap-4 min-w-0">
                            <x-avatar :model="auth()->user()" variant="accent" size="lg" />
                            <div class="min-w-0">
                                <div class="font-medium text-neutral-900 truncate">Minha Parte</div>
                                <div class="text-xs text-neutral-500">Sua despesa</div>
                            </div>
                        </div>
                        <div class="text-right shrink-0 ml-4 flex items-center gap-2">
                            <span class="font-bold text-red-600 whitespace-nowrap tabular-nums">- {{ formatCurrency($myShare) }}</span>
                            <div class="size-4"></div> <!-- Placeholder to align with chevron in other rows -->
                        </div>
                    </x-card>

                    <!-- Contatos -->
                    @foreach($settlementGroup->settlements as $settlement)
                        <x-card size="sm" href="{{ route('settlements.contact.show', $settlement->contact_id) }}" class="t-learn flex items-center justify-between group border-neutral-100">
                            <div class="flex items-center gap-4 min-w-0">
                                <x-avatar :model="$settlement->contact" size="lg" />
                                <div class="min-w-0">
                                    <div class="font-medium text-neutral-900 group-hover:text-brand-600 transition-colors truncate">{{ $settlement->contact->name }}</div>
                                    <div class="text-xs text-neutral-500">Deve reembolsar</div>
                                </div>
                            </div>
                            <div class="text-right shrink-0 ml-4 flex items-center gap-2">
                                <span class="font-bold text-green-600 whitespace-nowrap tabular-nums">+ {{ formatCurrency($settlement->amount) }}</span>
                                <x-heroicon-m-chevron-right class="t-learn-chevron size-4 text-neutral-400 group-hover:text-brand-600 transition-colors" />
                            </div>
                        </x-card>
                    @endforeach
                </div>

                <div class="mt-6 pt-4 border-t border-neutral-100 flex justify-between items-center">
                    <span class="text-sm font-medium text-neutral-500">Total</span>
                    <span class="text-lg font-bold text-red-600">- {{ formatCurrency($settlementGroup->total_amount) }}</span>
                </div>

                @if($settlementGroup->financialTransaction)
                    <div class="mt-8 pt-6 border-t border-neutral-100">
                        <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-4">Meio de Pagamento</h3>
                        <div class="flex items-center gap-4">
                            @if($settlementGroup->financialTransaction->invoice)
                                <x-avatar icon="heroicon-o-credit-card" size="lg" />
                                <div>
                                    <div class="font-medium text-neutral-900">{{ $settlementGroup->financialTransaction->invoice->creditCard->name }}</div>
                                    <div class="text-sm text-neutral-500">Cartão de Crédito • Fatura de {{ formatMonthYear($settlementGroup->financialTransaction->invoice->closing_date) }}</div>
                                </div>
                            @elseif($settlementGroup->financialTransaction->account)
                                <x-avatar icon="heroicon-o-building-library" size="lg" />
                                <div>
                                    <div class="font-medium text-neutral-900">{{ $settlementGroup->financialTransaction->account->name }}</div>
                                    <div class="text-sm text-neutral-500">Conta Corrente</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

            </x-card>

            @if($settlementGroup->hasMedia('attachments'))
                <x-media.manager :model="$settlementGroup" :editable="false" />
            @endif

            <x-activity-log :model="$settlementGroup" class="!mt-0" />
        </div>

        <!-- Right Column: Meta -->
        <div class="lg:col-span-4 flex flex-col gap-4 sm:gap-6">
            <x-card>
                <h3 class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-4">Detalhes</h3>
                
                <div class="space-y-4">
                    <div>
                        <div class="text-xs text-neutral-500 mb-1">Data</div>
                        <div class="font-medium text-neutral-900">{{ formatShort($settlementGroup->date) }}</div>
                    </div>
                    
                    <div>
                        <div class="text-xs text-neutral-500 mb-1">Modo de Divisão</div>
                        <div class="font-medium text-neutral-900">
                            {{ $settlementGroup->mode === 'equal' ? 'Partes Iguais' : 'Valores Exatos' }}
                        </div>
                    </div>

                    @if($settlementGroup->financialTransaction)
                        <div class="pt-4 border-t border-neutral-100">
                            <div class="text-xs text-neutral-500 mb-2">Transação Financeira</div>
                            <x-card size="sm" href="{{ route('financial.transactions.show', $settlementGroup->financialTransaction) }}" class="flex items-center justify-between group bg-neutral-50/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-white border border-neutral-200 flex items-center justify-center text-neutral-500 group-hover:border-accent/30 group-hover:text-accent transition-colors shrink-0">
                                        <x-heroicon-o-receipt-percent class="size-4" />
                                    </div>
                                    <span class="text-sm font-medium text-neutral-700 group-hover:text-neutral-900 transition-colors">Ver Transação</span>
                                </div>
                                <x-heroicon-m-chevron-right class="size-4 text-neutral-400 group-hover:text-neutral-600 transition-colors shrink-0" />
                            </x-card>
                        </div>
                    @endif
                </div>
            </x-card>

            <x-metadata-card :model="$settlementGroup" />
        </div>
    </div>
</x-layouts.app>
