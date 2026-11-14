<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportGmailThread;
use App\Models\ContactMessage;
use App\Models\GmailMailbox;
use App\Services\AuditLogService;
use App\Services\GmailSender;
use App\Services\GmailSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContactMessageController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('admin')->can('contact.view'), 403);

        $search = trim($request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '');
        $mailbox = GmailMailbox::query()->first();

        return Inertia::render('admin/contact/Index', [
            'messages' => $this->conversationPage($search),
            'filters' => ['search' => $search],
            'gmail' => [
                'email' => $mailbox && $mailbox->refresh_token !== '' ? $mailbox->email : null,
                'configured' => (bool) (config('services.gmail.client_id') && config('services.gmail.client_secret')),
                'callback_url' => route('admin.contact.gmail.callback'),
            ],
        ]);
    }

    public function more(Request $request): JsonResponse
    {
        abort_unless($request->user('admin')->can('contact.view'), 403);

        $search = trim($request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '');

        return response()->json($this->conversationPage($search));
    }

    public function messages(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        abort_unless($request->user('admin')->can('contact.view'), 403);

        return response()->json($contactMessage->replies()->where('notification', false)
            ->select('id', 'contact_message_id', 'body', 'incoming', 'created_at', 'gmail_message_id')
            ->orderByDesc('created_at')->orderByDesc('id')->cursorPaginate(10));
    }

    private function conversationPage(string $search): CursorPaginator
    {
        return ContactMessage::query()->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
            ->where('name', 'like', '%'.$search.'%')
            ->orWhere('email', 'like', '%'.$search.'%')
            ->orWhere('subject', 'like', '%'.$search.'%')))
            ->with(['latestReply' => fn ($query) => $query
                ->select('contact_replies.id', 'contact_replies.contact_message_id', 'contact_replies.incoming', 'contact_replies.created_at', 'contact_replies.gmail_message_id')
                ->selectRaw('SUBSTR(contact_replies.body, 1, 200) as body')])
            ->latest('id')->cursorPaginate(15);
    }

    public function reply(Request $request, ContactMessage $contactMessage, GmailSender $sender, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('contact.reply') && $request->user('admin')->can('contact.view'), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $mailbox = GmailMailbox::query()->first();
        if (! $mailbox) {
            return back()->withErrors(['body' => 'Connect Gmail before sending replies.']);
        }

        try {
            $sender->notifyIfNeeded($mailbox, $contactMessage);
            $messageId = $sender->send($mailbox, $contactMessage, $data['body']);
        } catch (\Throwable) {
            return back()->withErrors(['body' => 'The reply could not be sent. Please check the Gmail connection.']);
        }
        $reply = $contactMessage->replies()->create(['body' => $data['body'], 'rfc_message_id' => $messageId]);
        $audit->record('contact.reply.sent', $reply, actor: $request->user('admin'));

        return back()->with('success', 'Reply sent.');
    }

    public function start(Request $request, GmailSender $sender, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('contact.reply') && $request->user('admin')->can('contact.view'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);
        $mailbox = GmailMailbox::query()->first();
        if (! $mailbox) {
            return back()->withErrors(['body' => 'Connect Gmail before sending a message.']);
        }
        $contact = ContactMessage::create(['name' => $data['name'], 'email' => $data['email'], 'message' => $data['body'], 'initiated_by_admin' => true]);
        try {
            $messageId = $sender->send($mailbox, $contact, $data['body']);
        } catch (\Throwable) {
            $contact->delete();

            return back()->withErrors(['body' => 'The message could not be sent. Please check the Gmail connection.']);
        }
        $reply = $contact->replies()->create(['body' => $data['body'], 'rfc_message_id' => $messageId]);
        $audit->record('contact.conversation.started', $reply, actor: $request->user('admin'));

        return back()->with('success', 'Message sent.');
    }

    public function sync(Request $request, GmailSync $sync): RedirectResponse
    {
        abort_unless($request->user('admin')->can('contact.manage') && $request->user('admin')->can('contact.view'), 403);
        $mailbox = GmailMailbox::query()->first();
        if (! $mailbox) {
            return back()->withErrors(['gmail' => 'Connect Gmail before syncing.']);
        }
        try {
            $sync->sync($mailbox);
        } catch (\Throwable) {
            return back()->withErrors(['gmail' => 'Could not sync Gmail. Please check the connection and try again.']);
        }

        return back()->with('success', 'Gmail conversations synced.');
    }

    public function searchGmail(Request $request, GmailSync $sync): JsonResponse
    {
        abort_unless($request->user('admin')->can('contact.manage') && $request->user('admin')->can('contact.view'), 403);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'before' => ['nullable', 'string', 'regex:/^\d+$/']]);
        $mailbox = GmailMailbox::query()->first();
        if (! $mailbox) {
            throw ValidationException::withMessages(['email' => 'Connect Gmail before searching.']);
        }
        try {
            return response()->json($sync->searchThreads($mailbox, $data['email'], $data['before'] ?? null));
        } catch (\Throwable) {
            return response()->json(['message' => 'Could not search Gmail. Please try again.'], 502);
        }
    }

    public function importGmail(Request $request): JsonResponse
    {
        abort_unless($request->user('admin')->can('contact.manage') && $request->user('admin')->can('contact.view'), 403);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'threads' => ['required', 'array', 'min:1', 'max:100'],
            'threads.*.thread_id' => ['required', 'string', 'regex:/^\d+$/', 'distinct'],
            'threads.*.uid' => ['required', 'string', 'regex:/^\d+$/'],
        ]);
        $mailbox = GmailMailbox::query()->first();
        if (! $mailbox) {
            throw ValidationException::withMessages(['threads' => 'Connect Gmail before importing.']);
        }
        $adminId = $request->user('admin')->id;
        $jobs = collect($data['threads'])->map(fn ($thread) => new ImportGmailThread($adminId, $mailbox->id, $data['email'], $thread['thread_id'], $thread['uid']))->all();
        $batch = Bus::batch($jobs)->name('contact-gmail-import')->allowFailures()
            ->withOption('admin_id', $adminId)->withOption('email', strtolower($data['email']))
            ->withOption('thread_ids', array_column($data['threads'], 'thread_id'))->dispatch();

        return response()->json(['batch_id' => $batch->id], 202);
    }

    public function gmailImportStatus(Request $request, string $batch): JsonResponse
    {
        abort_unless($request->user('admin')->can('contact.manage') && $request->user('admin')->can('contact.view'), 403);
        $batch = Bus::findBatch($batch);
        abort_unless($batch && $batch->name === 'contact-gmail-import' && ($batch->options['admin_id'] ?? null) === $request->user('admin')->id, 404);
        $finished = $batch->pendingJobs <= $batch->failedJobs;

        return response()->json([
            'total' => $batch->totalJobs, 'processed' => $batch->totalJobs - $batch->pendingJobs + $batch->failedJobs,
            'failed' => $batch->failedJobs, 'finished' => $finished,
            'imported_ids' => $finished ? ContactMessage::query()->where('email', $batch->options['email'])->whereIn('gmail_thread_id', $batch->options['thread_ids'])->pluck('gmail_thread_id') : [],
        ]);
    }
}
