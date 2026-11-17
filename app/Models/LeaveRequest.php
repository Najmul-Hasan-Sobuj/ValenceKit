<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<string> $dates
 */
#[Fillable(['employee_id', 'leave_type_id', 'start_date', 'end_date', 'dates', 'reason', 'status', 'reviewer_id', 'review_note'])]
class LeaveRequest extends Model
{
    protected function casts(): array
    {
        return ['dates' => 'array'];
    }

    /** @return array<string, mixed> */
    public function auditLogValues(): array
    {
        return $this->only(['employee_id', 'leave_type_id', 'start_date', 'end_date', 'dates', 'status', 'reviewer_id', 'review_note']);
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
