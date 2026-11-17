<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\DataExport;
use App\Models\Lead;
use App\Services\ExportService;
use App\Services\FinanceService;
use App\Services\HrService;
use App\Services\WorkflowNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BuildDataExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $exportId)
    {
        $this->onQueue('exports');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('export:'.$this->exportId))->releaseAfter(30)->expireAfter(360)];
    }

    public function handle(ExportService $csv, FinanceService $finance, HrService $hr): void
    {
        $export = DataExport::find($this->exportId);
        if (! $export || $export->status === 'ready') {
            return;
        }
        $admin = Admin::find($export->admin_id);
        if (! $admin?->is_active || ! $admin->can($export->permission()) || $export->expires_at->isPast()) {
            $export->update(['status' => 'cancelled']);

            return;
        }
        $path = $export->path ?? 'exports/'.Str::uuid().'.csv';
        $export->update(['status' => 'processing', 'path' => $path]);
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');
        $output = fopen($disk->path($path), 'wb');
        if ($output === false) {
            throw new \RuntimeException('Cannot prepare export file.');
        }
        chmod($disk->path($path), 0600);
        try {
            if ($export->module === 'hr') {
                $filters = $export->filters;
                if (! isset($filters['from'], $filters['to'])) {
                    throw new \RuntimeException('Export dates are missing.');
                }
                fputcsv($output, ['Employee ID', 'Name', 'Date', 'Status', 'Check-in', 'Check-out', 'Duration minutes', 'Late minutes', 'Timezone'], escape: '');
                foreach ($hr->employees($admin)->orderBy('id')->lazyById(100) as $employee) {
                    foreach ($hr->report(collect([$employee]), $filters['from'], $filters['to']) as $row) {
                        fputcsv($output, array_map($csv->escapeFormula(...), array_values($row)), escape: '');
                    }
                }
            } else {
                $query = $export->module === 'finance' ? $finance->filteredTransactionQuery($export->filters)->with('admin')->orderBy('id') : Lead::orderBy('id');
                $csv->writeCsv($query, $export->module === 'finance' ? $finance->exportColumns() : $csv->leadColumns(), $output);
            }
        } catch (Throwable) {
            fclose($output);
            $disk->delete($path);
            throw new \RuntimeException('Export could not be prepared.');
        }
        fclose($output);
        $export->update(['status' => 'ready', 'path' => $path]);
        app(WorkflowNotifier::class)->account($admin, $export->permission(), 'Export ready', 'Your private export is available for 24 hours.', 'admin.exports.index');
    }

    public function failed(?Throwable $exception): void
    {
        DataExport::whereKey($this->exportId)->where('status', '!=', 'ready')->update(['status' => 'failed']);
    }
}
