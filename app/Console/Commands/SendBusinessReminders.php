<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\BusinessMailNotification;
use App\Services\MailDeliveryService;
use App\Services\WorkflowNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('business:remind')]
#[Description('Queue due invoice reminders and notify assigned lead owners')]
class SendBusinessReminders extends Command
{
    public function handle(MailDeliveryService $mail, WorkflowNotifier $notifier): int
    {
        $today = now('Asia/Dhaka')->toDateString();
        Invoice::where('status', 'issued')->whereNotNull('customer_email')->whereDate('due_on', '<=', $today)
            ->withSum('payments', 'amount_minor')->chunkById(100, function ($invoices) use ($mail, $today): void {
                foreach ($invoices as $invoice) {
                    if ((int) $invoice->payments_sum_amount_minor >= $invoice->amount_minor) {
                        continue;
                    }
                    $recipient = User::whereRaw('LOWER(email) = ?', [mb_strtolower($invoice->customer_email)])->first() ?? $invoice->customer_email;
                    $delivery = $mail->queue($invoice, $recipient, new BusinessMailNotification('Invoice payment reminder', 'Invoice '.$invoice->number.' is due. Please contact your account manager if you have already paid.'), 'invoice.reminder', deduplicationKey: $today);
                    $delivery->update(['expires_at' => now()->addDay()]);
                }
            });
        Lead::whereNotIn('stage', ['won', 'lost'])->whereNotNull('assigned_to')->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now())->whereNull('follow_up_notified_at')->chunkById(100, function ($leads) use ($notifier): void {
                foreach ($leads as $lead) {
                    DB::transaction(function () use ($lead, $notifier): void {
                        $lead = Lead::lockForUpdate()->find($lead->id);
                        $admin = $lead ? Admin::find($lead->assigned_to) : null;
                        if (! $lead || $lead->follow_up_notified_at || ! $admin?->is_active || ! $admin->can('leads.view')) {
                            return;
                        }
                        $notifier->account($admin, 'leads.view', 'Lead follow-up due', 'Follow up with '.$lead->name.'.', 'admin.leads.index', ['search' => $lead->name]);
                        $lead->update(['follow_up_notified_at' => now()]);
                    });
                }
            });
        $this->info('Business reminders processed.');

        return self::SUCCESS;
    }
}
