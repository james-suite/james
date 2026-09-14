<x-layouts.financial>
    <div class="mb-6 flex items-center justify-between">
        <x-breadcrumbs>
            <x-breadcrumbs.item href="{{ route('financial.recurrences.index') }}">Recorrências</x-breadcrumbs.item>
            <x-breadcrumbs.item>{{ $recurrence->title }}</x-breadcrumbs.item>
        </x-breadcrumbs>
    </div>

    <x-page-header title="Detalhes da Recorrência">
        <x-back-button fallback="{{ route('financial.recurrences.index') }}" />

        <x-button color="outline" href="{{ route('financial.recurrences.edit', $recurrence) }}" class="bg-white">
            <x-heroicon-o-pencil-square class="size-4" />
            Editar
        </x-button>

        <x-modal.delete
            action="{{ route('financial.recurrences.destroy', $recurrence) }}"
            item-name="a recorrência"
            item-desc="{{ $recurrence->title }}"
            title="Excluir Recorrência"
        />
    </x-page-header>

    <x-card class="mb-8">
        <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-2xl font-bold text-neutral-900">{{ $recurrence->title }}</h2>
                    @if (! $recurrence->is_active)
                        <x-badge color="neutral" size="sm">Pausada</x-badge>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @forelse ($recurrence->tags as $tag)
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-xxs font-semibold" style="background-color: {{ $tag->color_hex }}15; color: {{ $tag->color_hex }}; border-color: {{ $tag->color_hex }}40;">
                            <x-dynamic-component :component="$tag->icon" class="size-3" />
                            {{ $tag->name }}
                        </span>
                    @empty
                        <span class="text-sm text-neutral-500">Sem tags</span>
                    @endforelse
                </div>
            </div>

            <div class="text-left md:text-right">
                <p class="text-sm font-medium text-neutral-500">Valor recorrente</p>
                <p class="text-3xl font-bold {{ $recurrence->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                    {{ $recurrence->type === 'income' ? '+' : '-' }} {{ formatCurrency($recurrence->amount) }}
                </p>
            </div>
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-neutral-100 pt-6 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Frequência</dt>
                <dd class="mt-1 font-medium text-neutral-900">{{ $recurrence->frequency === 'monthly' ? 'Mensal' : 'Anual' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Próximo processamento</dt>
                <dd class="mt-1 font-medium text-neutral-900">{{ formatShort($recurrence->next_processing_date) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Destino</dt>
                <dd class="mt-1 flex items-center gap-1.5 font-medium text-neutral-900">
                    @if ($recurrence->financialCreditCard)
                        <x-heroicon-o-credit-card class="size-4 shrink-0 text-neutral-500" />
                        {{ $recurrence->financialCreditCard->name }}
                    @else
                        <x-heroicon-o-building-library class="size-4 shrink-0 text-neutral-500" />
                        {{ $recurrence->financialAccount?->name }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Vigência</dt>
                <dd class="mt-1 font-medium text-neutral-900">
                    {{ formatShort($recurrence->start_date) }}@if ($recurrence->end_date) até {{ formatShort($recurrence->end_date) }}@endif
                </dd>
            </div>
        </dl>
    </x-card>

    <section aria-labelledby="occurrence-history-heading">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div>
                <h2 id="occurrence-history-heading" class="text-lg font-bold text-neutral-900">Histórico gerado</h2>
                <p class="mt-1 text-sm text-neutral-500">Transações já criadas por esta recorrência.</p>
            </div>
            <x-badge color="neutral" size="sm">{{ $transactions->total() }}</x-badge>
        </div>

        <x-finance.transaction-table :transactions="$transactions" />

        @if ($transactions->hasPages())
            <div class="mt-6 pb-6">
                {{ $transactions->links() }}
            </div>
        @endif
    </section>
</x-layouts.financial>
