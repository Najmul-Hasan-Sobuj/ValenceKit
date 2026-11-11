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

    public function auditLogValues(): array
    {
        return $this->only(['name', 'date']);
    }
}
