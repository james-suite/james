<?php

namespace App\Models;

use App\Enums\FinancialAccountType;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class FinancialAccount extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'type',
        'pix_keys',
        'archived_at',
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
            'type' => FinancialAccountType::class,
            'pix_keys' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /**
     * Get the transactions associated with the account.
     *
     * @return HasMany<FinancialTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /**
     * Get the credit cards associated with the account.
     *
     * @return HasMany<FinancialCreditCard, $this>
     */
    public function creditCards(): HasMany
    {
        return $this->hasMany(FinancialCreditCard::class);
    }

    /**
     * Get the recurrences associated with the account.
     *
     * @return HasMany<FinancialRecurrence, $this>
     */
    public function recurrences(): HasMany
    {
        return $this->hasMany(FinancialRecurrence::class);
    }

    /**
     * Scope a query to include the account balance.
     */
    public function scopeWithBalance(Builder $query): void
    {
        $query->addSelect([
            'balance' => FinancialTransaction::selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount WHEN type = 'expense' THEN -amount ELSE 0 END), 0)")
                ->whereColumn('financial_account_id', 'financial_accounts.id')
                ->posted(),
        ])->withCasts(['balance' => 'float']);
    }

    /**
     * Scope a query to exclude investment accounts.
     */
    public function scopeWithoutInvestments(Builder $query): void
    {
        $query->where('type', '!=', FinancialAccountType::Investment);
    }

    protected static array $recordEvents = ['created', 'updated', 'deleted', 'restored', 'forceDeleted'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('financial_account');
    }
}
