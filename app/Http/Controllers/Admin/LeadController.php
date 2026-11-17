<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\LeadResource;
use App\Models\Admin;
use App\Models\Lead;
use App\Services\AuditLogService;
use App\Services\ExportService;
use App\Services\WorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin && $admin->can('leads.view'), 403);
        $search = $request->validate(['search' => ['nullable', 'string', 'max:255']])['search'] ?? '';

        return Inertia::render('admin/leads/Index', [
            'leads' => LeadResource::collection(Lead::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                ->latest('id')->paginate(15)->withQueryString()),
            'filters' => ['search' => $search],
            'assignees' => $admin->can('leads.manage') ? Admin::permission('leads.view')->where('is_active', true)->orderBy('name')->get(['id', 'name']) : [],
            'webhookUrl' => route('api.leads.webhook'),
            'tokens' => $admin->can('leads.import') ? $admin->tokens()->where('revoked', false)
                ->where('expires_at', '>', now())->get()->filter(fn ($token) => $token->scopes === ['leads:import'])
                ->map(fn ($token) => $token->only(['id', 'name', 'expires_at']))->values() : [],
        ]);
    }

    public function export(Request $request, ExportService $export): StreamedResponse
    {
        abort_unless($request->user('admin')->can('leads.export'), 403);

        return $export->exportCsv(Lead::query()->orderBy('id'), 'leads.csv', $export->leadColumns());
    }

    public function destroy(Request $request, Lead $lead, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('leads.delete'), 403);
        DB::transaction(function () use ($lead, $audit, $request): void {
            $before = $lead->auditLogValues();
            $lead->delete();
            $audit->deleted('lead.deleted', $lead, $before, actor: $request->user('admin'));
        });

        return back()->with('success', 'Lead deleted.');
    }

    public function update(Request $request, Lead $lead, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('leads.manage'), 403);
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', Rule::exists('admins', 'id')->where('is_active', true)],
            'stage' => ['required', Rule::in(['new', 'contacted', 'qualified', 'won', 'lost'])],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);
        $assignee = empty($data['assigned_to']) ? null : Admin::whereKey($data['assigned_to'])->first();
        if ($assignee && ! $assignee->can('leads.view')) {
            throw ValidationException::withMessages(['assigned_to' => 'Choose an active account with leads access.']);
        }
        if (in_array($data['stage'], ['won', 'lost'], true)) {
            $data['next_follow_up_at'] = null;
        }
        DB::transaction(function () use ($request, $lead, $audit, $data, $assignee): void {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            $before = $lead->auditLogValues();
            $previousOwner = $lead->assigned_to;
            $lead->fill($data);
            if ($lead->isDirty(['next_follow_up_at', 'assigned_to', 'stage'])) {
                $lead->follow_up_notified_at = null;
            }
            $lead->save();
            $audit->updated('lead.updated', $lead, $before, actor: $request->user('admin'));
            if ($assignee && $previousOwner !== $assignee->id) {
                app(WorkflowNotifier::class)->account($assignee, 'leads.view', 'Lead assigned', $lead->name.' was assigned to you.', 'admin.leads.index', ['search' => $lead->name]);
            }
        });

        return back()->with('success', 'Lead workflow updated.');
    }
}
