<?php

use App\Jobs\QueueHeartbeat;
use App\Jobs\SyncGmailMailbox;
use App\Models\GmailMailbox;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('contact:sync-gmail', function () {
    $mailbox = GmailMailbox::query()->first();
    if (! $mailbox) {
        return Command::SUCCESS;
    }
    try {
        SyncGmailMailbox::dispatch($mailbox->id)->afterCommit();
    } catch (Throwable) {
        $this->error('Gmail sync failed. Check the mailbox connection.');

        return Command::FAILURE;
    }

    return Command::SUCCESS;
})->purpose('Sync website contact conversations with Gmail');

Schedule::command('contact:sync-gmail')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('proposals:remind')->dailyAt('09:00')->timezone('Asia/Dhaka')->withoutOverlapping()->onOneServer();

Schedule::command('finance:run-recurring')->dailyAt('00:10')->timezone('Asia/Dhaka')->withoutOverlapping()->onOneServer();
Schedule::command('business:remind')->dailyAt('09:15')->timezone('Asia/Dhaka')->withoutOverlapping()->onOneServer();

Schedule::call(function (): void {
    Cache::put('operations.scheduler_heartbeat', now()->timestamp, now()->addDay());
    QueueHeartbeat::dispatch();
})->name('operations-heartbeat')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('operations:prune --apply')->dailyAt('03:00')->timezone('Asia/Dhaka')->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily()->withoutOverlapping();
Schedule::command('queue:prune-batches --hours=168')->daily()->withoutOverlapping();
Schedule::command('mail:recover-pending')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
