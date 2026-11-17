<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Signature('operations:health')]
#[Description('Report queue and scheduler health without exposing job payloads')]
class OperationsHealth extends Command
{
    public function handle(): int
    {
        $scheduler = Cache::get('operations.scheduler_heartbeat');
        $worker = Cache::get('operations.worker_heartbeat');
        $failed = DB::table('failed_jobs')->count();
        $oldest = DB::table('jobs')->where('available_at', '<=', now()->timestamp)->min('created_at');
        $health = [
            'queue_connection' => config('queue.default'),
            'scheduler_ok' => is_numeric($scheduler) && now()->getTimestamp() - (int) $scheduler < 300,
            'worker_ok' => is_numeric($worker) && now()->getTimestamp() - (int) $worker < 300,
            'failed_jobs' => $failed,
            'failed_mail' => DB::table('mail_deliveries')->where('status', 'failed')->count(),
            'undispatched_mail' => DB::table('mail_deliveries')->where('status', 'pending')->where('created_at', '<', now()->subMinutes(10))->count(),
            'failed_exports' => DB::table('data_exports')->where('status', 'failed')->where('expires_at', '>', now())->count(),
            'last_backup_at' => Cache::get('operations.backup_completed_at'),
            'oldest_pending_seconds' => $oldest ? max(0, now()->getTimestamp() - (int) $oldest) : 0,
        ];
        $healthy = $health['scheduler_ok'] && $health['worker_ok'] && $failed === 0 && $health['failed_mail'] === 0 && $health['undispatched_mail'] === 0 && $health['failed_exports'] === 0 && $health['oldest_pending_seconds'] < 600;
        $this->line(json_encode(['healthy' => $healthy, ...$health], JSON_THROW_ON_ERROR));

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
