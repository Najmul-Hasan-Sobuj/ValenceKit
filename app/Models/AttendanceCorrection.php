<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'work_date', 'check_in', 'check_out', 'reason', 'status', 'reviewer_id', 'review_note'])]
class AttendanceCorrection extends Model
{
    protected function casts(): array
    {
        return ['check_in' => 'immutable_datetime', 'check_out' => 'immutable_datetime'];
    }

    public function auditLogValues(): array
    {
        return $this->only(['employee_id', 'work_date', 'check_in', 'check_out', 'status', 'reviewer_id', 'review_note']);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
