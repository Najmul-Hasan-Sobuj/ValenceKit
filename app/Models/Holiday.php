<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'date'])]
class Holiday extends Model
{
    protected function casts(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function auditLogValues(): array
    {
        return $this->only(['name', 'date']);
    }
}
