<?php

namespace App\Models;

use App\Enums\FinancialAccountType;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Nota de Implementação (Validação):
 * Em qualquer Form Request que manipule a criação ou edição de uma Recorrência,
 * deve-se garantir a validação lógica XOR entre `financial_account_id` e `financial_credit_card_id`.
 * Ou seja, exatamente UM dos dois campos deve estar preenchido (usando `required_without` / `prohibits`).
 */
class FinancialRecurrence extends Model
{
    use LogsActivity;

    protected $fillable = [
        'title',
        'amount',
        'type',
        'frequency',
        'financial_account_id',
        'financial_credit_card_id',
        'start_date',
        'end_date',
        'next_processing_date',
        'is_active',
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
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_processing_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the financial account associated with the recurrence.
     *
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /**
     * Get the financial credit card associated with the recurrence.
     *
     * @return BelongsTo<FinancialCreditCard, $this>
     */
    public function financialCreditCard(): BelongsTo
    {
        return $this->belongsTo(FinancialCreditCard::class);
    }

    /**
     * Get the transactions materialized from this recurrence.
     *
     * @return HasMany<FinancialTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'financial_recurrence_id');
    }

    /**
     * Return the day of the month on which the recurrence should be processed.
     */
    public function dayOfMonth(): int
    {
        return $this->start_date->day;
    }

    /**
     * Count remaining scheduled occurrences from next_processing_date through end_date (inclusive).
     */
    public function remainingOccurrencesCount(): int
    {
        if (! $this->is_active || $this->end_date === null || $this->next_processing_date === null) {
            return 0;
        }

        $cursor = $this->next_processing_date->copy()->startOfDay();
        $endDate = $this->end_date->copy()->startOfDay();

        if ($cursor->gt($endDate)) {
            return 0;
        }

        $count = 0;

        while ($cursor->lte($endDate)) {
            $count++;

            if ($this->frequency === 'monthly') {
                $monthsSinceStart = (($cursor->year - $this->start_date->year) * 12)
                    + $cursor->month
                    - $this->start_date->month
                    + 1;
                $next = $this->start_date->copy()->startOfDay()->addMonthsNoOverflow($monthsSinceStart);

                while ($next->lte($cursor)) {
                    $monthsSinceStart++;
                    $next = $this->start_date->copy()->startOfDay()->addMonthsNoOverflow($monthsSinceStart);
                }

                $cursor = $next;
            } elseif ($this->frequency === 'yearly') {
                $yearsSinceStart = $cursor->year - $this->start_date->year + 1;
                $next = $this->start_date->copy()->startOfDay()->addYearsNoOverflow($yearsSinceStart);

                while ($next->lte($cursor)) {
                    $yearsSinceStart++;
                    $next = $this->start_date->copy()->startOfDay()->addYearsNoOverflow($yearsSinceStart);
                }

                $cursor = $next;
            } else {
                break;
            }
        }

        return $count;
    }

    /**
     * Calculate the remaining committed amount for a finite recurrence (with end_date).
     */
    public function remainingAmount(): float
    {
        return round((float) $this->amount * $this->remainingOccurrencesCount(), 2);
    }

    /**
     * Get all of the tags for the recurrence.
     */
    public function tags()
    {
        return $this->morphToMany(
            FinancialTag::class,
            'financial_taggable',
            'financial_taggables',
            'financial_taggable_id',
            'financial_tag_id'
        )->withPivot('is_primary');
    }

    /**
     * Scope a query to only include active recurrences.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include recurrences with next processing date between two dates.
     */
    public function scopeNextProcessingBetween(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('next_processing_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to exclude recurrences linked to investment accounts.
     */
    public function scopeWithoutInvestments(Builder $query): Builder
    {
        return $query->whereDoesntHave('financialAccount', function ($q) {
            $q->where('type', FinancialAccountType::Investment);
        })->whereDoesntHave('financialCreditCard.financialAccount', function ($q) {
            $q->where('type', FinancialAccountType::Investment);
        });
    }

    /**
     * Scope a query to only include recurrences for specific accounts.
     */
    public function scopeForAccounts(Builder $query, ?array $accountIds): Builder
    {
        if (empty($accountIds)) {
            return $query;
        }

        return $query->where(function ($sub) use ($accountIds) {
            $sub->whereIn('financial_account_id', $accountIds)
                ->orWhereHas('financialCreditCard', function ($q2) use ($accountIds) {
                    $q2->whereIn('financial_account_id', $accountIds);
                });
        });
    }

    protected static array $recordEvents = ['created', 'updated', 'deleted', 'restored', 'forceDeleted'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('financial_recurrence');
    }
}
