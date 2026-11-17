<?php

namespace App\Console\Commands;

use App\Models\DataExport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('operations:prune {--days=90 : Retention in days, minimum 30} {--apply : Delete eligible records; otherwise preview only}')]
#[Description('Prune old read notifications and successful mail delivery records; preserve audit history')]
class PruneOperationalData extends Command
{
    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        if ($days === false || $days < 30) {
            $this->error('Retention must be at least 30 days.');

            return self::FAILURE;
        }
        $before = now()->subDays($days);
        $expired = DataExport::where('expires_at', '<', now())->get();
        $this->line('expired_exports: '.$expired->count());
        if ($this->option('apply')) {
            foreach ($expired as $export) {
                if ($export->path && ! Storage::disk('local')->delete($export->path)) {
                    continue;
                }
                $export->delete();
            }
        }
        $disk = Storage::disk('local');
        $backups = collect($disk->files('backups'))->filter(fn (string $path): bool => str_ends_with($path, '.enc'));
        $hasRecentBackup = $backups->contains(fn (string $path): bool => $disk->lastModified($path) > now()->subDay()->getTimestamp());
        if ($hasRecentBackup) {
            $oldBackups = $backups->filter(fn (string $path): bool => $disk->lastModified($path) < now()->subDays(30)->getTimestamp());
            $this->line('expired_backups: '.$oldBackups->count());
            if ($this->option('apply')) {
                $disk->delete($oldBackups->values()->all());
            }
        }
        $queries = [
            'notifications' => DB::table('notifications')->whereNotNull('read_at')->where('read_at', '<', $before),
            'mail_deliveries' => DB::table('mail_deliveries')->whereIn('status', ['sent', 'cancelled'])->where('updated_at', '<', $before),
        ];
        foreach ($queries as $name => $query) {
            $count = $this->option('apply') ? $query->delete() : $query->count();
            $this->line($name.': '.$count.($this->option('apply') ? ' removed' : ' eligible'));
        }

        return self::SUCCESS;
    }
}
