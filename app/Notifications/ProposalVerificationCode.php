<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalVerificationCode extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $code, public string $number, public int $revision = 1, public string $total = '') {}

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
            ->subject('Verify your response to '.$this->number)
            ->line('Revision '.$this->revision.' | Total: '.$this->total)
            ->line('A response to document '.$this->number.' requires verification of this email address.')
            ->line('Your verification code is: '.$this->code)
            ->line('This code expires in 10 minutes and can be used once. Do not share it.')
            ->line('If you did not request this code, ignore this email.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
}
