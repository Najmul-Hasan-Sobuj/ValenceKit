<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $check_in
 * @property CarbonImmutable|null $check_out
 */
#[Fillable(['employee_id', 'work_date', 'check_in', 'check_out', 'reason', 'status', 'reviewer_id', 'review_note'])]
class AttendanceCorrection extends Model
{
    protected function casts(): array
    {
        return ['check_in' => 'immutable_datetime', 'check_out' => 'immutable_datetime'];
    }

    /** @return array<string, mixed> */
    public function auditLogValues(): array
    {
        return $this->only(['employee_id', 'work_date', 'check_in', 'check_out', 'status', 'reviewer_id', 'review_note']);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
