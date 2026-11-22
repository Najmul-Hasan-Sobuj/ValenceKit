<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'email', 'phone', 'message', 'subject', 'referral_source', 'gmail_thread_id', 'initiated_by_admin'])]
class ContactMessage extends Model
{
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where(fn (Builder $query) => $query->whereNull('read_at')->where('initiated_by_admin', false))
                ->orWhereHas('replies', fn (Builder $query) => $query->where('incoming', true)->where('notification', false)
                    ->whereColumn('contact_replies.id', '>', 'contact_messages.last_read_reply_id'));
        });
    }

    /** @return HasMany<ContactReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(ContactReply::class);
    }

    /** @return HasOne<ContactReply, $this> */
    public function latestReply(): HasOne
    {
        return $this->hasOne(ContactReply::class)->ofMany(
            ['created_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->where('notification', false),
        );
    }
}
