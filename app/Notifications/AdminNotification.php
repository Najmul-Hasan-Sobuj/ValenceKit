<?php

namespace App\Notifications;

class AdminNotification extends UserNotification
{
    /** @param array<string, int|string> $parameters */
    public function __construct(public string $permission, public string $title, public string $body, public string $routeName, public array $parameters = []) {}

    public function toArray(object $notifiable): array
    {
        return ['permission' => $this->permission, ...parent::toArray($notifiable)];
    }
}
