<?php

namespace App\Console\Commands;

use App\Models\Proposal;
use App\Models\User;
use App\Notifications\ProposalStatusNotification;
use App\Services\ProposalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendProposalReminders extends Command
{
    protected $signature = 'proposals:remind {--dry-run : List eligible documents without sending email}';

    protected $description = 'Send daily reminders for pending documents and warn before expiration';

    public function handle(ProposalService $documents): int
    {
        $sent = 0;
        $failed = 0;
        Proposal::where('status', 'sent')->whereNull('superseded_at')
            ->whereNotNull('invitation_sent_at')
            ->whereDate('valid_until', '>=', today())
            ->where(fn ($query) => $query->whereNull('last_reminded_at')->orWhereDate('last_reminded_at', '<', today()))
            ->where(fn ($query) => $query->where('invitation_sent_at', '<=', now()->subDays(3))->orWhereDate('valid_until', '<=', today()->addDay()))
            ->chunkById(100, function ($proposals) use ($documents, &$sent, &$failed): void {
                foreach ($proposals as $proposal) {
                    if ($this->option('dry-run')) {
                        $this->line($proposal->number.' — '.$proposal->displayStatus());

                        continue;
                    }
                    Cache::lock('proposal-reminder:'.$proposal->id, 120)->get(function () use ($proposal, $documents, &$sent, &$failed): void {
                        $proposal->refresh();
                        if ($proposal->displayStatus() !== 'sent' || $proposal->last_reminded_at?->isToday()) {
                            return;
                        }
                        $email = $proposal->owner_claimed_at ? User::find($proposal->client_user_id)?->email : $proposal->client_email;
                        if (! $email) {
                            return;
                        }
                        $message = $proposal->valid_until->lte(today()->addDay()) ? 'Action needed: this document expires on '.$proposal->valid_until->toDateString().'.' : 'Reminder: this document is awaiting your response.';
                        if ($documents->queueStatus($proposal, $email, new ProposalStatusNotification($proposal->number, $message, route('proposals.public.show', $proposal)), 'proposal.reminder_sent')) {
                            $proposal->last_reminded_at = now();
                            $proposal->save();
                            $sent++;
                        } else {
                            $failed++;
                        }
                    });
                }
            });
        $this->info('Reminders queued: '.$sent.'. Failed: '.$failed.'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
