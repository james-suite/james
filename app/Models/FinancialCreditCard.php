<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\TransactionStatus;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class FinancialCreditCard extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'financial_account_id',
        'credit_limit',
        'closing_day',
        'due_day',
    ];

    use HasFactory, Searchable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'closing_day' => 'integer',
            'due_day' => 'integer',
        ];
    }

    /**
     * Get the financial account that pays this credit card's invoices.
     *
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * Get the invoices associated with the credit card.
     *
     * @return HasMany<FinancialCreditCardInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(FinancialCreditCardInvoice::class);
    }

    /**
     * Get the recurrences charged directly to this credit card.
     *
     * @return HasMany<FinancialRecurrence, $this>
     */
    public function recurrences(): HasMany
    {
        return $this->hasMany(FinancialRecurrence::class);
    }

    /**
     * Scope a query to include the used_limit attribute.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithUsedLimit(Builder $query): Builder
    {
        return $query->addSelect([
            'used_limit' => FinancialCreditCardInvoice::selectRaw("COALESCE(SUM(
                GREATEST(0, (
                    SELECT COALESCE(SUM(CASE WHEN type = 'expense' THEN amount WHEN type = 'income' THEN -amount ELSE 0 END), 0)
                    FROM financial_transactions
                    WHERE financial_credit_card_invoice_id = financial_credit_card_invoices.id
                        AND status != ?
                ) - amount_paid)
            ), 0)", [TransactionStatus::Draft->value])
                ->whereColumn('financial_credit_card_id', 'financial_credit_cards.id')
                ->whereNull('paid_at'),
        ]);
    }

    /**
     * Calculate used limit across all non-paid invoices.
     */
    public function usedLimit(): float
    {
        if (isset($this->used_limit)) {
            return (float) $this->used_limit;
        }

        return (float) $this->invoices()
            ->whereNull('paid_at')
            ->get()
            ->sum(fn ($invoice) => max(0, $invoice->total() - $invoice->amount_paid));
    }

    /**
     * Resolve the reference month for a given date based on the card's closing day.
     *
     * Dates before the closing day belong to the current month's invoice. The
     * closing day itself and later dates belong to the next month's invoice.
     * Pure calculation — no database queries.
     */
    public function resolveReferenceMonth(Carbon $date): Carbon
    {
        $candidateMonth = $date->copy()->startOfMonth();
        $closingDate = $candidateMonth->copy()->day((int) min($this->closing_day, $candidateMonth->daysInMonth));

        if ($date->copy()->startOfDay()->lt($closingDate)) {
            return $candidateMonth;
        }

        return $candidateMonth->copy()->addMonthNoOverflow()->startOfMonth();
    }

    /**
     * Resolve the invoice due date for a given purchase/processing date.
     *
     * Determines which invoice period the date belongs to, then returns the
     * corresponding due date. Pure calculation — no database queries.
     */
    public function resolveInvoiceDueDate(Carbon $purchaseDate): Carbon
    {
        $referenceMonth = $this->resolveReferenceMonth($purchaseDate);

        $dueMonth = $this->due_day <= $this->closing_day
            ? $referenceMonth->copy()->addMonthNoOverflow()
            : $referenceMonth->copy();

        return $dueMonth->day((int) min($this->due_day, $dueMonth->daysInMonth));
    }

    /**
     * Resolve the current invoice from the already-eager-loaded invoices relation and
     * set current_invoice_total and current_invoice_status on this model instance.
     *
     * Expects the invoices relation to be loaded with withTotalAmount().
     */
    public function setCurrentInvoice(Carbon $date): void
    {
        $referenceMonth = $this->resolveReferenceMonth($date);

        $currentInvoice = $this->invoices
            ->first(fn ($inv) => $inv->reference_month && $inv->reference_month->copy()->startOfMonth()->eq($referenceMonth));

        if (! $currentInvoice) {
            $currentInvoice = $this->invoices
                ->filter(fn ($inv) => $inv->paid_at === null)
                ->sortByDesc('due_date')
                ->first();
        }

        $this->current_invoice_total = $currentInvoice ? $currentInvoice->total() : 0;
        $this->current_invoice_status = $currentInvoice ? $currentInvoice->status() : InvoiceStatus::Open;
    }

    /**
     * Update closing schedule and recalculate affected open invoices.
     */
    public function updateClosingSchedule(int $closingDay, int $dueDay): void
    {
        DB::transaction(function () use ($closingDay, $dueDay): void {
            $this->update([
                'closing_day' => $closingDay,
                'due_day' => $dueDay,
            ]);

            $this->invoices()
                ->whereNull('paid_at')
                ->get()
                ->each(fn (FinancialCreditCardInvoice $invoice) => $invoice->setRelation('creditCard', $this))
                ->filter(fn (FinancialCreditCardInvoice $invoice) => $invoice->status() === InvoiceStatus::Open)
                ->each(fn (FinancialCreditCardInvoice $invoice) => $invoice->recalculateDates());
        });
    }

    /**
     * Create installment transactions spreading over invoices.
     */
    public function createInstallmentPurchase(
        Carbon $purchaseDate,
        float $totalAmount,
        int $installments,
        string $description,
        string $type = 'expense'
    ): Collection {
        return DB::transaction(function () use ($purchaseDate, $totalAmount, $installments, $description, $type): Collection {
            $firstInvoice = FinancialCreditCardInvoice::resolveForDate($this, $purchaseDate);
            $installmentAmount = round($totalAmount / $installments, 2);

            $transactions = collect();

            for ($i = 1; $i <= $installments; $i++) {
                if ($i === 1) {
                    $invoice = $firstInvoice;
                } else {
                    $invoice = FinancialCreditCardInvoice::resolveForDate(
                        $this,
                        $purchaseDate->copy()->addMonthsNoOverflow($i - 1)
                    );
                }

                $amount = $i === $installments
                    ? $totalAmount - ($installmentAmount * ($installments - 1))
                    : $installmentAmount;

                $transactions->push($invoice->transactions()->create([
                    'financial_account_id' => null,
                    'date' => $purchaseDate,
                    'type' => $type,
                    'amount' => $amount,
                    'description' => $description,
                    'status' => TransactionStatus::Pending,
                    'installment_current' => $i,
                    'installment_total' => $installments,
                ]));
            }

            return $transactions;
        });
    }

    protected static array $recordEvents = ['created', 'updated', 'deleted', 'restored', 'forceDeleted'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('financial_credit_card');
    }
}
