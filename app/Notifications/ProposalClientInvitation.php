<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalClientInvitation extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $number, public string $documentUrl, public ?string $setupUrl = null) {}

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
        $message = (new MailMessage)
            ->subject('Client portal invitation: '.$this->number)
            ->line('You have been invited to review document '.$this->number.'.');

        if ($this->setupUrl !== null) {
            return $message->line('Your client account is ready. Set your own password using this single-use link. It expires in '.config('auth.passwords.users.expire').' minutes.')
                ->action('Set your password', $this->setupUrl)
                ->line('Then sign in with this email address and request an email verification link on the verification screen.')
                ->line('After verifying your email, open your document: '.$this->documentUrl);
        }

        return $message->line('Sign in with this email address and verify it before viewing your documents.')
            ->action('View your document', $this->documentUrl)
            ->line('Your client account only has access to documents addressed to your verified email.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
