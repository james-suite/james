@php
    $formatSignedCurrency = function (float $amount): string {
        if (abs($amount) < 0.005) {
            return formatCurrency(0);
        }

        return ($amount > 0 ? '+ ' : '- ') . formatCurrency(abs($amount));
    };
@endphp

<x-mail::message>
# Vencimentos de hoje e amanhã

Olá, {{ $name }}. Há {{ $alert['total_items'] }} {{ $alert['total_items'] === 1 ? 'item previsto' : 'itens previstos' }} no período.

<x-mail::table>
| Receitas | Despesas | Impacto líquido |
| ---: | ---: | ---: |
| {{ formatCurrency($alert['income']) }} | {{ formatCurrency($alert['expense']) }} | {{ $formatSignedCurrency($alert['net']) }} |
</x-mail::table>

@foreach($alert['days'] as $day)
## {{ $day['label'] }} · {{ $day['date'] }}

@if($day['incomes'] !== [])
### Receitas
@foreach($day['incomes'] as $item)
- **{{ $item['description'] }}** — {{ formatCurrency($item['amount']) }} · {{ $item['destination'] }}@if($item['is_recurrence']) · Recorrente @endif
@endforeach
@endif

@if($day['expenses'] !== [])
### Despesas
@foreach($day['expenses'] as $item)
- **{{ $item['description'] }}** — {{ formatCurrency($item['amount']) }} · {{ $item['destination'] }}@if($item['is_recurrence']) · Recorrente @endif
@endforeach
@endif

@if($day['invoices'] !== [])
### Faturas
@foreach($day['invoices'] as $invoice)
- **{{ $invoice['description'] }}** — {{ formatCurrency($invoice['amount']) }} · {{ $invoice['transactions_count'] }} {{ $invoice['transactions_count'] === 1 ? 'lançamento' : 'lançamentos' }} · {{ $invoice['recurrences_count'] }} {{ $invoice['recurrences_count'] === 1 ? 'recorrência' : 'recorrências' }}
@endforeach
@endif
@endforeach

<x-mail::button :url="$detailUrl">
Ver vencimentos no James
</x-mail::button>

Atenciosamente,  
James
</x-mail::message>
