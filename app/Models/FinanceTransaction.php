<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $amount
 * @property string $currency
 * @property string $category_key
 * @property string $category_name
 * @property string $description
 * @property string|null $reference
 * @property Carbon $transaction_date
 * @property string $payment_method
 * @property bool $is_recurring
 * @property string|null $recurring_frequency
 * @property string|null $notes
 * @property int $admin_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Admin $admin
 */
#[Fillable([
    'type',
    'amount',
    'currency',
    'category_key',
    'category_name',
    'description',
    'reference',
    'transaction_date',
    'payment_method',
    'is_recurring',
    'recurring_frequency',
    'notes',
    'admin_id',
])]
class FinanceTransaction extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    public function auditLogValues(): array
    {
        return $this->only(['type', 'amount', 'currency', 'category_key', 'description', 'reference', 'transaction_date', 'payment_method']);
    }

    /**
     * Generate a unique transaction reference identifier.
     */
    public static function generateReference(): string
    {
        return 'TRX-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    /**
     * Get the admin that recorded this transaction.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * Scope a query to only include income transactions.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', 'income');
    }

    /**
     * Scope a query to only include expense transactions.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', 'expense');
    }

    /**
     * Scope a query to filter transactions between two dates.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeBetweenDates(Builder $query, string|CarbonInterface $from, string|CarbonInterface $to): Builder
    {
        $fromDate = $from instanceof CarbonInterface ? $from->toDateString() : Carbon::parse($from)->toDateString();
        $toDate = $to instanceof CarbonInterface ? $to->toDateString() : Carbon::parse($to)->toDateString();

        return $query->whereDate('transaction_date', '>=', $fromDate)
            ->whereDate('transaction_date', '<=', $toDate);
    }

    /**
     * Scope a query to transactions within a given month.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month);
    }
}
