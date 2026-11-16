<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read int|float|string|null $payments_sum_amount_minor */
class Invoice extends Model
{
    protected $guarded = ['id'];

    /** @return HasMany<InvoicePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /** @return array<string, mixed> */
    public function auditLogValues(): array
    {
        return $this->only(['number', 'customer_name', 'amount_minor', 'currency', 'issued_on', 'due_on']);
    }
}
