<?php

namespace App\Models;

use App\Concerns\HasMedia;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_code', 'name', 'email', 'phone', 'department_id', 'designation', 'manager_id', 'admin_id', 'joined_on', 'ended_on', 'is_active', 'emergency_contact', 'notes'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    use HasMedia;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return array<string, mixed> */
    public function auditLogValues(): array
    {
        return $this->only(['employee_code', 'name', 'email', 'phone', 'department_id', 'designation', 'manager_id', 'admin_id', 'joined_on', 'ended_on', 'is_active']);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
