<?php

namespace App\Services;

use DriveSnap\BackupActivity;
use DriveSnap\EncryptedFile;
use DriveSnap\Models\Backup;
use DriveSnap\Snapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DatabaseBackup
{
    /** Keep the existing operations command on DriveSnap's single backup engine. */
    public function create(bool $verify = false): string
    {
        if ($verify && DB::connection(config('drivesnap.connection'))->getDriverName() !== 'sqlite') {
            throw new RuntimeException('MySQL restore verification requires an isolated staging database.');
        }
        $backup = Backup::create(['upload_to_drive' => false]);
        $restored = null;
        try {
            app(Snapshot::class)->create($backup);
            if ($verify) {
                $restored = Storage::disk('drivesnap')->path('tmp/verify-'.$backup->id.'.sqlite');
                app(EncryptedFile::class)->decrypt(Storage::disk('drivesnap')->path($backup->path), $restored);
                $pdo = new \PDO('sqlite:'.$restored);
                $check = $pdo->query('PRAGMA integrity_check');
                if ($check === false || $check->fetchColumn() !== 'ok') {
                    throw new RuntimeException('Isolated restore verification failed.');
                }
            }
            $backup->update(['status' => 'completed', 'completed_at' => now()]);
            event(new BackupActivity('completed', $backup));

            return $backup->path;
        } catch (Throwable $exception) {
            $backup->update(['status' => 'failed', 'error_code' => 'backup_or_verify_failed']);
            throw $exception;
        } finally {
            if ($restored && is_file($restored)) {
                unlink($restored);
            }
        }
    }
}
