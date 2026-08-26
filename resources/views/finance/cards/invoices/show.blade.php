<x-layouts.financial>
    <div class="flex justify-between items-center mb-6">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('financial.cards.index') }}">Cartões</x-breadcrumbs.item>
            <x-breadcrumbs.item href="{{ route('financial.cards.show', $card) }}">{{ $card->name }}</x-breadcrumbs.item>
            <x-breadcrumbs.item>Fatura {{ formatMonthYear($invoice->reference_month) }}</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    @php
        $status = $invoice->status();

        $total = $invoice->total();
        $isFavorable = $total < 0;
        $remaining = max(0, $total - $invoice->amount_paid);
    @endphp

    <x-page-header :title="'Fatura de ' . formatMonthYearFull($invoice->reference_month)">
        <x-slot:subtitle>
            <div class="flex items-center gap-2 mt-2">
                <x-badge :color="$status->color()">
                    {{ $status->label() }}
                </x-badge>
                <span class="text-sm text-neutral-500">Cartão: {{ $card->name }}</span>
            </div>
        </x-slot:subtitle>

        <x-modal.trigger name="edit-invoice-modal">
            <x-button type="button" color="outline" class="bg-white">
                <x-heroicon-o-pencil-square class="size-4" />
                <span class="hidden sm:inline">Editar Fatura</span>
            </x-button>
        </x-modal.trigger>

        @if($invoice->amount_paid > 0)
            <x-modal.trigger name="unpay-invoice-modal">
                <x-button type="button" color="outline" class="bg-white text-orange-600 hover:bg-orange-50 border-orange-200">
                    <x-heroicon-o-arrow-uturn-left class="size-4" />
                    <span class="hidden sm:inline">Reabrir Fatura</span>
                </x-button>
            </x-modal.trigger>

            <x-modal
                name="unpay-invoice-modal"
                title="Reabrir Fatura"
                message="Tem certeza que deseja desfazer o pagamento e reabrir a fatura? As transações de pagamento serão excluídas."
                confirmVariant="danger">
                <form action="{{ route('financial.cards.invoices.unpay', [$card, $invoice]) }}" method="POST" class="m-0">
                    @csrf
                    <x-button type="submit" color="red" class="w-full sm:w-auto">
                        Sim, Reabrir Fatura
                    </x-button>
                </form>
            </x-modal>
        @endif

        @if($status !== App\Enums\InvoiceStatus::Paid && !$isFavorable && $total > 0)
            <x-modal.trigger name="pay-invoice-modal">
                <x-button type="button" class="w-full sm:w-auto">
                    <x-heroicon-o-currency-dollar class="size-4" />
                    <span class="whitespace-nowrap">Registrar Pagamento</span>
                </x-button>
            </x-modal.trigger>
        @endif
    </x-page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <x-card class="p-5">
            <h3 class="text-sm font-medium text-neutral-500 mb-1">Fechamento</h3>
            <div class="text-lg font-semibold text-neutral-900">{{ formatShort($invoice->closing_date) }}</div>
        </x-card>
        
        <x-card class="p-5">
            <h3 class="text-sm font-medium text-neutral-500 mb-1">Vencimento</h3>
            <div class="text-lg font-semibold text-neutral-900">{{ formatShort($invoice->due_date) }}</div>
        </x-card>
        
        <x-card class="p-5">
            <h3 class="text-sm font-medium text-neutral-500 mb-1">Total da Fatura</h3>
            <div class="text-lg font-bold {{ $isFavorable ? 'text-green-600' : 'text-neutral-900' }}">
                @if($isFavorable)
                    Saldo a seu favor: {{ formatCurrency(abs($total)) }}
                @else
                    {{ formatCurrency($total) }}
                @endif
            </div>
        </x-card>

        <x-card class="p-5">
            <h3 class="text-sm font-medium text-neutral-500 mb-1">Falta Pagar</h3>
            <div class="text-lg font-bold {{ $remaining > 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ formatCurrency($remaining) }}
            </div>
            @if($invoice->amount_paid > 0)
                <div class="text-xs text-neutral-400 mt-1">Pago: {{ formatCurrency($invoice->amount_paid) }}</div>
            @endif
        </x-card>
    </div>

    @if($invoice->notes)
        <div class="mb-8">
            <h3 class="text-sm font-medium text-neutral-500 mb-2">Anotações / Observações</h3>
            <div class="bg-white border border-neutral-200 rounded-lg p-4 text-sm text-neutral-700 whitespace-pre-wrap">{{ $invoice->notes }}</div>
        </div>
    @endif

    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-bold text-neutral-900">Transações</h2>
    </div>

    <x-finance.transaction-table :transactions="$transactions" class="lg:mb-4" />
    @if($transactions->hasPages())
        <div class="mb-8">
            {{ $transactions->links() }}
        </div>
    @endif

    @if($status !== App\Enums\InvoiceStatus::Paid && !$isFavorable && $total > 0)
        <!-- Modal Pagamento -->
        <x-modal name="pay-invoice-modal" title="Pagar Fatura">
            <x-slot name="content">
                <form action="{{ route('financial.cards.invoices.pay', [$card, $invoice]) }}" method="POST" id="pay-form" class="mt-4">
                    @csrf
                    <div class="space-y-4">
                        <p class="text-sm text-neutral-600 mb-4">
                            O pagamento será debitado da conta <strong>{{ $card->financialAccount->name }}</strong>.
                        </p>
                        
                        <x-form-input 
                            name="amount" 
                            :currency="true" 
                            label="Valor do Pagamento" 
                            value="{{ number_format($remaining, 2, '.', '') }}" 
                            required 
                        />
                        
                        <x-form-input 
                            name="paid_at" 
                            type="date" 
                            label="Data do Pagamento" 
                            value="{{ date('Y-m-d') }}" 
                            required 
                        />
                        
                        <x-form-input 
                            name="interest_amount" 
                            :currency="true" 
                            label="Juros e Multas (Opcional)" 
                            placeholder="0.00" 
                        />
                    </div>
                </form>
            </x-slot>
            
            <x-button form="pay-form" type="submit" class="w-full sm:w-auto">
                Confirmar Pagamento
            </x-button>
        </x-modal>
    @endif

    <x-activity-log :model="$invoice" />

    <!-- Modal Editar Fatura -->
    <x-modal name="edit-invoice-modal" title="Editar Fatura" confirmVariant="none">
        <form action="{{ route('financial.cards.invoices.update', [$card, $invoice]) }}" method="POST" class="m-0">
            @csrf
            @method('PUT')
            <div class="space-y-4 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-form-input 
                        name="closing_date" 
                        type="date" 
                        label="Data de Fechamento" 
                        value="{{ old('closing_date', $invoice->closing_date->format('Y-m-d')) }}" 
                        required 
                    />
                    
                    <x-form-input 
                        name="due_date" 
                        type="date" 
                        label="Data de Vencimento" 
                        value="{{ old('due_date', $invoice->due_date->format('Y-m-d')) }}" 
                        required 
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-neutral-900">Anotações / Observações</label>
                    <div class="mt-2">
                        <textarea name="notes" rows="3" class="block w-full rounded-md border-0 py-1.5 text-neutral-900 shadow-sm ring-1 ring-inset ring-neutral-300 placeholder:text-neutral-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6">{{ old('notes', $invoice->notes) }}</textarea>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end items-center gap-3 pt-4 border-t border-neutral-100">
                <x-button type="button" color="outline" @click="$dispatch('modal-close', 'edit-invoice-modal')">
                    Cancelar
                </x-button>
                <x-button type="submit">
                    Salvar Alterações
                </x-button>
            </div>
        </form>
    </x-modal>
</x-layouts.financial>
