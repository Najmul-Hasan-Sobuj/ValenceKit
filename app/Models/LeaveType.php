<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'is_paid', 'is_active'])]
class LeaveType extends Model
{
    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return array<string, mixed> */
    public function auditLogValues(): array
    {
        return $this->only(['name', 'is_paid', 'is_active']);
    }
}
