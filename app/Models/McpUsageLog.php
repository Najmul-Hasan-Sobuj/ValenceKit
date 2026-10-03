<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpUsageLog extends Model
{
    protected $fillable = [
        'token_used',
        'user_id',
        'admin_id',
        'ip_address',
        'user_agent',
        'endpoint',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
