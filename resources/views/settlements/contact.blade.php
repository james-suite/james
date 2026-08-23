<x-layouts.app>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('settlements.index') }}">Acertos</x-breadcrumbs.item>
            <x-breadcrumbs.item>{{ $contact->name }}</x-breadcrumbs.item>
        </x-breadcrumbs>

        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
            <x-back-button fallback="{{ route('settlements.index') }}" />

            <x-modal.trigger name="share-modal-{{ $contact->id }}">
                <x-button type="button" color="outline">
                    <x-heroicon-o-share class="size-4" />
                    <span class="hidden sm:inline">Compartilhar</span>
                </x-button>
            </x-modal.trigger>

            <x-modal 
                name="share-modal-{{ $contact->id }}"
                title="Copiar Mensagem" 
                confirmVariant="primary"
                hideFooter="true">
                <x-slot:content>
                    <div class="space-y-4" x-data="{
                        baseText: {{ Js::from($baseMessageText) }},
                        selectedPixKey: '{{ $pixKeys->first() ?? '' }}',
                        get generatedText() {
                            if (this.selectedPixKey && {{ $netBalance > 0 ? 'true' : 'false' }}) {
                                return this.baseText + `Chave PIX: *${this.selectedPixKey}*\n`;
                            }
                            return this.baseText;
                        },
                        
                        async copyText() {
                            try {
                                if (navigator.clipboard && window.isSecureContext) {
                                    await navigator.clipboard.writeText(this.generatedText);
                                } else {
                                    const textArea = document.createElement('textarea');
                                    textArea.value = this.generatedText;
                                    textArea.style.position = 'absolute';
                                    textArea.style.left = '-999999px';
                                    document.body.prepend(textArea);
                                    textArea.select();
                                    try {
                                        document.execCommand('copy');
                                    } catch (error) {
                                        console.error('Fallback copy failed', error);
                                    } finally {
                                        textArea.remove();
                                    }
                                }
                                window.dispatchEvent(new CustomEvent('toast', {
                                    detail: { type: 'success', message: 'Mensagem copiada!' }
                                }));
                            } catch (e) {
                                console.error('Failed to copy text', e);
                            }
                        }
                    }">
                        @if($netBalance > 0)
                        <div>
                            <x-form-select name="pix_key" label="Chave PIX (Opcional)" class="w-full text-sm" x-model="selectedPixKey">
                                <option value="">Sem chave PIX</option>
                                @foreach($pixKeys as $key)
                                    <option value="{{ $key }}">{{ $key }}</option>
                                @endforeach
                            </x-form-select>
                        </div>
                        @endif
                        
                        <div>
                            <label class="block text-sm font-medium text-neutral-700 mb-1">Mensagem</label>
                            <textarea :value="generatedText" readonly rows="6" class="w-full rounded-lg border-neutral-300 focus:border-neutral-500 focus:ring-neutral-500 text-sm font-mono bg-neutral-50"></textarea>
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-2">
                            <x-button type="button" color="outline" class="w-full sm:w-auto" @click="window.dispatchEvent(new CustomEvent('modal-close', { detail: 'share-modal-{{ $contact->id }}' }))">
                                Fechar
                            </x-button>
                            <x-button type="button" color="outline" class="w-full sm:w-auto" @click="window.open(`https://wa.me/?text=${encodeURIComponent(generatedText)}`, '_blank')">
                                <x-heroicon-o-chat-bubble-oval-left-ellipsis class="size-4 text-green-600" />
                                <span>WhatsApp</span>
                            </x-button>
                            <x-button type="button" color="primary" class="bg-neutral-800 hover:bg-neutral-900 border-neutral-800 text-white w-full sm:w-auto" @click="copyText()">
                                <span class="flex items-center gap-1.5"><x-heroicon-o-clipboard-document class="size-4" /> Copiar</span>
                            </x-button>
                        </div>
                    </div>
                </x-slot:content>
            </x-modal>

            <x-modal.trigger name="archive-contact-{{ $contact->id }}">
                <x-button type="button" color="outline" class="bg-white">
                    <x-heroicon-o-archive-box class="size-4" />
                    <span class="hidden sm:inline">Arquivar</span>
                </x-button>
            </x-modal.trigger>

            <x-modal 
                name="archive-contact-{{ $contact->id }}"
                title="Arquivar Acertos" 
                message="Tem certeza que deseja arquivar os acertos com este contato? Ele não aparecerá na lista principal até que seja desarquivado." 
                confirmVariant="primary">
                <form action="{{ route('settlements.archive') }}" method="POST" class="m-0">
                    @csrf
                    <input type="hidden" name="contact_ids[]" value="{{ $contact->id }}">
                    <x-button type="submit" class="w-full sm:w-auto">
                        Sim, arquivar
                    </x-button>
                </form>
            </x-modal>
            
            @if($settleUrl)
                <x-button href="{{ $settleUrl }}" color="outline" class="bg-white flex-1 sm:flex-initial justify-center">
                    <x-heroicon-o-check-circle class="size-4" />
                    <span class="whitespace-nowrap">Quitar Dívida</span>
                </x-button>
            @endif

            <x-button href="{{ route('settlements.create', $contact) }}" class="{{ $settleUrl ? 'w-full sm:w-auto justify-center' : 'flex-1 sm:flex-initial justify-center' }}">
                <x-heroicon-o-plus class="size-4" />
                <span class="whitespace-nowrap">Novo Acerto</span>
            </x-button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <!-- Contact Header -->
        <x-card href="{{ route('contacts.show', $contact->id) }}" class="flex items-center gap-4 h-full">
            <x-avatar :model="$contact" size="xl" />
            <div>
                <h1 class="text-2xl font-bold text-neutral-900">{{ $contact->name }}</h1>
                <div class="flex items-center gap-2 text-neutral-500 text-sm mt-1">
                    <span>{{ $contact->relationship_category ?? 'Contato' }}</span>
                    @if($contact->phones && count($contact->phones) > 0)
                        <x-heroicon-m-minus class="size-3 text-neutral-300" />
                        <span class="flex items-center gap-1">
                            <x-heroicon-o-phone class="size-3.5" />
                            {{ collect($contact->phones)->first()['value'] ?? '' }}
                        </span>
                    @endif
                </div>
            </div>
        </x-card>

        <!-- Acertos KPI Card -->
        <x-finance.kpi-card
            title="Saldo Líquido"
            value="{{ formatCurrency(abs($netBalance)) }}"
            icon="heroicon-o-scale"
            :color="$netBalance > 0 ? 'green' : ($netBalance < 0 ? 'red' : 'neutral')"
            :hideIconOnMobile="false"
            class="h-full"
        >
            {{ $netBalance > 0 ? 'Você tem a receber' : ($netBalance < 0 ? 'Você tem a pagar' : 'Tudo quitado') }}
        </x-finance.kpi-card>
    </div>

    <!-- Ledger Table -->
    <h2 class="text-lg font-semibold text-neutral-900 mb-4">Histórico de Transações</h2>
    
    <div class="mb-12">
        <x-table>
            <x-table.header class="hidden sm:grid sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <x-table.column>Data</x-table.column>
                <x-table.column>Descrição</x-table.column>
                <x-table.column>Tipo</x-table.column>
                <x-table.column>Pagamento</x-table.column>
                <x-table.column class="text-right">Valor</x-table.column>
            </x-table.header>

            <x-table.body>
                @forelse($settlements as $settlement)
                    @php
                        $isPositiveForMe = in_array($settlement->type->value, [\App\Enums\SettlementType::TheyOwe->value, \App\Enums\SettlementType::IPaid->value]);
                        $amountColor = $isPositiveForMe ? 'text-emerald-600' : 'text-red-600';
                        $amountPrefix = $isPositiveForMe ? '+' : '-';
                    @endphp

                    <x-table.row href="{{ $settlement->settlement_group_id ? route('settlements.groups.show', $settlement->settlement_group_id) : route('settlements.show_item', $settlement) }}" class="hidden sm:grid sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)]">
                        <x-table.cell class="text-neutral-500">
                            {{ formatShort($settlement->date) }}
                        </x-table.cell>
                        
                        <x-table.cell>
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="min-w-0 flex-1 truncate font-medium text-neutral-700">{{ $settlement->description }}</span>
                                @php
                                    $mediaCount = $settlement->getMedia('attachments')->count();
                                    if ($settlement->group) {
                                        $mediaCount += $settlement->group->getMedia('attachments')->count();
                                    }
                                @endphp
                                <x-media.attachment-indicator :count="$mediaCount" />
                            </div>
                        </x-table.cell>

                        <x-table.cell>
                            <x-badge :color="$settlement->type->color()" class="flex items-center gap-1 w-fit">
                                <x-dynamic-component :component="$settlement->type->icon()" class="size-3.5" />
                                <span>{{ $settlement->type->label() }}</span>
                            </x-badge>
                        </x-table.cell>

                        <x-table.cell class="text-neutral-500 text-sm">
                            @php
                                $transaction = $settlement->financialTransaction ?? $settlement->group?->financialTransaction;
                            @endphp
                            @if($transaction)
                                @if($transaction->invoice)
                                    <div class="flex items-center gap-1.5 truncate text-neutral-600">
                                        <x-heroicon-o-credit-card class="size-4 shrink-0" />
                                        <span class="truncate">{{ $transaction->invoice->creditCard->name }}</span>
                                    </div>
                                @elseif($transaction->account)
                                    <div class="flex items-center gap-1.5 truncate text-neutral-600">
                                        <x-heroicon-o-building-library class="size-4 shrink-0" />
                                        <span class="truncate">{{ $transaction->account->name }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-1.5 truncate text-neutral-600">
                                        <x-heroicon-o-currency-dollar class="size-4 shrink-0" />
                                        <span class="truncate">Transação</span>
                                    </div>
                                @endif
                            @else
                                <span class="text-neutral-300">-</span>
                            @endif
                        </x-table.cell>

                        <x-table.cell class="text-right font-semibold tabular-nums {{ $amountColor }}">
                            {{ $amountPrefix }} {{ formatCurrency($settlement->amount) }}
                        </x-table.cell>

                        <!-- Mobile View -->
                        <x-slot:mobile>
                            <div class="flex justify-between items-start gap-4">
                                <div class="flex flex-col gap-1 min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <x-badge :color="$settlement->type->color()" class="flex items-center shrink-0 w-fit">
                                            <x-dynamic-component :component="$settlement->type->icon()" class="size-3.5" />
                                        </x-badge>
                                        <span class="font-medium text-neutral-900 truncate">{{ $settlement->description }}</span>
                                        @php
                                            $mediaCount = $settlement->getMedia('attachments')->count();
                                            if ($settlement->group) {
                                                $mediaCount += $settlement->group->getMedia('attachments')->count();
                                            }
                                        @endphp
                                        <x-media.attachment-indicator :count="$mediaCount" />
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-neutral-500">
                                        <span>{{ formatShort($settlement->date) }}</span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="font-semibold tabular-nums {{ $amountColor }}">{{ $amountPrefix }} {{ formatCurrency($settlement->amount) }}</span>
                                    <div class="text-xs text-neutral-500 mt-0.5">{{ $settlement->type->label() }}</div>
                                </div>
                            </div>
                        </x-slot:mobile>
                    </x-table.row>
                @empty
                    <div class="col-span-full">
                        <x-empty-state 
                            icon="heroicon-o-queue-list" 
                            title="Nenhuma transação" 
                            description="Você ainda não registrou nenhum acerto com este contato." 
                        />
                    </div>
                @endforelse
            </x-table.body>
        </x-table>

        <div class="mt-6">
            {{ $settlements->links() }}
        </div>
    </div>
</x-layouts.app>
