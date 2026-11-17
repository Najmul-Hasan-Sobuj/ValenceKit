<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return array<string, mixed> */
    public function auditLogValues(): array
    {
        return $this->only(['employee_id', 'period', 'currency', 'status', 'approved_by', 'paid_by', 'paid_on', 'payment_reference']);
    }
}
