<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $guarded = ['id'];

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function auditLogValues(): array
    {
        return $this->only(['number', 'customer_name', 'amount_minor', 'currency', 'issued_on', 'due_on']);
    }
}
