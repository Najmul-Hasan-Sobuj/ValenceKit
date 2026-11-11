<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'leave_type_id', 'start_date', 'end_date', 'dates', 'reason', 'status', 'reviewer_id', 'review_note'])]
class LeaveRequest extends Model
{
    protected function casts(): array
    {
        return ['dates' => 'array'];
    }

    public function auditLogValues(): array
    {
        return $this->only(['employee_id', 'leave_type_id', 'start_date', 'end_date', 'dates', 'status', 'reviewer_id', 'review_note']);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
