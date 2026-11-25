<?php

namespace App\Http\Controllers;

use App\Http\Requests\RespondToProposalRequest;
use App\Models\Admin;
use App\Models\Proposal;
use App\Notifications\ProposalStatusNotification;
use App\Notifications\ProposalVerificationCode;
use App\Services\AuditLogService;
use App\Services\ProposalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProposalResponseController extends Controller
{
    public function __construct(private ProposalService $documents) {}

    public function index(Request $request): Response
    {
        $this->documents->claimDocuments($request->user('web'));
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['proposal', 'quotation', 'contract'])],
            'status' => ['nullable', Rule::in(['pending', 'expiring', 'completed'])],
        ]);
        $search = $filters['search'] ?? '';
        $query = Proposal::where('client_user_id', $request->user('web')->id)->where('status', '!=', 'draft');
        $pending = (clone $query)->where('status', 'sent')->whereNull('superseded_at')->whereDate('valid_until', '>=', today());
        $summary = ['pending' => (clone $pending)->count(), 'expiring' => (clone $pending)->whereDate('valid_until', '<=', today()->addDays(3))->count(), 'completed' => (clone $query)->whereIn('status', ['accepted', 'rejected'])->count()];

        return Inertia::render('client/Proposals', [
            'summary' => $summary,
            'documents' => $query
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$search.'%')->orWhere('number', 'like', '%'.$search.'%')))
                ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
                ->when(in_array($filters['status'] ?? '', ['pending', 'expiring'], true), fn ($query) => $query->where('status', 'sent')->whereNull('superseded_at')->whereDate('valid_until', '>=', today()))
                ->when(($filters['status'] ?? '') === 'expiring', fn ($query) => $query->whereDate('valid_until', '<=', today()->addDays(3)))
                ->when(($filters['status'] ?? '') === 'completed', fn ($query) => $query->whereIn('status', ['accepted', 'rejected']))
                ->latest('id')->paginate(15)->withQueryString()
                ->through(fn (Proposal $proposal): array => [
                    'public_id' => $proposal->public_id, 'number' => $proposal->number, 'revision' => $proposal->revision,
                    'title' => $proposal->title, 'type' => $proposal->type, 'status' => $proposal->displayStatus(),
                    'total_minor' => $proposal->total_minor, 'currency' => $proposal->currency,
                    'valid_until' => $proposal->valid_until->format('Y-m-d'),
                ]),
            'filters' => ['search' => $search, 'type' => $filters['type'] ?? '', 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function sendCode(Request $request, Proposal $proposal): RedirectResponse
    {
        $proposal = $this->documents->authorizeRecipient($request->user('web'), $proposal);
        $data = $request->validate(['fingerprint' => ['required', 'string', 'size:64']]);
        $key = 'proposal-verification:'.$proposal->id;
        if (RateLimiter::tooManyAttempts($key, 3) || $this->documents->codeRetryAfter($proposal) > 0) {
            return to_route('proposals.public.show', $proposal)->withErrors(['verification_code' => 'Please wait before requesting another code. Maximum three codes per hour.']);
        }
        $code = (string) random_int(100000, 999999);
        $challenge = DB::transaction(function () use ($request, $proposal, $data, $code): array {
            $locked = $this->documents->authorizeRecipient($request->user('web'), $proposal);
            if ($locked->displayStatus() !== 'sent') {
                return [];
            }
            if (! hash_equals($locked->approvalFingerprint(), $data['fingerprint'])) {
                return [];
            }
            $challenge = ['hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10)->getTimestamp(), 'attempts' => 0, 'fingerprint' => $locked->approvalFingerprint(), 'delivered' => false];
            $locked->recipient_verification = $challenge;
            $locked->save();

            return $challenge;
        });
        if ($challenge === []) {
            return to_route('proposals.public.show', $proposal)->withErrors(['verification_code' => 'The document changed. Reload and review the latest revision before requesting a code.']);
        }
        RateLimiter::hit($key, 3600);
        RateLimiter::hit('proposal-code-cooldown:'.$proposal->id, 60);
        $proposal->refresh();
        $sent = $this->documents->deliver($proposal, $request->user('web')->email, new ProposalVerificationCode($code, $proposal->number, $proposal->revision, $proposal->currency.' '.number_format($proposal->total_minor / 100, 2, '.', '')), 'proposal.code_sent');
        DB::transaction(function () use ($proposal, $challenge, $sent): void {
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if (($locked->recipient_verification['hash'] ?? null) === $challenge['hash']) {
                $locked->recipient_verification = $sent ? [...$challenge, 'delivered' => true] : null;
                $locked->save();
            }
        });
        if (! $sent) {
            return to_route('proposals.public.show', $proposal)->withErrors(['verification_code' => 'Email could not be sent. Please retry after the cooldown.']);
        }

        return to_route('proposals.public.show', $proposal)->with('success', 'Verification code sent. It expires in 10 minutes.');
    }

    public function show(Request $request, Proposal $proposal, AuditLogService $audit): Response
    {
        $proposal = $this->documents->authorizeRecipient($request->user('web'), $proposal);
        $audit->record('proposal.viewed', $proposal, actor: $request->user('web'));

        return Inertia::render('public/Proposal', [
            'proposal' => $this->documents->documentData($proposal),
            'preview' => false,
            'retry_after' => $this->documents->codeRetryAfter($proposal),
        ]);
    }

    public function respond(RespondToProposalRequest $request, Proposal $proposal, AuditLogService $audit): RedirectResponse
    {
        $this->documents->authorizeRecipient($request->user('web'), $proposal);
        $responded = DB::transaction(function () use ($request, $proposal, $audit): bool {
            $locked = $this->documents->authorizeRecipient($request->user('web'), $proposal);
            if ($locked->displayStatus() !== 'sent' || ! hash_equals($locked->approvalFingerprint(), $request->validated('fingerprint'))) {
                return false;
            }
            $verification = $locked->recipient_verification;
            if (! is_array($verification) || ! ($verification['delivered'] ?? false) || $verification['expires_at'] <= now()->timestamp || $verification['attempts'] >= 5 || ! hash_equals($verification['fingerprint'], $locked->approvalFingerprint())) {
                return false;
            }
            $verification['attempts']++;
            $locked->recipient_verification = $verification;
            $locked->save();
            if (! Hash::check($request->validated('verification_code'), $verification['hash'])) {
                $audit->record('proposal.verification_failed', $locked, actor: $request->user('web'));

                return false;
            }
            $before = $locked->auditLogValues();
            $locked->update([
                'status' => $request->validated('decision'),
                'signer_name' => $request->validated('decision') === 'accepted' ? $request->validated('signer_name') : null,
                'response_note' => $request->validated('response_note'), 'responded_at' => now(),
            ]);
            $locked->accepted_snapshot = $locked->documentValues();
            $locked->recipient_verification = null;
            $locked->recipient_verified_at = now();
            $locked->save();
            $audit->updated('proposal.responded', $locked, $before, description: 'Client '.$locked->status.' revision '.$locked->revision.'.', actor: $request->user('web'));
            $audit->record('proposal.recipient_verified', $locked, metadata: ['method' => 'email_code', 'recipient_email' => $request->user('web')->email, 'document_fingerprint' => $verification['fingerprint']], actor: $request->user('web'));

            return true;
        });
        if (! $responded) {
            return to_route('proposals.public.show', $proposal)->withErrors(['verification_code' => 'The code is invalid, expired, exhausted, or this document has changed. Reload and review the document, then request a new code.'])->with('error', 'Your response could not be recorded.');
        }
        $proposal->refresh();
        $url = route('proposals.public.show', $proposal);
        $receipt = new ProposalStatusNotification($proposal->number, 'Revision '.$proposal->revision.' was '.$proposal->status.'. Response recorded at '.$proposal->responded_at->toIso8601String().'. Your document and approval receipt are available in the portal.', $url);
        $this->documents->queueStatus($proposal, $request->user('web')->email, $receipt, 'proposal.receipt_sent');
        $creator = Admin::find($proposal->created_by);
        if ($creator) {
            $this->documents->queueStatus($proposal, $creator->email, new ProposalStatusNotification($proposal->number, 'Client '.$proposal->status.' revision '.$proposal->revision.'.', route('admin.proposals.preview', $proposal)), 'proposal.admin_notified', $creator);
        }

        return to_route('proposals.public.show', $proposal)->with('success', 'Your verified response has been recorded.');
    }
}
