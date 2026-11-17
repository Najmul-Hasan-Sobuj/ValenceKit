<?php

namespace App\Models;

use App\Notifications\BusinessMailNotification;
use App\Notifications\ProposalStatusNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expires_at
 */
class MailDelivery extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['email', 'notification'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['notification' => 'encrypted:array', 'email' => 'encrypted', 'attempts' => 'integer', 'sent_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function message(): Notification
    {
        $data = $this->getAttribute('notification');
        if (! is_array($data)) {
            throw new \RuntimeException('Delivery message is unavailable.');
        }

        return match ($data['type'] ?? null) {
            'proposal' => new ProposalStatusNotification($data['number'], $data['message'], $data['url']),
            'business' => new BusinessMailNotification($data['title'], $data['message']),
            default => throw new \RuntimeException('Unknown delivery message.'),
        };
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
