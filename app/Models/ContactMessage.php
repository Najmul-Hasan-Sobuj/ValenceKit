<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'email', 'phone', 'message', 'subject', 'referral_source', 'gmail_thread_id', 'initiated_by_admin'])]
class ContactMessage extends Model
{
    public function replies(): HasMany
    {
        return $this->hasMany(ContactReply::class);
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(ContactReply::class)->ofMany(
            ['created_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->where('notification', false),
        );
    }
}
