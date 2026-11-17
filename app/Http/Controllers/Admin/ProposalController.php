<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Proposal\StoreProposalRequest;
use App\Http\Requests\Admin\Proposal\UpdateProposalRequest;
use App\Http\Resources\Admin\ProposalResource;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\ProposalClientInvitation;
use App\Notifications\ProposalStatusNotification;
use App\Services\AuditLogService;
use App\Services\ProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function preview(Request $request, Proposal $proposal, ProposalService $documents): Response|JsonResponse
    {
        abort_unless($request->user('admin')->can('proposals.view'), 403);
        $data = $documents->documentData($proposal, true);
        if (! $request->user('admin')->can('invoices.view')) {
            $data['invoice'] = null;
        }

        $props = ['proposal' => $data, 'preview' => true, 'retry_after' => 0];

        return $request->wantsJson() ? response()->json($props) : Inertia::render('public/Proposal', $props);
    }

    public function revise(Request $request, Proposal $proposal, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('proposals.create') && $request->user('admin')->can('proposals.edit'), 403);
        $copy = DB::transaction(function () use ($request, $proposal, $audit): Proposal {
            $root = Proposal::whereKey($proposal->root_id ?? $proposal->id)->lockForUpdate()->firstOrFail();
            $revision = (int) Proposal::where(fn ($query) => $query->whereKey($root->id)->orWhere('root_id', $root->id))->max('revision') + 1;
            $copy = $proposal->replicate(['public_id', 'number', 'recipient_verification', 'recipient_verified_at', 'accepted_snapshot', 'responded_at', 'signer_name', 'response_note', 'superseded_at', 'last_reminded_at', 'invitation_status', 'invitation_sent_at']);
            $copy->number = mb_substr($root->number, 0, 85).'-R'.$revision;
            $copy->forceFill(['revision' => $revision]);
            $copy->root_id = $root->id;
            $copy->status = 'draft';
            $copy->forceFill(['created_by' => $request->user('admin')->id]);
            $copy->issued_on = today();
            $copy->valid_until = today()->addDays(14);
            $copy->save();
            $audit->created('proposal.revision_created', $copy, description: 'Created revision '.$revision.' from '.$proposal->number.'.', actor: $request->user('admin'));

            return $copy;
        });

        return to_route('admin.proposals.index', ['search' => $copy->number])->with('success', 'Draft revision created. Edit it, then mark it sent.');
    }

    public function transfer(Request $request, Proposal $proposal, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('proposals.transfer'), 403);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'reason' => ['required', 'string', 'max:1000']]);
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->whereNotNull('email_verified_at')->first();
        if (! $user) {
            return back()->withErrors(['email' => 'The new recipient must have a verified client account.']);
        }
        DB::transaction(function () use ($proposal, $user, $request, $data, $audit): void {
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            abort_if(in_array($locked->status, ['accepted', 'rejected'], true), 409);
            $before = ['client_user_id' => $locked->client_user_id, 'client_email' => $locked->client_email];
            $locked->forceFill(['client_user_id' => $user->id]);
            $locked->owner_claimed_at = now();
            $locked->client_email = $user->email;
            $locked->client_name = $user->name;
            $locked->recipient_verification = null;
            $locked->save();
            $audit->record('proposal.recipient_transferred', $locked, beforeValues: $before, afterValues: ['client_user_id' => $user->id, 'client_email' => $user->email], description: $data['reason'], actor: $request->user('admin'));
        });

        return back()->with('success', 'Recipient transferred. Prior access and verification codes were revoked.');
    }

    public function invoice(Request $request, Proposal $proposal, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('proposals.view') && $request->user('admin')->can('invoices.create'), 403);
        $data = $request->validate(['percentage' => ['required', 'integer', 'min:1', 'max:100'], 'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']]);
        $invoice = DB::transaction(function () use ($request, $proposal, $data, $audit): Invoice {
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'accepted', 409);
            $existing = Invoice::where('proposal_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }
            $amount = (int) round($locked->total_minor * $data['percentage'] / 100);
            if ($amount < 1) {
                throw ValidationException::withMessages(['percentage' => 'The invoice amount must be at least one minor unit.']);
            }
            $invoice = Invoice::create(['proposal_id' => $locked->id, 'number' => 'INV-P'.$locked->id, 'customer_name' => $locked->client_name, 'customer_email' => $locked->client_email, 'description' => mb_substr($data['percentage'].'% of '.$locked->number.' — '.$locked->title, 0, 255), 'amount_minor' => $amount, 'currency' => $locked->currency, 'issued_on' => today()->toDateString(), 'due_on' => $data['due_on'], 'status' => 'draft']);
            $audit->created('invoice.created', $invoice, actor: $request->user('admin'));
            $audit->record('proposal.invoice_created', $locked, description: 'Draft '.$invoice->number.' created ('.$data['percentage'].'%).', actor: $request->user('admin'));

            return $invoice;
        });

        return back()->with('success', 'Invoice draft '.$invoice->number.' is available in Invoices.');
    }

    public function receipt(Request $request, Proposal $proposal, ProposalService $documents): RedirectResponse
    {
        abort_unless($request->user('admin')->can('proposals.edit'), 403);
        abort_unless(in_array($proposal->status, ['accepted', 'rejected'], true), 409);
        $email = User::find($proposal->client_user_id)?->email;
        abort_unless(is_string($email) && $email !== '', 409);
        $sent = $documents->queueStatus($proposal, $email, new ProposalStatusNotification($proposal->number, 'Revision '.$proposal->revision.' was '.$proposal->status.'. Your response receipt is available in the portal.', route('proposals.public.show', $proposal)), 'proposal.receipt_sent');

        return back()->with($sent ? 'success' : 'error', $sent ? 'Receipt queued for delivery.' : 'Receipt could not be queued. Please retry.');
    }

    public function remind(Request $request, Proposal $proposal, ProposalService $documents): RedirectResponse
    {
        abort_unless($request->user('admin')->can('proposals.edit'), 403);
        $response = Cache::lock('proposal-reminder:'.$proposal->id, 120)->get(function () use ($proposal, $documents): RedirectResponse {
            $proposal->refresh();
            abort_unless($proposal->displayStatus() === 'sent', 409);
            if ($proposal->last_reminded_at?->isToday()) {
                return back()->with('error', 'A reminder has already been sent today.');
            }
            $email = $proposal->owner_claimed_at ? User::find($proposal->client_user_id)?->email : $proposal->client_email;
            abort_unless(is_string($email) && $email !== '', 409);
            $sent = $documents->queueStatus($proposal, $email, new ProposalStatusNotification($proposal->number, 'Reminder: revision '.$proposal->revision.' awaits your response before '.$proposal->valid_until->toDateString().'.', route('proposals.public.show', $proposal)), 'proposal.reminder_sent');
            if ($sent) {
                $proposal->last_reminded_at = now();
                $proposal->save();
            }

            return back()->with($sent ? 'success' : 'error', $sent ? 'Reminder queued for delivery.' : 'Reminder could not be queued. Please retry.');
        });

        return $response ?: back()->with('error', 'A reminder is already being sent.');
    }

    public function invite(Request $request, Proposal $proposal, AuditLogService $audit, ProposalService $documents): RedirectResponse
    {
        abort_unless($request->user('admin')->can('proposals.edit'), 403);
        abort_if($proposal->status === 'draft', 409);
        $email = $proposal->owner_claimed_at ? User::find($proposal->client_user_id)?->email : $proposal->client_email;
        if (! $email) {
            return back()->with('error', 'The linked client account was deleted. Transfer this document to a verified account before inviting.');
        }
        $user = $proposal->owner_claimed_at
            ? User::findOrFail($proposal->client_user_id)
            : (User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first()
                ?? User::firstOrCreate(['email' => mb_strtolower($email)], ['name' => $proposal->client_name, 'password' => Str::random(64)]));
        if (! $user->hasVerifiedEmail()) {
            $status = $documents->sendPasswordLink($proposal, $user, true);
            if ($status === Password::RESET_THROTTLED) {
                return back()->with('error', 'Please wait one minute before requesting another setup link.');
            }
            $sent = $status === Password::RESET_LINK_SENT;
        } else {
            $sent = $documents->deliver($proposal, $email, new ProposalClientInvitation($proposal->number, route('proposals.public.show', $proposal)), 'proposal.invitation_delivery');
        }
        $proposal->invitation_status = $sent ? 'sent' : 'failed';
        if ($sent) {
            $proposal->invitation_sent_at = now();
        }
        $proposal->save();
        if (! $sent) {
            return back()->with('error', 'Invitation delivery failed. Use Invite client to retry.');
        }
        $audit->record('proposal.client_invited', $proposal, actor: $request->user('admin'));

        return back()->with('success', 'Client invitation sent. The recipient must sign in and verify their email to view the document.');
    }

    public function passwordReset(Request $request, Proposal $proposal, ProposalService $documents): RedirectResponse
    {
        abort_unless($request->user('admin')->can('proposals.view') && $request->user('admin')->can('clients.password-reset'), 403);
        $user = $proposal->owner_claimed_at
            ? User::find($proposal->client_user_id)
            : User::whereRaw('LOWER(email) = ?', [mb_strtolower($proposal->client_email)])->first();
        if (! $user) {
            return back()->with('error', 'No linked client account. Invite the client first.');
        }
        $status = $documents->sendPasswordLink($proposal, $user);

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', match ($status) {
            Password::RESET_LINK_SENT => 'Password reset link sent to the client. Their password remains unchanged until they use it.',
            Password::RESET_THROTTLED => 'Please wait one minute before requesting another reset link.',
            default => 'Password reset delivery failed. Please retry.',
        });
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user('admin')->can('proposals.view'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['proposal', 'quotation', 'contract'])],
            'status' => ['nullable', Rule::in(['draft', 'sent', 'accepted', 'rejected', 'expired', 'superseded'])],
        ]);
        $search = $filters['search'] ?? '';
        $status = $filters['status'] ?? null;

        $proposals = Proposal::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', '%'.$search.'%')
                ->orWhere('title', 'like', '%'.$search.'%')
                ->orWhere('client_name', 'like', '%'.$search.'%')
                ->orWhere('client_email', 'like', '%'.$search.'%')))
            ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when($status === 'expired', fn ($query) => $query->where('status', 'sent')->whereNull('superseded_at')->whereDate('valid_until', '<', today()))
            ->when($status === 'sent', fn ($query) => $query->where('status', 'sent')->whereNull('superseded_at')->whereDate('valid_until', '>=', today()))
            ->when($status === 'superseded', fn ($query) => $query->whereNotNull('superseded_at'))
            ->when($status && ! in_array($status, ['expired', 'sent', 'superseded'], true), fn ($query) => $query->where('status', $status))
            ->latest('id')->paginate(15)->withQueryString();

        return Inertia::render('admin/proposals/Index', [
            'proposals' => ProposalResource::collection($proposals),
            'filters' => ['search' => $search, 'type' => $filters['type'] ?? 'all', 'status' => $status ?? 'all'],
        ]);
    }

    public function store(StoreProposalRequest $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->withTotals($request->validated());

        DB::transaction(function () use ($data, $request, $audit): void {
            $proposal = Proposal::create([...$data, 'created_by' => $request->user('admin')->id]);
            $audit->created('proposal.created', $proposal, description: 'Proposal '.$proposal->number.' was created.', actor: $request->user('admin'));
        });

        return back()->with('success', 'Document created successfully.');
    }

    public function update(UpdateProposalRequest $request, Proposal $proposal, AuditLogService $audit): RedirectResponse
    {
        $data = $this->withTotals($request->validated());
        $updated = DB::transaction(function () use ($data, $request, $proposal, $audit): bool {
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                return false;
            }
            if ($locked->owner_claimed_at && mb_strtolower($data['client_email']) !== mb_strtolower($locked->client_email)) {
                throw ValidationException::withMessages(['client_email' => 'Use Transfer recipient to change the linked client.']);
            }

            $before = $locked->auditLogValues();
            $locked->update($data);
            $locked->recipient_verification = null;
            $locked->save();
            if ($data['status'] === 'sent' && $locked->root_id) {
                Proposal::where(fn ($query) => $query->whereKey($locked->root_id)->orWhere('root_id', $locked->root_id))->where('id', '!=', $locked->id)->where('status', 'sent')->whereNull('superseded_at')->update(['superseded_at' => now(), 'recipient_verification' => null]);
            }
            $audit->updated('proposal.updated', $locked, $before, description: 'Proposal '.$locked->number.' was updated.', actor: $request->user('admin'));

            return true;
        });

        if (! $updated) {
            return back()->with('error', 'Only drafts can be edited. Create a new revision for a sent or completed document.');
        }

        return back()->with('success', 'Document updated successfully.');
    }

    public function destroy(Request $request, Proposal $proposal, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('proposals.delete'), 403);

        $deleted = DB::transaction(function () use ($request, $proposal, $audit): bool {
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'accepted' || Proposal::where('root_id', $locked->id)->exists()) {
                return false;
            }

            $before = $locked->auditLogValues();
            $number = $locked->number;
            $locked->delete();
            $audit->deleted('proposal.deleted', $locked, $before, description: 'Proposal '.$number.' was deleted.', actor: $request->user('admin'));

            return true;
        });

        if (! $deleted) {
            return back()->with('error', 'Accepted documents cannot be deleted.');
        }

        return back()->with('success', 'Document deleted successfully.');
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withTotals(array $data): array
    {
        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $subtotal += (int) round((float) $item['quantity'] * (int) $item['unit_price_minor']);
        }
        $discount = (int) $data['discount_minor'];

        if ($subtotal > 100000000000) {
            throw ValidationException::withMessages(['items' => 'The subtotal cannot exceed 100000000000 minor units.']);
        }

        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount_minor' => 'The discount cannot exceed the subtotal.']);
        }

        $taxable = $subtotal - $discount;
        $tax = (int) round($taxable * ((int) $data['tax_rate_basis_points'] / 10000));

        return [...$data, 'subtotal_minor' => $subtotal, 'tax_minor' => $tax, 'total_minor' => $taxable + $tax];
    }
}
