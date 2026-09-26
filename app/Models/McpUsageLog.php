<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpUsageLog extends Model
{
    protected $fillable = [
        'token_used',
        'user_id',
        'ip_address',
        'user_agent',
        'endpoint',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
