<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayFinancialCreditCardInvoiceRequest;
use App\Models\FinancialCreditCard;
use App\Models\FinancialCreditCardInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FinancialCreditCardInvoiceController extends Controller
{
    /**
     * Display the specified invoice.
     */
    public function show(FinancialCreditCard $card, FinancialCreditCardInvoice $invoice): View
    {
        // Ensure invoice belongs to card
        abort_unless($invoice->financial_credit_card_id === $card->id, 404);

        $transactions = $invoice->transactions()
            ->with(['account', 'invoice.creditCard', 'media', 'tags'])
            ->latest('date')
            ->latest('updated_at')
            ->paginate(100)
            ->withQueryString();

        $previousInvoice = $card->invoices()
            ->where('reference_month', '<', $invoice->reference_month)
            ->latest('reference_month')
            ->first();

        $nextInvoice = $card->invoices()
            ->where('reference_month', '>', $invoice->reference_month)
            ->oldest('reference_month')
            ->first();

        return view('finance.cards.invoices.show', compact('card', 'invoice', 'transactions', 'previousInvoice', 'nextInvoice'));
    }

    /**
     * Update the invoice dates.
     */
    public function update(Request $request, FinancialCreditCard $card, FinancialCreditCardInvoice $invoice): RedirectResponse
    {
        abort_unless($invoice->financial_credit_card_id === $card->id, 404);

        $validated = $request->validate([
            'closing_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $invoice->update([
            'closing_date' => $validated['closing_date'],
            'due_date' => $validated['due_date'],
            'notes' => $validated['notes'],
        ]);

        return redirect()
            ->route('financial.cards.invoices.show', [$card, $invoice])
            ->with('success', 'Datas da fatura atualizadas com sucesso.');
    }

    /**
     * Pay the invoice.
     */
    public function pay(PayFinancialCreditCardInvoiceRequest $request, FinancialCreditCard $card, FinancialCreditCardInvoice $invoice): RedirectResponse
    {
        abort_unless($invoice->financial_credit_card_id === $card->id, 404);

        $validated = $request->validated();

        $invoice->registerPayment(
            (float) $validated['amount'],
            Carbon::parse($validated['paid_at']),
            isset($validated['interest_amount']) ? (float) $validated['interest_amount'] : null
        );

        return redirect()
            ->route('financial.cards.invoices.show', [$card, $invoice])
            ->with('success', 'Pagamento registrado com sucesso.');
    }

    /**
     * Unpay (re-open) the invoice.
     */
    public function unpay(FinancialCreditCard $card, FinancialCreditCardInvoice $invoice): RedirectResponse
    {
        abort_unless($invoice->financial_credit_card_id === $card->id, 404);

        $invoice->undoPayment();

        return redirect()
            ->route('financial.cards.invoices.show', [$card, $invoice])
            ->with('success', 'Fatura reaberta com sucesso.');
    }
}
