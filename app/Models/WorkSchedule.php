<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'start_time', 'end_time', 'weekdays', 'grace_minutes', 'timezone', 'is_active'])]
class WorkSchedule extends Model
{
    protected function casts(): array
    {
        return ['weekdays' => 'array', 'is_active' => 'boolean'];
    }

    public function auditLogValues(): array
    {
        return $this->only(['name', 'start_time', 'end_time', 'weekdays', 'grace_minutes', 'timezone', 'is_active']);
    }
}
