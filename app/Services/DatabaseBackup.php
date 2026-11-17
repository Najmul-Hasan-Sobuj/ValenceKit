<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseBackup
{
    /** Create an encrypted private snapshot. Restore checks always use a temporary SQLite database. */
    public function create(bool $verify = false): string
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new \RuntimeException('Use the database provider backup service for this driver.');
        }
        if ($verify && $driver !== 'sqlite') {
            throw new \RuntimeException('MySQL restore verification requires a separate staging database. No live database was changed.');
        }
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');
        $temporary = tempnam($disk->path('backups'), '.snapshot-');
        if ($temporary === false) {
            throw new \RuntimeException('Cannot prepare backup.');
        }
        chmod($temporary, 0600);
        $credentials = null;
        $restored = null;
        try {
            if ($driver === 'sqlite') {
                unlink($temporary);
                $connection->statement('VACUUM INTO '.$connection->getPdo()->quote($temporary));
                chmod($temporary, 0600);
            } else {
                $credentials = tempnam($disk->path('backups'), '.mysql-');
                if ($credentials === false) {
                    throw new \RuntimeException('Cannot prepare backup credentials.');
                }
                chmod($credentials, 0600);
                $quote = static fn (string $value): string => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value).'"';
                $options = "[client]\n";
                foreach (['host', 'port', 'username', 'password', 'unix_socket'] as $key) {
                    $value = (string) $connection->getConfig($key);
                    if ($value !== '') {
                        $option = match ($key) {
                            'username' => 'user', 'unix_socket' => 'socket', default => $key
                        };
                        $options .= $option.'='.$quote($value)."\n";
                    }
                }
                file_put_contents($credentials, $options);
                $result = Process::timeout(120)->run([(string) config('database.backup_binary', 'mysqldump'), '--defaults-extra-file='.$credentials, '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--result-file='.$temporary, '--', $connection->getDatabaseName()]);
                if (! $result->successful()) {
                    throw new \RuntimeException('Database snapshot failed. Check the dump client and database privileges.');
                }
            }
            clearstatcache(true, $temporary);
            if (filesize($temporary) > 64 * 1024 * 1024) {
                throw new \RuntimeException('Use managed database snapshots for databases larger than 64 MiB.');
            }
            $plaintext = file_get_contents($temporary);
            if ($plaintext === false || $plaintext === '') {
                throw new \RuntimeException('Database snapshot is empty.');
            }
            $encrypted = Crypt::encryptString($plaintext);
            if ($verify) {
                $restored = $temporary.'.restore';
                file_put_contents($restored, Crypt::decryptString($encrypted));
                chmod($restored, 0600);
                $pdo = new \PDO('sqlite:'.$restored);
                $check = $pdo->query('PRAGMA integrity_check');
                if ($check === false || $check->fetchColumn() !== 'ok') {
                    throw new \RuntimeException('Isolated restore verification failed.');
                }
                unset($pdo);
            }
            $path = 'backups/'.now()->format('Ymd-His').'-'.Str::uuid().'.'.$driver.'.enc';
            if (! $disk->put($path, $encrypted)) {
                throw new \RuntimeException('Cannot store encrypted backup.');
            }
            chmod($disk->path($path), 0600);

            return $path;
        } finally {
            foreach ([$temporary, $credentials, $restored] as $file) {
                if ($file && is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
