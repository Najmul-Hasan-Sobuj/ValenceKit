<?php

namespace App\Notifications;

use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

class AdminInviteNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $token
    ) {
        $this->afterCommit();
        $this->onQueue('mail');
    }

    public int $tries = 3;

    public int $timeout = 30;

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function shouldSend(Admin $notifiable, string $channel): bool
    {
        return $notifiable->is_active && $notifiable->must_change_password && Password::broker('admins')->tokenExists($notifiable, $this->token);
    }

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
    public function toMail(Admin $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to '.config('app.name'))
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('You have been invited to join the '.config('app.name').' administration panel.')
            ->line('Set your password using this invitation link. The link expires in 60 minutes.')
            ->action('Set password', route('admin.password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]))
            ->line('If you did not expect this invitation, you can ignore this email.');
    }
}
