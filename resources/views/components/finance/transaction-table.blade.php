@props(['transactions', 'hidePendingBadge' => false])

<x-table {{ $attributes }}>
    @if($transactions->isNotEmpty())
        <x-table.header class="hidden sm:grid sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)]">
            <x-table.column>Data</x-table.column>
            <x-table.column>Descrição</x-table.column>
            <x-table.column>Conta/Fatura</x-table.column>
            <x-table.column>Tags</x-table.column>
            <x-table.column align="right">Valor</x-table.column>
        </x-table.header>
    @endif

    <x-table.body>
        @forelse($transactions as $transaction)
            @php
                $href = '#';
                if (!empty($transaction->is_invoice) && $transaction->invoice) {
                    $href = route('financial.cards.invoices.show', [$transaction->invoice->financial_credit_card_id, $transaction->invoice->id]);
                } elseif (!empty($transaction->is_recurrence) && !empty($transaction->recurrence_id)) {
                    $href = route('financial.recurrences.edit', $transaction->recurrence_id);
                } elseif ($transaction->id) {
                    $href = route('financial.transactions.show', $transaction->id);
                }
            @endphp
            @php
                $tagsIds = $transaction->relationLoaded('tags') ? $transaction->tags->pluck('id')->toJson() : '[]';
                $attachmentCount = $transaction->relationLoaded('media')
                    ? $transaction->media->where('collection_name', 'attachments')->count()
                    : $transaction->getMedia('attachments')->count();
            @endphp
            <div style="display: contents"
                 x-data="{ tags: {{ $tagsIds }} }"
                 x-show="typeof selectedTagId === 'undefined' || selectedTagId === null || tags.includes(selectedTagId) || (selectedTagId === 0 && tags.length === 0)">
                <x-table.row href="{{ $href }}" class="hidden sm:grid sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)] group transition-all">
                    <x-table.cell>
                        <span class="font-medium text-neutral-900">{{ formatShort($transaction->date) }}</span>
                    </x-table.cell>

                <x-table.cell>
                    <div class="flex min-w-0 items-center gap-2">
                        <span class="min-w-0 flex-1 truncate font-semibold text-neutral-900">
                            {{ $transaction->description }}
                            @if($transaction->installment_total > 1)
                                <span class="text-neutral-500 font-normal ml-1">({{ $transaction->installment_current }}/{{ $transaction->installment_total }})</span>
                            @endif
                        </span>
                        @if($attachmentCount > 0)
                            <x-media.attachment-indicator :count="$attachmentCount" />
                        @endif
                        @if($transaction->status !== \App\Enums\TransactionStatus::Posted && !$hidePendingBadge && empty($transaction->is_recurrence) && empty($transaction->is_invoice))
                            @if($transaction->status === \App\Enums\TransactionStatus::Draft)
                                <span class="text-xxs uppercase font-bold text-neutral-600 bg-neutral-100 px-1.5 py-0.5 rounded ring-1 ring-inset ring-neutral-300 shrink-0">Rascunho</span>
                            @else
                                <span class="text-xxs uppercase font-bold text-yellow-700 bg-yellow-50 px-1.5 py-0.5 rounded ring-1 ring-inset ring-yellow-600/20 shrink-0">Pendente</span>
                            @endif
                        @endif
                        @if(isset($transaction->is_recurrence) && $transaction->is_recurrence)
                            <span class="text-xxs uppercase font-bold text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded ring-1 ring-inset ring-blue-600/20 shrink-0 flex items-center gap-1"><x-heroicon-o-arrow-path class="size-3" /> Recorrência</span>
                        @endif
                        @if(isset($transaction->is_invoice) && $transaction->is_invoice)
                            <span class="text-xxs uppercase font-bold text-purple-700 bg-purple-50 px-1.5 py-0.5 rounded ring-1 ring-inset ring-purple-600/20 shrink-0 flex items-center gap-1"><x-heroicon-o-document-text class="size-3" /> Fatura</span>
                        @endif
                    </div>
                </x-table.cell>

                <x-table.cell>
                    @if($transaction->invoice)
                        <div class="flex min-w-0 items-center gap-1.5 text-neutral-600 truncate">
                            <x-heroicon-o-credit-card class="size-4 shrink-0" />
                            <span class="truncate">{{ $transaction->invoice->creditCard->name }}</span>
                        </div>
                    @elseif($transaction->account)
                        <div class="flex min-w-0 items-center gap-1.5 text-neutral-600 truncate">
                            <x-heroicon-o-building-library class="size-4 shrink-0" />
                            <span class="truncate">{{ $transaction->account->name }}</span>
                        </div>
                    @else
                        <span class="text-neutral-400">-</span>
                    @endif
                </x-table.cell>

                <x-table.cell>
                    @php
                        $tags = $transaction->tags;
                        $primary = $tags->firstWhere('pivot.is_primary', true);
                        $others = $tags->reject(fn($t) => $t->id === optional($primary)->id);
                        
                        $visibleTags = collect(array_filter([$primary, $others->first()]))->take(2);
                        $remainingCount = $tags->count() - $visibleTags->count();
                    @endphp
                    
                    <div class="flex min-w-0 items-center gap-1.5">
                        @foreach($visibleTags as $tag)
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xxs font-semibold"
                                  style="background-color: {{ $tag->color_hex }}15; color: {{ $tag->color_hex }}; border-color: {{ $tag->color_hex }}40;">
                                @if(isset($primary) && $tag->id === $primary->id)
                                    <span class="relative -ml-0.5 shrink-0 text-yellow-500">
                                        <x-heroicon-s-star class="size-2.5" />
                                    </span>
                                @endif
                                <x-dynamic-component :component="$tag->icon" class="size-3" />
                                <span class="max-w-[80px] truncate">{{ $tag->name }}</span>
                            </span>
                        @endforeach

                        @if($remainingCount > 0)
                            <x-tooltip :text="$others->skip($visibleTags->count() - ($primary ? 1 : 0))->pluck('name')->join(', ')" id="transaction-tags-more-tooltip-{{ $transaction->id }}">
                                <span class="inline-flex cursor-help items-center justify-center rounded-full bg-neutral-100 px-1.5 py-0.5 text-xxs font-bold text-neutral-500 ring-1 ring-inset ring-neutral-200">
                                    +{{ $remainingCount }}
                                </span>
                            </x-tooltip>
                        @endif
                    </div>
                </x-table.cell>

                <x-table.cell align="right">
                    <div class="flex w-full min-w-0 justify-end gap-2">
                        <span class="font-bold tracking-tight text-base {{ $transaction->type === 'expense' ? 'text-red-600' : 'text-green-600' }}">
                            {{ $transaction->type === 'expense' ? '-' : '+' }} {{ formatCurrency($transaction->amount) }}
                        </span>
                    </div>
                </x-table.cell>

                <x-slot name="mobile">
                    <div class="flex items-start justify-between gap-3 w-full">
                        <div class="flex-1 min-w-0 flex flex-col gap-1.5">
                            <h3 class="text-base font-semibold text-neutral-900 leading-tight flex flex-wrap items-center gap-2">
                                <span class="truncate max-w-full">
                                    {{ $transaction->description }}
                                    @if($transaction->installment_total > 1)
                                        <span class="text-neutral-500 font-normal text-sm ml-1">({{ $transaction->installment_current }}/{{ $transaction->installment_total }})</span>
                                    @endif
                                </span>
                                @if($attachmentCount > 0)
                                    <x-media.attachment-indicator :count="$attachmentCount" />
                                @endif
                                @if($transaction->status !== \App\Enums\TransactionStatus::Posted && !$hidePendingBadge && empty($transaction->is_recurrence) && empty($transaction->is_invoice))
                                    @if($transaction->status === \App\Enums\TransactionStatus::Draft)
                                        <span class="text-xxs uppercase font-bold text-neutral-600 bg-neutral-100 px-1.5 py-0.5 rounded ring-1 ring-inset ring-neutral-300 shrink-0">Rascunho</span>
                                    @else
                                        <span class="text-xxs uppercase font-bold text-yellow-700 bg-yellow-50 px-1.5 py-0.5 rounded ring-1 ring-inset ring-yellow-600/20 shrink-0">Pendente</span>
                                    @endif
                                @endif
                                @if(isset($transaction->is_recurrence) && $transaction->is_recurrence)
                                    <span class="text-xxs uppercase font-bold text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded ring-1 ring-inset ring-blue-600/20 shrink-0 flex items-center gap-1"><x-heroicon-o-arrow-path class="size-3" /> Recorrência</span>
                                @endif
                                @if(isset($transaction->is_invoice) && $transaction->is_invoice)
                                    <span class="text-xxs uppercase font-bold text-purple-700 bg-purple-50 px-1.5 py-0.5 rounded ring-1 ring-inset ring-purple-600/20 shrink-0 flex items-center gap-1"><x-heroicon-o-document-text class="size-3" /> Fatura</span>
                                @endif
                            </h3>
                            <div class="flex items-center gap-2 text-sm text-neutral-500">
                                <span>{{ formatShort($transaction->date) }}</span>
                                <span>&bull;</span>
                                @if($transaction->invoice)
                                    <span class="truncate flex items-center gap-1"><x-heroicon-o-credit-card class="size-3" /> {{ $transaction->invoice->creditCard->name }}</span>
                                @elseif($transaction->account)
                                    <span class="truncate flex items-center gap-1"><x-heroicon-o-building-library class="size-3" /> {{ $transaction->account->name }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="font-bold tracking-tight text-base {{ $transaction->type === 'expense' ? 'text-red-600' : 'text-green-600' }}">
                                {{ $transaction->type === 'expense' ? '-' : '+' }} {{ formatCurrency($transaction->amount) }}
                            </div>
                        </div>
                    </div>
                </x-slot>
            </x-table.row>
            </div>
        @empty
            <x-empty-state 
                icon="heroicon-o-banknotes" 
                title="Nenhuma transação encontrada" 
                description="Não há transações disponíveis no momento."
            />
        @endforelse
    </x-table.body>
</x-table>
