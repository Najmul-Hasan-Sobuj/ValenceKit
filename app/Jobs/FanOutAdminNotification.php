<?php

namespace App\Jobs;

use App\Notifications\AdminNotification;
use App\Services\AdminNotifier;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FanOutAdminNotification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public AdminNotification $notification)
    {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(AdminNotifier $notifier): void
    {
        $notifier->send($this->notification, false);
    }
}
