<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Notifications\BusinessMailNotification;
use Illuminate\Support\Facades\DB;

class WorkflowNotifier
{
    /** @param array<string, int|string> $parameters */
    public function admins(string $permission, string $title, string $body, string $route, array $parameters = []): void
    {
        app(AdminNotifier::class)->send(new AdminNotification($permission, $title, $body, $route, $parameters));
    }

    /** @param array<string, int|string> $parameters */
    public function account(Admin $admin, string $permission, string $title, string $body, string $route, array $parameters = []): void
    {
        DB::afterCommit(function () use ($admin, $permission, $title, $body, $route, $parameters): void {
            $current = Admin::find($admin->id);
            if ($current?->is_active && ($permission === 'account.self' || $current->can($permission))) {
                $current->notify(new AdminNotification($permission, $title, $body, $route, $parameters));
            }
        });
    }

    public function leaveRequested(LeaveRequest $leave): void
    {
        $this->admins('leave.approve', 'Leave request awaiting review', 'A leave request is awaiting your review.', 'admin.hr.index');
        $manager = Employee::find($leave->employee_id)?->manager?->admin;
        if ($manager && ! $manager->can('leave.approve')) {
            $this->account($manager, 'leave.approve-team', 'Team leave request', 'A member of your team requested leave.', 'admin.hr.index');
        }
    }

    public function leaveReviewed(LeaveRequest $leave): void
    {
        $account = Employee::find($leave->employee_id)?->admin;
        if ($account) {
            $this->account($account, 'hr.self', 'Leave request updated', 'Your leave request status is '.$leave->status.'.', 'admin.hr.index');
        }
    }

    public function invoice(Invoice $invoice, string $event): void
    {
        $this->admins('invoices.view', 'Invoice '.$event, $invoice->number.' was '.$event.'.', 'admin.invoices.index');
        if ($invoice->customer_email) {
            $recipient = User::whereRaw('LOWER(email) = ?', [mb_strtolower($invoice->customer_email)])->first() ?? $invoice->customer_email;
            $message = $event === 'issued'
                ? 'Invoice '.$invoice->number.' for '.$invoice->currency.' '.number_format($invoice->amount_minor / 100, 2).' is due on '.$invoice->due_on.'.'
                : 'A payment has been recorded against invoice '.$invoice->number.'.';
            app(MailDeliveryService::class)->queue($invoice, $recipient, new BusinessMailNotification('Invoice '.$invoice->number, $message), 'invoice.'.$event, deduplicationKey: $event === 'issued' ? 'issued' : null);
        }
    }
}
