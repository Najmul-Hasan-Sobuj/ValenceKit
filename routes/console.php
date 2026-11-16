<?php

use App\Models\GmailMailbox;
use App\Services\GmailSync;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('contact:sync-gmail', function (GmailSync $sync) {
    $mailbox = GmailMailbox::query()->first();
    if (! $mailbox) {
        return Command::SUCCESS;
    }
    try {
        $sync->sync($mailbox);
    } catch (Throwable) {
        $this->error('Gmail sync failed. Check the mailbox connection.');

        return Command::FAILURE;
    }

    return Command::SUCCESS;
})->purpose('Sync website contact conversations with Gmail');

Schedule::command('contact:sync-gmail')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('proposals:remind')->dailyAt('09:00')->withoutOverlapping();
