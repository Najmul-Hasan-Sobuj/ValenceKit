<?php

use App\Jobs\QueueHeartbeat;
use App\Services\DatabaseBackup;
use DriveSnap\EncryptedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('reports missing workers and accepts fresh scheduler and worker heartbeats', function () {
    Cache::forget('operations.worker_heartbeat');
    Cache::forget('operations.scheduler_heartbeat');
    $this->artisan('operations:health')->assertFailed();
    Cache::put('operations.scheduler_heartbeat', now()->timestamp);
    (new QueueHeartbeat)->handle();
    $this->artisan('operations:health')->assertSuccessful();
});

it('encrypts a database snapshot and verifies restoration away from the source database', function () {
    Storage::fake('drivesnap');
    // VACUUM requires an isolated connection outside the test transaction.
    config(['database.connections.backup_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    $original = config('drivesnap.connection');
    config(['drivesnap.connection' => 'backup_test']);
    DB::connection('backup_test')->statement('CREATE TABLE restore_probe (value TEXT)');
    DB::connection('backup_test')->insert('INSERT INTO restore_probe VALUES (?)', ['restore-test']);
    try {
        $path = app(DatabaseBackup::class)->create(true);
        expect(Storage::disk('drivesnap')->get($path))->not->toContain('restore-test');
        $restored = Storage::disk('drivesnap')->path('verified.sqlite');
        app(EncryptedFile::class)->decrypt(Storage::disk('drivesnap')->path($path), $restored);
        $pdo = new PDO('sqlite:'.$restored);
        expect($pdo->query('PRAGMA integrity_check')->fetchColumn())->toBe('ok');
        expect(DB::connection('backup_test')->table('restore_probe')->value('value'))->toBe('restore-test');
    } finally {
        config(['drivesnap.connection' => $original]);
        DB::purge('backup_test');
    }
});

it('previews retention without deleting business audit history', function () {
    $this->artisan('operations:prune')->assertSuccessful();
    $this->artisan('operations:prune', ['--days' => 1])->assertFailed();
    $this->artisan('operations:prune', ['--apply' => true])->assertSuccessful();
});
