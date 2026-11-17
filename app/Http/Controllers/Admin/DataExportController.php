<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\BuildDataExport;
use App\Models\DataExport;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataExportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('admin')->canAny(['finance.export', 'leads.export', 'hr.export']), 403);

        return Inertia::render('admin/Exports', ['exports' => DataExport::where('admin_id', $request->user('admin')->id)->latest('id')->limit(30)->get()]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'module' => ['required', Rule::in(['finance', 'leads', 'hr'])],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:module,hr', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:module,hr', 'required_with:from', 'after_or_equal:from'],
        ]);
        $export = new DataExport(['module' => $data['module']]);
        abort_unless($request->user('admin')->can($export->permission()), 403);
        if (! empty($data['from']) && CarbonImmutable::parse($data['from'])->diffInDays($data['to']) > 366) {
            throw ValidationException::withMessages(['to' => 'Select at most 367 days.']);
        }
        DB::transaction(function () use ($request, $data, $audit): void {
            $export = DataExport::create(['admin_id' => $request->user('admin')->id, 'module' => $data['module'], 'filters' => array_intersect_key($data, array_flip(['from', 'to'])), 'expires_at' => now()->addDay()]);
            $audit->record('export.requested', $export, actor: $request->user('admin'));
            DB::afterCommit(fn () => BuildDataExport::dispatch($export->id));
        });

        return back()->with('success', 'Export queued. You will be notified when it is ready.');
    }

    public function download(Request $request, DataExport $export, AuditLogService $audit): BinaryFileResponse
    {
        abort_unless($export->admin_id === $request->user('admin')->id && $request->user('admin')->can($export->permission()), 403);
        abort_unless($export->status === 'ready' && $export->expires_at->isFuture() && $export->path && Storage::disk('local')->exists($export->path), 404);
        $audit->record('export.downloaded', $export, actor: $request->user('admin'));

        return response()->download(Storage::disk('local')->path($export->path), $export->module.'-export.csv', ['Cache-Control' => 'private, no-store']);
    }
}
