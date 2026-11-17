<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('operations:backup {--verify : Verify SQLite restore in an isolated temporary database}')]
#[Description('Write an encrypted database snapshot to private storage')]
class BackupDatabase extends Command
{
    public function handle(DatabaseBackup $backup): int
    {
        try {
            $path = $backup->create((bool) $this->option('verify'));
            Cache::put('operations.backup_completed_at', now()->timestamp, now()->addDays(8));
            $this->info('Encrypted snapshot stored: '.$path);

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Backup failed. Check the database driver, dump client, privileges and available disk space. MySQL restore checks require an isolated staging database.');

            return self::FAILURE;
        }
    }
}
