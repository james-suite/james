@props([
    'card',
])

@php
    $limit = $card->credit_limit;
    // Assume scopeWithUsedLimit was used, providing 'used_limit' attribute, fallback to method or 0.
    $usedLimit = $card->used_limit ?? (method_exists($card, 'usedLimit') ? $card->usedLimit() : 0);
    
    // Find the current invoice or default to the first loaded one
    if (isset($card->current_invoice_total)) {
        $invoiceTotal = $card->current_invoice_total;
        $invoiceStatus = $card->current_invoice_status;
    } else {
        $currentInvoice = $card->invoices->first();
        if ($currentInvoice) {
            $invoiceTotal = $currentInvoice->total_amount ?? (method_exists($currentInvoice, 'total') ? $currentInvoice->total() : 0);
            $invoiceStatus = $currentInvoice->status();
        } else {
            $invoiceTotal = 0;
            $invoiceStatus = \App\Enums\InvoiceStatus::Open;
        }
    }

    $statusLabel = $invoiceStatus->label();
    $statusColor = $invoiceStatus->color();
@endphp

<x-card :href="route('financial.cards.show', $card->id)" class="p-4 flex flex-col justify-between group h-full">
    <div>
        <div class="flex justify-between items-start mb-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="p-1.5 bg-neutral-100 rounded text-neutral-600 shrink-0">
                    <x-heroicon-o-credit-card class="size-4" />
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-sm text-neutral-900 truncate">{{ $card->name }}</h3>
                    <p class="text-[10px] text-neutral-500 truncate">Vence dia {{ $card->due_day }} • Fecha dia {{ $card->closing_day }}</p>
                </div>
            </div>
            <div class="p-1 -mr-1 -mt-1 text-neutral-400 group-hover:text-brand-600 transition-colors">
                <x-heroicon-o-arrow-top-right-on-square class="size-4" />
            </div>
        </div>

        @if((float) $limit > 0)
            <div class="mb-4">
                <x-progress 
                    :value="$usedLimit" 
                    :max="$limit" 
                    :showValue="false" 
                />
                <div class="flex justify-between items-center text-[10px] text-neutral-500 mt-1.5">
                    <span>Usado: {{ formatCurrency($usedLimit) }}</span>
                    <span>Limite: {{ formatCurrency($limit) }}</span>
                </div>
            </div>
        @endif

        <div class="flex justify-between items-end border-t border-neutral-100 pt-3 mt-auto">
            <div>
                <div class="text-[10px] text-neutral-500 uppercase tracking-wider font-semibold mb-1">Fatura Atual</div>
                <div class="font-bold text-base leading-none {{ $invoiceStatus === \App\Enums\InvoiceStatus::Paid ? 'text-green-600' : 'text-neutral-900' }}">
                    {{ formatCurrency($invoiceTotal) }}
                </div>
            </div>
            <div class="text-right flex flex-col items-end gap-1">
                <x-badge :color="$statusColor" class="text-[9px] px-1.5 py-0.5 leading-none">
                    {{ $statusLabel }}
                </x-badge>
            </div>
        </div>
    </div>
</x-card>
