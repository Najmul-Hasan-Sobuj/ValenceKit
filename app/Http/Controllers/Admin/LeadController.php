<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\LeadResource;
use App\Models\Admin;
use App\Models\Lead;
use App\Services\AuditLogService;
use App\Services\ExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'webhookUrl' => route('api.leads.webhook'),
            'tokens' => $admin->can('leads.import') ? $admin->tokens()->where('revoked', false)
                ->where('expires_at', '>', now())->get()->filter(fn ($token) => $token->scopes === ['leads:import'])
                ->map(fn ($token) => $token->only(['id', 'name', 'expires_at']))->values() : [],
        ]);
    }

    public function export(Request $request, ExportService $export): StreamedResponse
    {
        abort_unless($request->user('admin')->can('leads.export'), 403);

        return $export->exportCsv(Lead::query()->orderBy('id'), 'leads.csv', [
            'Name' => fn (Lead $lead) => $lead->name,
            'Phone' => fn (Lead $lead) => $lead->phone,
            'Email' => fn (Lead $lead) => $lead->email,
            'Address' => fn (Lead $lead) => $lead->address,
            'Website' => fn (Lead $lead) => $lead->website,
            'Google Maps' => fn (Lead $lead) => $lead->maps_url,
        ]);
    }

    public function destroy(Request $request, Lead $lead, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('leads.delete'), 403);
        $before = $lead->auditLogValues();
        $lead->delete();
        $audit->deleted('lead.deleted', $lead, $before, actor: $request->user('admin'));

        return back()->with('success', 'Lead deleted.');
    }
}
