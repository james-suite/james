@php
    $formatSignedCurrency = function (float $amount): string {
        if (abs($amount) < 0.005) {
            return formatCurrency(0);
        }

        return ($amount > 0 ? '+ ' : '- ') . formatCurrency(abs($amount));
    };

    $formatComparison = function (float $current, float $variation): string {
        if (abs($variation) < 0.005) {
            return 'Sem variação';
        }

        $previous = $current - $variation;
        $percentage = abs($previous) >= 0.005
            ? ' ('.($variation > 0 ? '+' : '-').number_format(abs(($variation / abs($previous)) * 100), 1, ',', '.').'%)'
            : ' · nova movimentação';

        return ($variation > 0 ? '↑ ' : '↓ ').formatCurrency(abs($variation)).$percentage;
    };
@endphp

<x-mail::message>
# Resumo financeiro de {{ $summary['period'] }}

Olá, {{ $name }}. Este é o fechamento do período, comparado com {{ $summary['previous_period'] }}.

<x-mail::panel>
**Resultado líquido**  
{{ $formatSignedCurrency($summary['net']) }}  
Variação: {{ $formatComparison($summary['net'], $summary['net_variation']) }}
</x-mail::panel>

<x-mail::table>
| Indicador | Valor | Variação |
| :--- | ---: | ---: |
| Receitas | {{ formatCurrency($summary['income']) }} | {{ $formatComparison($summary['income'], $summary['income_variation']) }} |
| Despesas | {{ formatCurrency($summary['expense']) }} | {{ $formatComparison($summary['expense'], $summary['expense_variation']) }} |
</x-mail::table>

## Posição financeira atual

<x-mail::table>
| Indicador | Valor |
| :--- | ---: |
| Saldo em contas | {{ formatCurrency($summary['account_balance']) }} |
| Compromissos pendentes | {{ formatCurrency($summary['pending_commitments']) }} |
| Saldo líquido | {{ $formatSignedCurrency($summary['net_balance']) }} |
</x-mail::table>

@if($summary['expense_categories'] !== [])
## Principais categorias de despesas

<x-mail::table>
| Categoria | Valor | Participação |
| :--- | ---: | ---: |
@foreach(array_slice($summary['expense_categories'], 0, 3) as $category)
| {{ $category['name'] }} | {{ formatCurrency($category['amount']) }} | {{ $category['percentage'] }}% |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="$detailUrl">
Ver resumo completo no James
</x-mail::button>

Atenciosamente,  
James
</x-mail::message>
