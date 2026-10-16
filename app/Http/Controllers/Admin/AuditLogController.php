<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('admin')->can('audit.view'), 403);

        $filters = $this->filters($request);
        $logs = $this->query($filters)
            ->with('actor')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log, int $key): array => $this->present($log));

        return Inertia::render('admin/AuditLogs', [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action')->values(),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request, ExportService $exportService): StreamedResponse
    {
        abort_unless($request->user('admin')->can('audit.export'), 403);

        $query = $this->query($this->filters($request))->orderBy('id');

        $columns = [
            'Date' => fn (AuditLog $log) => $log->created_at?->toIso8601String(),
            'Action' => fn (AuditLog $log) => $log->action,
            'Actor Type' => fn (AuditLog $log) => $log->actor_type,
            'Actor ID' => fn (AuditLog $log) => $log->actor_id,
            'Subject Type' => fn (AuditLog $log) => $log->subject_type,
            'Subject ID' => fn (AuditLog $log) => $log->subject_id,
            'Description' => fn (AuditLog $log) => $log->description,
            'IP Address' => fn (AuditLog $log) => $log->ip_address,
        ];

        return $exportService->exportCsv(
            $query,
            'audit-logs-'.now()->format('Y-m-d').'.csv',
            $columns
        );
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Builder<AuditLog>
     */
    private function query(array $filters): Builder
    {
        return AuditLog::query()
            ->when($filters['search'], function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%");
                });
            })
            ->when($filters['action'], fn ($query, string $action) => $query->where('action', $action))
            ->when($filters['from'], fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'], fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id');
    }

    /**
     * @return array<string, string|null>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]) + [
            'search' => null,
            'action' => null,
            'from' => null,
            'to' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'description' => $log->description,
            'actor' => $log->actor ? [
                'name' => $log->actor->getAttribute('name'),
                'email' => $log->actor->getAttribute('email'),
            ] : null,
            'actor_type' => $log->actor_type,
            'actor_id' => $log->actor_id,
            'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
            'subject_id' => $log->subject_id,
            'metadata' => $log->metadata,
            'before_values' => $log->before_values,
            'after_values' => $log->after_values,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at,
        ];
    }
}
