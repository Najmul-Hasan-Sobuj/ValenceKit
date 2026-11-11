<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'work_date', 'check_in', 'check_out', 'schedule', 'reason'])]
class Attendance extends Model
{
    protected function casts(): array
    {
        return ['check_in' => 'immutable_datetime', 'check_out' => 'immutable_datetime', 'schedule' => 'array'];
    }

    public function auditLogValues(): array
    {
        return $this->only(['employee_id', 'work_date', 'check_in', 'check_out', 'schedule']);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
