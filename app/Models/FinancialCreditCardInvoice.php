<?php

namespace App\Models;

use App\Enums\FinancialAccountType;
use App\Enums\InvoiceStatus;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class FinancialCreditCardInvoice extends Model
{
    use LogsActivity;

    protected $fillable = [
        'financial_credit_card_id',
        'reference_month',
        'closing_date',
        'due_date',
        'paid_at',
        'notes',
        'interest_transaction_id',
        'payment_transaction_id',
    ];

    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_month' => 'date',
            'closing_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
            'amount_paid' => 'decimal:2',
        ];
    }

    /**
     * Get the credit card that owns this invoice.
     *
     * @return BelongsTo<FinancialCreditCard, $this>
     */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(FinancialCreditCard::class, 'financial_credit_card_id');
    }

    /**
     * Get the transactions associated with the invoice.
     *
     * @return HasMany<FinancialTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /**
     * Get the interest transaction associated with the invoice.
     *
     * @return BelongsTo<FinancialTransaction, $this>
     */
    public function interestTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'interest_transaction_id');
    }

    /**
     * Get the payment transaction associated with partial payments.
     *
     * @return BelongsTo<FinancialTransaction, $this>
     */
    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'payment_transaction_id');
    }

    /**
     * Scope a query to include the total_amount attribute (computed via subquery).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithTotalAmount(Builder $query): Builder
    {
        return $query->addSelect([
            'total_amount' => FinancialTransaction::selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount WHEN type = 'income' THEN -amount ELSE 0 END), 0)")
                ->whereColumn('financial_credit_card_invoice_id', 'financial_credit_card_invoices.id')
                ->withoutDrafts(),
        ]);
    }

    /**
     * Scope a query to only include unpaid invoices.
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNull('paid_at');
    }

    /**
     * Scope a query to only include invoices due between two dates.
     */
    public function scopeDueBetween(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to exclude invoices linked to investment accounts.
     */
    public function scopeWithoutInvestments(Builder $query): Builder
    {
        return $query->whereDoesntHave('creditCard.financialAccount', function ($q) {
            $q->where('type', FinancialAccountType::Investment);
        });
    }

    /**
     * Calculate the total amount of the invoice.
     * Expenses sum, incomes subtract.
     */
    public function total(): float
    {
        if (isset($this->total_amount)) {
            return (float) $this->total_amount;
        }

        return (float) $this->transactions()
            ->withoutDrafts()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount WHEN type = 'income' THEN -amount ELSE 0 END), 0) as total")
            ->value('total');
    }

    /**
     * Get the status of the invoice.
     */
    public function status(): InvoiceStatus
    {
        if ($this->paid_at !== null) {
            return InvoiceStatus::Paid;
        }

        $total = $this->total();

        if ($this->amount_paid > 0 && $this->amount_paid < $total) {
            return InvoiceStatus::PartiallyPaid;
        }

        $today = Carbon::today();

        if ($today->lte($this->closing_date)) {
            return InvoiceStatus::Open;
        }

        if ($today->gt($this->due_date)) {
            return InvoiceStatus::Overdue;
        }

        return InvoiceStatus::Closed;
    }

    /**
     * Check if the invoice is fully paid.
     */
    public function isPaid(): bool
    {
        return $this->status() === InvoiceStatus::Paid;
    }

    /**
     * Register a payment for this invoice.
     */
    public function registerPayment(float $amount, Carbon $paidAt, ?float $interestAmount = null): void
    {
        DB::transaction(function () use ($amount, $paidAt, $interestAmount): void {
            self::query()
                ->lockForUpdate()
                ->findOrFail($this->getKey());

            $this->loadMissing(['creditCard.financialAccount', 'paymentTransaction', 'interestTransaction']);

            $newAmountPaid = $this->amount_paid + $amount;
            $total = $this->total();

            if ($newAmountPaid >= $total) {
                if ($this->payment_transaction_id) {
                    $this->paymentTransaction?->delete();
                }

                $this->transactions()->withoutDrafts()->update([
                    'financial_account_id' => $this->creditCard->financial_account_id,
                    'status' => TransactionStatus::Posted->value,
                ]);

                $this->paid_at = $paidAt;
                $this->amount_paid = $total;
                $this->payment_transaction_id = null;
            } else {
                if ($this->payment_transaction_id) {
                    $this->paymentTransaction()->update([
                        'amount' => $newAmountPaid,
                        'date' => $paidAt,
                    ]);
                } else {
                    $paymentTransaction = $this->creditCard->financialAccount->transactions()->create([
                        'date' => $paidAt,
                        'type' => 'expense',
                        'amount' => $newAmountPaid,
                        'description' => "Pagamento parcial fatura {$this->reference_month->format('m/Y')}",
                        'status' => TransactionStatus::Posted,
                    ]);

                    if (class_exists(FinancialTag::class) && defined('\App\Models\FinancialTag::PAGAMENTO_PARCIAL_ID')) {
                        $paymentTransaction->tags()->attach(FinancialTag::PAGAMENTO_PARCIAL_ID, ['is_primary' => true]);
                    }

                    $this->payment_transaction_id = $paymentTransaction->id;
                }
                $this->amount_paid = $newAmountPaid;
            }

            if ($interestAmount !== null && $interestAmount > 0 && $this->interest_transaction_id === null) {
                $interestTransaction = $this->creditCard->financialAccount->transactions()->create([
                    'date' => $paidAt,
                    'type' => 'expense',
                    'amount' => $interestAmount,
                    'description' => "Juros da fatura {$this->reference_month->format('m/Y')} do cartão {$this->creditCard->name}",
                    'status' => TransactionStatus::Posted,
                ]);

                if (class_exists(FinancialTag::class) && defined('\App\Models\FinancialTag::JUROS_ID')) {
                    $interestTransaction->tags()->attach(FinancialTag::JUROS_ID, ['is_primary' => true]);
                }

                $this->interest_transaction_id = $interestTransaction->id;
            }

            $this->save();
        });
    }

    /**
     * Undo the payment (re-open invoice).
     */
    public function undoPayment(): void
    {
        DB::transaction(function (): void {
            $this->loadMissing(['paymentTransaction', 'interestTransaction']);

            if ($this->payment_transaction_id) {
                $this->paymentTransaction?->delete();
                $this->payment_transaction_id = null;
            }

            if ($this->interest_transaction_id) {
                $this->interestTransaction?->delete();
                $this->interest_transaction_id = null;
            }

            $this->transactions()->withoutDrafts()->update([
                'financial_account_id' => null,
                'status' => TransactionStatus::Pending->value,
            ]);

            $this->paid_at = null;
            $this->amount_paid = 0;
            $this->save();
        });
    }

    /**
     * Recalculate closing and due dates based on current card settings.
     */
    public function recalculateDates(): void
    {
        $this->loadMissing('creditCard');
        $card = $this->creditCard;

        $closingDate = $this->reference_month->copy()->day(
            (int) min($card->closing_day, $this->reference_month->daysInMonth)
        );

        $dueMonth = $card->due_day <= $card->closing_day
            ? $this->reference_month->copy()->addMonthNoOverflow()
            : $this->reference_month->copy();

        $dueDate = $dueMonth->day((int) min($card->due_day, $dueMonth->daysInMonth));

        $this->closing_date = $closingDate;
        $this->due_date = $dueDate;

        $this->save();
    }

    /**
     * Find or create the correct invoice based on the purchase date and card closing/due days.
     *
     * When an invoice already exists for the candidate month, its stored closing_date
     * is used instead of recalculating from the card's closing_day. This ensures that
     * invoices with a customised closing_date are respected when assigning transactions.
     */
    public static function resolveForDate(FinancialCreditCard $card, Carbon $date): self
    {
        // Try current month as the reference month candidate
        $candidateMonth = $date->copy()->startOfMonth();

        // Check if an invoice already exists for the candidate month so we can
        // honour its actual closing_date rather than recalculating from the card's closing_day.
        $existingInvoice = static::where('financial_credit_card_id', $card->id)
            ->where('reference_month', $candidateMonth->toDateString())
            ->first();

        $closingDate = $existingInvoice
            ? $existingInvoice->closing_date->copy()
            : $candidateMonth->copy()->day((int) min($card->closing_day, $candidateMonth->daysInMonth));

        if ($date->copy()->startOfDay()->lte($closingDate)) {
            // Purchases through the closing date belong to the current month's invoice.
            $referenceMonth = $candidateMonth;
        } else {
            // Purchases after the closing date belong to the next month's invoice.
            $referenceMonth = $candidateMonth->copy()->addMonthNoOverflow();
            $closingDate = $referenceMonth->copy()->day((int) min($card->closing_day, $referenceMonth->daysInMonth));
        }

        $dueMonth = $card->due_day <= $card->closing_day
            ? $referenceMonth->copy()->addMonthNoOverflow()
            : $referenceMonth->copy();

        $dueDate = $dueMonth->day((int) min($card->due_day, $dueMonth->daysInMonth));

        return static::firstOrCreate(
            [
                'financial_credit_card_id' => $card->id,
                'reference_month' => $referenceMonth->toDateString(),
            ],
            [
                'closing_date' => $closingDate,
                'due_date' => $dueDate,
            ]
        );
    }

    protected static array $recordEvents = ['created', 'updated', 'deleted'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('financial_credit_card_invoice');
    }
}
