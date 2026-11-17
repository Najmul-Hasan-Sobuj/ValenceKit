<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'leave_type_id', 'year', 'days', 'reason'])]
class LeaveAllocation extends Model
{
    protected function casts(): array
    {
        return ['days' => 'integer'];
    }

    /** @return array<string, mixed> */
    public function auditLogValues(): array
    {
        return $this->only(['employee_id', 'leave_type_id', 'year', 'days']);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
