<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $expires_at
 * @property array{from?: string, to?: string} $filters
 */
class DataExport extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path', 'filters'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'expires_at' => 'datetime'];
    }

    public function permission(): string
    {
        return match ($this->module) {
            'finance' => 'finance.export', 'leads' => 'leads.export', 'hr' => 'hr.export', default => throw new \LogicException('Unknown export module.')
        };
    }
}
