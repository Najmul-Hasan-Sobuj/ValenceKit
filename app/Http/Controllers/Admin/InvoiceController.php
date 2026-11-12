<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('admin')->can('invoices.view'), 403);

        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '';

        return Inertia::render('admin/billing/Invoices', [
            'invoices' => Invoice::query()->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('number', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%')))
                ->withSum('payments', 'amount_minor')->latest('id')->paginate(15)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('invoices.create'), 403);
        $data = $request->validate([
            'number' => ['required', 'string', 'max:100', 'unique:invoices,number'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'issued_on' => ['required', 'date_format:Y-m-d'],
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:issued_on'],
        ]);
        DB::transaction(function () use ($data, $audit, $request): void {
            $invoice = Invoice::create($data);
            $audit->created('invoice.created', $invoice, actor: $request->user('admin'));
        });

        return back()->with('success', 'Invoice created.');
    }

    public function payment(Request $request, Invoice $invoice, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('invoices.payments.create'), 403);
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($invoice, $data, $audit, $request): void {
            $lockedInvoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $remaining = $lockedInvoice->amount_minor - $lockedInvoice->payments()->sum('amount_minor');
            if ((int) $data['amount_minor'] > $remaining) {
                throw ValidationException::withMessages(['amount_minor' => 'The payment cannot exceed the outstanding amount.']);
            }
            $payment = $lockedInvoice->payments()->create($data);
            $audit->created('invoice.payment.created', $payment, actor: $request->user('admin'));
        });

        return back()->with('success', 'Payment recorded.');
    }
}
