<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalStatusNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public string $number, public string $message, public string $documentUrl) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->number.' — document update')
            ->line($this->message)
            ->action('View document', $this->documentUrl);
    }
}
