<?php

namespace App\Http\Controllers;

use App\Models\SuperadminBillingCharge;
use App\Models\SuperadminBill;
use App\Models\SuperadminBillPayment;
use App\Models\SuperadminFinanceEntry;
use App\Models\SuperadminLogisticsInvoice;
use App\Models\LedgerCategory;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantCreditTransaction;
use App\Models\TenantPayment;
use App\Models\ActivityLog;
use App\Mail\TenantTemplateMail;
use App\Services\TenantMailService;
use App\Services\CourierNotificationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperadminFinanceController extends Controller
{
    public function entries(Request $request)
    {
        $this->ensureDefaultLedgerCategories();
        $entries = $this->filteredEntries($request)->latest('entry_date')->paginate($this->perPage(request(), 15))->withQueryString();
        $summary = $this->summary($request);
        $chart = $this->chartData($request);
        $categories = $this->ledgerCategories();

        return view('superadmin.finance.entries', compact('entries', 'summary', 'chart', 'categories'));
    }

    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:income,expense',
            'category' => ['nullable', 'string', 'max:120', Rule::exists('ledger_categories', 'name')->where(fn ($query) => $query->where('portal', 'superadmin')->where('scope_key', 'superadmin')->where('type', $request->type)->where('is_active', true))],
            'new_category' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'entry_date' => 'required|date',
            'reference_number' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);

        $data['category'] = $this->ledgerCategoryFromRequest($request, $data);
        unset($data['new_category']);

        SuperadminFinanceEntry::create($data + ['created_by' => auth()->id()]);

        return back()->with('success', 'Finance entry saved.');
    }

    public function updateEntry(Request $request, SuperadminFinanceEntry $entry)
    {
        $data = $request->validate([
            'type' => 'required|in:income,expense',
            'category' => ['nullable', 'string', 'max:120', Rule::exists('ledger_categories', 'name')->where(fn ($query) => $query->where('portal', 'superadmin')->where('scope_key', 'superadmin')->where('type', $request->type)->where('is_active', true))],
            'new_category' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'entry_date' => 'required|date',
            'reference_number' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);

        $data['category'] = $this->ledgerCategoryFromRequest($request, $data);
        unset($data['new_category']);

        $entry->update($data);

        return back()->with('success', 'Finance entry updated.');
    }

    public function categories()
    {
        $this->ensureDefaultLedgerCategories();
        $categories = LedgerCategory::where('portal', 'superadmin')
            ->where('scope_key', 'superadmin')
            ->orderBy('type')
            ->orderBy('name')
            ->paginate($this->perPage(request(), 20));

        return view('superadmin.finance.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:income,expense',
            'name' => ['required', 'string', 'max:120', Rule::unique('ledger_categories', 'name')->where(fn ($query) => $query->where('portal', 'superadmin')->where('scope_key', 'superadmin')->where('type', $request->type))],
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        LedgerCategory::create($data + [
            'portal' => 'superadmin',
            'scope_key' => 'superadmin',
            'tenant_id' => null,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Ledger category created.');
    }

    public function updateCategory(Request $request, LedgerCategory $category)
    {
        abort_if($category->portal !== 'superadmin' || $category->scope_key !== 'superadmin', 404);

        $data = $request->validate([
            'type' => 'required|in:income,expense',
            'name' => ['required', 'string', 'max:120', Rule::unique('ledger_categories', 'name')->ignore($category->id)->where(fn ($query) => $query->where('portal', 'superadmin')->where('scope_key', 'superadmin')->where('type', $request->type))],
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $oldType = $category->type;
        $oldName = $category->name;
        $category->update($data + ['is_active' => $request->boolean('is_active')]);

        if ($oldType !== $category->type || $oldName !== $category->name) {
            SuperadminFinanceEntry::where('type', $oldType)
                ->where('category', $oldName)
                ->update(['type' => $category->type, 'category' => $category->name]);
        }

        return back()->with('success', 'Ledger category updated.');
    }

    public function destroyCategory(LedgerCategory $category)
    {
        abort_if($category->portal !== 'superadmin' || $category->scope_key !== 'superadmin', 404);

        $inUse = SuperadminFinanceEntry::where('type', $category->type)->where('category', $category->name)->exists();
        if ($inUse) {
            return back()->withErrors(['category' => 'This category is used by ledger entries. Deactivate it instead of deleting it.']);
        }

        $category->delete();

        return back()->with('success', 'Ledger category deleted.');
    }

    public function destroyEntry(SuperadminFinanceEntry $entry)
    {
        $entry->delete();

        return back()->with('success', 'Finance entry deleted.');
    }

    public function importEntries(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:4096']);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($column) => Str::snake(trim((string) $column)), fgetcsv($handle) ?: []);
        $created = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            if (! $data || ! in_array($data['type'] ?? '', ['income', 'expense'], true)) {
                continue;
            }
            SuperadminFinanceEntry::create([
                'type' => $data['type'],
                'category' => $data['category'] ?? 'Imported',
                'description' => $data['description'] ?? null,
                'amount' => (float) ($data['amount'] ?? 0),
                'entry_date' => $data['entry_date'] ?? now()->toDateString(),
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $created++;
        }
        fclose($handle);

        return back()->with('success', 'Bulk upload complete. Created '.$created.' entries.');
    }

    public function entriesTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['type', 'category', 'description', 'amount', 'entry_date', 'reference_number', 'notes']);
            fputcsv($out, ['expense', 'Warehouse', 'Monthly storage rental', '150000.00', now()->toDateString(), 'EXP-001', 'Sample expense row']);
            fputcsv($out, ['income', 'Service Fee', 'Platform setup fee', '75000.00', now()->toDateString(), 'INC-001', 'Sample income row']);
            fclose($out);
        }, 'superadmin-finance-entry-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportEntries(Request $request): StreamedResponse
    {
        $rows = $this->filteredEntries($request)->latest('entry_date')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['type', 'category', 'description', 'amount', 'entry_date', 'reference_number', 'notes']);
            foreach ($rows as $entry) {
                fputcsv($out, [$entry->type, $entry->category, $entry->description, $entry->amount, optional($entry->entry_date)->format('Y-m-d'), $entry->reference_number, $entry->notes]);
            }
            fclose($out);
        }, 'superadmin-expense-income-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function printEntries(Request $request)
    {
        return view('superadmin.finance.print-entries', [
            'entries' => $this->filteredEntries($request)->latest('entry_date')->get(),
            'summary' => $this->summary($request),
        ]);
    }

    public function profitLoss(Request $request)
    {
        return view('superadmin.finance.profit-loss', [
            'summary' => $this->summary($request),
            'chart' => $this->chartData($request),
            'entries' => $this->filteredEntries($request)->latest('entry_date')->limit(20)->get(),
        ]);
    }

    public function billingRates()
    {
        $this->ensureDefaultCharges();
        $charges = SuperadminBillingCharge::latest()->paginate($this->perPage(request(), 15));

        return view('superadmin.finance.billing-rates', compact('charges'));
    }

    public function bills(Request $request)
    {
        $billQuery = SuperadminBill::with(['tenant', 'payments'])
            ->where('source_type', '<>', 'shipped_package')
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->input('status') === 'unpaid', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($request->filled('status') && ! in_array($request->status, ['unpaid', 'void'], true), fn ($query) => $query->where('status', $request->status))
            ->when($request->input('status') === 'void', fn ($query) => $query->where('status', 'cancelled'))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(fn ($nested) => $nested
                    ->where('bill_number', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term)));
            });

        $invoiceQuery = SuperadminLogisticsInvoice::with(['tenant', 'manifest', 'items', 'payments'])
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->input('status') === 'issued', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($request->filled('status') && ! in_array($request->status, ['issued', 'cancelled'], true), fn ($query) => $query->where('status', $request->status))
            ->when($request->input('status') === 'cancelled', fn ($query) => $query->where('status', 'void'))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(fn ($nested) => $nested
                    ->where('invoice_number', 'like', $term)
                    ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))
                    ->orWhereHas('manifest', fn ($manifestQuery) => $manifestQuery
                        ->where('manifest_number', 'like', $term)
                        ->orWhere('warehouse_reference', 'like', $term)));
            });

        if ($request->input('billing_type') === 'manual') {
            $invoiceQuery->whereRaw('1 = 0');
        } elseif ($request->input('billing_type') === 'shipment') {
            $billQuery->whereRaw('1 = 0');
        }

        $records = $billQuery->get()
            ->toBase()
            ->map(fn (SuperadminBill $bill) => [
                'kind' => 'manual',
                'number' => $bill->bill_number,
                'title' => $bill->title,
                'tenant' => optional($bill->tenant)->company_name ?: '-',
                'category' => $bill->category,
                'source' => ucfirst(str_replace('_', ' ', $bill->source_type)),
                'issue_date' => $bill->issue_date,
                'due_date' => $bill->due_date,
                'sort_date' => $bill->issue_date ?: $bill->created_at,
                'status' => $bill->isLocked() ? 'Voided / Archived' : ucfirst(str_replace('_', ' ', $bill->status)),
                'status_class' => $bill->isLocked() ? 'secondary' : ($bill->status === 'paid' ? 'success' : ($bill->status === 'under_review' ? 'info' : ($bill->status === 'partial' ? 'warning text-dark' : 'secondary'))),
                'total' => (float) $bill->total_jmd,
                'paid' => (float) $bill->amount_paid_jmd,
                'balance' => (float) $bill->balance_jmd,
                'model' => $bill,
            ])
            ->merge($invoiceQuery->get()->toBase()->map(fn (SuperadminLogisticsInvoice $invoice) => [
                'kind' => 'shipment',
                'number' => $invoice->invoice_number,
                'title' => 'Shipment Invoice'.($invoice->manifest ? ' - '.$invoice->manifest->manifest_number : ''),
                'tenant' => optional($invoice->tenant)->company_name ?: '-',
                'category' => 'Shipment Invoice',
                'source' => optional($invoice->manifest)->warehouse_reference ?: optional($invoice->manifest)->manifest_number ?: 'Logistics',
                'issue_date' => $invoice->created_at,
                'due_date' => null,
                'sort_date' => $invoice->created_at,
                'status' => $invoice->status === 'void' ? 'Voided / Archived' : ucfirst(str_replace('_', ' ', $invoice->status)),
                'status_class' => $invoice->status === 'paid' ? 'success' : ($invoice->status === 'void' ? 'secondary' : ($invoice->status === 'partial' ? 'warning text-dark' : 'secondary')),
                'total' => (float) $invoice->total,
                'paid' => (float) $invoice->amount_paid_jmd,
                'balance' => (float) $invoice->balance_jmd,
                'model' => $invoice,
            ]))
            ->sortByDesc(fn ($record) => optional($record['sort_date'])->timestamp ?: 0)
            ->values();

        $perPage = $this->perPage($request, 15);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $bills = new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $tenants = Tenant::orderBy('company_name')->get();
        $summary = [
            'total' => (float) SuperadminBill::where('source_type', '<>', 'shipped_package')->sum('total_jmd') + (float) SuperadminLogisticsInvoice::sum('total'),
            'paid' => (float) SuperadminBill::where('source_type', '<>', 'shipped_package')->sum('amount_paid_jmd') + (float) SuperadminLogisticsInvoice::sum('amount_paid_jmd'),
            'outstanding' => (float) SuperadminBill::where('source_type', '<>', 'shipped_package')->sum('balance_jmd') + (float) SuperadminLogisticsInvoice::sum('balance_jmd'),
            'underReview' => SuperadminBill::where('source_type', '<>', 'shipped_package')->where('status', 'under_review')->count() + SuperadminLogisticsInvoice::where('status', 'under_review')->count(),
        ];

        return view('superadmin.finance.bills', compact('bills', 'tenants', 'summary'));
    }

    public function storeBill(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:120',
            'source_type' => 'required|in:system_service,package_usage,subscription,other',
            'description' => 'nullable|string',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'subtotal_jmd' => 'required|numeric|min:0',
            'tax_jmd' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $subtotal = (float) $data['subtotal_jmd'];
        $tax = (float) ($data['tax_jmd'] ?? 0);
        $data['tax_jmd'] = $tax;
        $bill = SuperadminBill::create($data + [
            'bill_number' => $this->nextBillNumber(),
            'currency' => 'JMD',
            'tax_jmd' => $tax,
            'total_jmd' => $subtotal + $tax,
            'amount_paid_jmd' => 0,
            'balance_jmd' => $subtotal + $tax,
            'status' => 'issued',
            'created_by' => auth()->id(),
        ]);
        $this->applyTenantCredit($bill);

        return back()->with('success', 'Bill issued to courier.');
    }

    public function generateCycleBill(Request $request)
    {
        return redirect()
            ->route('superadmin.finance.bills', ['billing_type' => 'shipment'])
            ->withErrors(['billing' => 'Warehouse/freight billing is now handled as Shipment Invoices in Courier Billing to prevent duplicate bills.']);
    }

    public function generatePackageUsageBill(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'per_package_base_fee_jmd' => 'nullable|numeric|min:0',
            'tax_jmd' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date|after_or_equal:period_end',
            'notes' => 'nullable|string',
        ]);

        $tenant = Tenant::with('subscription.plan')->findOrFail($data['tenant_id']);
        $billingModel = $tenant->billing_model ?: optional(optional($tenant->subscription)->plan)->billing_model ?: 'subscription';
        if (! in_array($billingModel, ['per_package', 'hybrid'], true)) {
            return back()
                ->withErrors(['tenant_id' => 'This courier is set to subscription billing. Change the courier billing model to Per Package or Subscription + Per Package first.'])
                ->withInput();
        }

        $cycleTitle = 'Per-package usage billing cycle: '.$data['period_start'].' to '.$data['period_end'];
        $existingBill = SuperadminBill::where('tenant_id', $tenant->id)
            ->whereIn('source_type', ['package_usage', 'shipped_package'])
            ->where('title', $cycleTitle)
            ->where(function ($query) {
                $query->whereNull('voided_at')->where('status', '!=', 'cancelled');
            })
            ->first();

        if ($existingBill) {
            return back()
                ->withErrors(['period_start' => 'A per-package usage bill already exists for this courier and billing cycle: '.$existingBill->bill_number.'. Void that bill before generating a replacement.'])
                ->withInput();
        }

        $packages = Package::query()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [
                $data['period_start'].' 00:00:00',
                $data['period_end'].' 23:59:59',
            ])
            ->orderBy('created_at')
            ->get();

        if ($packages->isEmpty()) {
            return back()
                ->withErrors(['period_start' => 'No packages were logged by this courier in the selected billing cycle.'])
                ->withInput();
        }

        $packageCount = $packages->count();
        $baseFee = (float) ($data['per_package_base_fee_jmd'] ?? 0);
        if ($baseFee <= 0) {
            $baseFee = (float) ($tenant->per_package_base_fee_jmd ?: optional(optional($tenant->subscription)->plan)->per_package_base_fee_jmd ?: 0);
        }
        $subtotal = round($packageCount * $baseFee, 2);
        $tax = (float) ($data['tax_jmd'] ?? 0);

        $bill = SuperadminBill::create([
            'tenant_id' => $tenant->id,
            'bill_number' => $this->nextBillNumber(),
            'title' => $cycleTitle,
            'category' => 'Package Usage',
            'source_type' => 'package_usage',
            'description' => $packageCount.' package'.($packageCount === 1 ? '' : 's').' billed for '.$data['period_start'].' to '.$data['period_end'].'.',
            'issue_date' => now()->toDateString(),
            'due_date' => $data['due_date'] ?? null,
            'currency' => 'JMD',
            'subtotal_jmd' => $subtotal,
            'tax_jmd' => $tax,
            'total_jmd' => $subtotal + $tax,
            'amount_paid_jmd' => 0,
            'balance_jmd' => $subtotal + $tax,
            'status' => 'issued',
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);
        $this->applyTenantCredit($bill);

        return back()->with('success', 'Per-package usage bill generated for '.$packageCount.' logged package(s).');
    }

    private function notifyCourierBillIssued(SuperadminBill $bill): void
    {
        app(CourierNotificationService::class)->notify(
            (int) $bill->tenant_id,
            null,
            'bill_issued',
            'Bill Issued',
            $bill->bill_number.' is available for review and payment.',
            route('courier.finance.bills'),
            ['bill_id' => $bill->id]
        );
    }
    public function showBill(SuperadminBill $bill)
    {
        $bill->load(['tenant', 'payments.submitter', 'payments.reviewer', 'creator', 'reviewer', 'sender', 'voider']);

        return view('superadmin.finance.bill-show', compact('bill'));
    }

    public function sendBill(SuperadminBill $bill, TenantMailService $mail)
    {
        $this->ensureBillIsNotLocked($bill);
        $bill->load('tenant');
        abort_unless($bill->tenant && $bill->tenant->email, 422, 'This courier does not have an email address.');

        $mail->send(null, $bill->tenant->email, new TenantTemplateMail('Courier bill '.$bill->bill_number, [
            'tenant' => $bill->tenant,
            'subject' => 'Courier bill '.$bill->bill_number,
            'headline' => 'Your courier bill is ready',
            'bodyHtml' => '<p>A bill has been issued for your courier account. Please review the balance and submit payment from your courier portal.</p>',
            'details' => [
                'Bill Number' => $bill->bill_number,
                'Title' => $bill->title,
                'Issue Date' => optional($bill->issue_date)->format('M d, Y'),
                'Due Date' => optional($bill->due_date)->format('M d, Y') ?: '-',
                'Total' => 'JMD '.number_format((float) $bill->total_jmd, 2),
                'Balance' => 'JMD '.number_format((float) $bill->balance_jmd, 2),
            ],
            'buttonLabel' => 'Open Courier Billing',
            'buttonUrl' => route('courier.finance.bills'),
            'attachmentUrl' => null,
            'footerText' => 'Please contact support if you have questions about this bill.',
            'footerHtml' => null,
        ]));

        $bill->update([
            'sent_at' => now(),
            'sent_by' => auth()->id(),
        ]);

        return back()->with('success', 'Bill sent to '.$bill->tenant->email.'.');
    }

    public function downloadBill(SuperadminBill $bill)
    {
        $bill->load(['tenant', 'payments']);

        return response()
            ->view('superadmin.finance.bill-download', compact('bill'))
            ->header('Content-Type', 'text/html');
    }

    public function updateBill(Request $request, SuperadminBill $bill)
    {
        $this->ensureBillIsNotLocked($bill);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:120',
            'source_type' => 'required|in:system_service,package_usage,subscription,other',
            'description' => 'nullable|string',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'subtotal_jmd' => 'required|numeric|min:0',
            'tax_jmd' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $subtotal = (float) $data['subtotal_jmd'];
        $tax = (float) ($data['tax_jmd'] ?? 0);
        $data['tax_jmd'] = $tax;
        $data['total_jmd'] = $subtotal + $tax;
        $bill->update($data);
        $this->syncBillStatus($bill->fresh());

        return back()->with('success', 'Bill updated.');
    }

    public function voidBill(Request $request, SuperadminBill $bill)
    {
        if ($bill->isLocked()) {
            return back()->withErrors(['bill' => 'This bill is already voided, permanently locked, and archived.']);
        }

        $data = $request->validate([
            'void_reason' => 'nullable|string|max:1000',
        ]);

        $bill->update([
            'status' => 'cancelled',
            'balance_jmd' => 0,
            'voided_by' => auth()->id(),
            'voided_at' => now(),
            'void_reason' => $data['void_reason'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        ActivityLog::record('voided', 'superadmin_bills', 'Voided bill '.$bill->bill_number, [
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'void_reason' => $bill->void_reason,
        ]);

        return redirect()->route('superadmin.finance.bills.show', $bill)
            ->with('success', 'Bill voided, archived, and permanently locked.');
    }

    public function reviewBillPayment(Request $request, SuperadminBillPayment $payment)
    {
        $data = $request->validate([
            'status' => 'required|in:under_review,approved,partial,rejected',
            'approved_amount_jmd' => 'nullable|numeric|min:0',
            'admin_notes' => 'nullable|string',
        ]);

        $payment->load('bill');
        $this->ensureBillIsNotLocked($payment->bill);
        $approvedAmount = in_array($data['status'], ['approved', 'partial'], true)
            ? (float) ($data['approved_amount_jmd'] ?? $payment->amount_jmd)
            : 0.0;
        $approvedAmount = min($approvedAmount, (float) $payment->amount_jmd);

        $payment->update([
            'amount_jmd' => $approvedAmount > 0 ? $approvedAmount : $payment->amount_jmd,
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $bill = $this->syncBillStatus($payment->bill->fresh());

        if (in_array($data['status'], ['approved', 'partial'], true) && $approvedAmount > 0) {
            $this->recordCourierExpense($bill, $payment);
            $this->recordSystemRevenue($bill, $payment, $approvedAmount);
        }

        return back()->with('success', 'Bill payment reviewed. Outstanding balance: JMD '.number_format((float) $bill->balance_jmd, 2));
    }

    public function downloadBillReceipt(SuperadminBillPayment $payment)
    {
        abort_if(! $payment->receipt_path || ! Storage::disk('public')->exists($payment->receipt_path), 404);

        return Storage::disk('public')->download($payment->receipt_path, $payment->receipt_original_name);
    }

    public function storeBillingRate(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'amount_usd' => 'nullable|numeric|min:0',
            'amount_jmd' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $baseCode = Str::slug($data['name']);
        $code = $baseCode;
        $counter = 2;
        while (SuperadminBillingCharge::where('code', $code)->exists()) {
            $code = $baseCode.'-'.$counter++;
        }

        SuperadminBillingCharge::create($data + [
            'code' => $code,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Billing charge saved.');
    }

    public function updateBillingRate(Request $request, SuperadminBillingCharge $charge)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'amount_usd' => 'nullable|numeric|min:0',
            'amount_jmd' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $charge->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Billing charge updated.');
    }

    public function destroyBillingRate(SuperadminBillingCharge $charge)
    {
        $charge->delete();

        return back()->with('success', 'Billing charge deleted.');
    }

    private function filteredEntries(Request $request)
    {
        return SuperadminFinanceEntry::query()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->type))
            ->when($request->filled('category'), fn ($query) => $query->where('category', 'like', '%'.$request->category.'%'))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('entry_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('entry_date', '<=', $request->date_to));
    }

    private function ledgerCategories(): array
    {
        return LedgerCategory::where('portal', 'superadmin')
            ->where('scope_key', 'superadmin')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->pluck('name')->values()->all())
            ->union(['income' => [], 'expense' => []])
            ->all();
    }

    private function ledgerCategoryFromRequest(Request $request, array $data): string
    {
        $newCategory = trim((string) ($data['new_category'] ?? ''));
        if ($newCategory !== '') {
            LedgerCategory::firstOrCreate([
                'portal' => 'superadmin',
                'scope_key' => 'superadmin',
                'type' => $data['type'],
                'name' => $newCategory,
            ], [
                'tenant_id' => null,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            return $newCategory;
        }

        if (blank($data['category'] ?? null)) {
            throw ValidationException::withMessages(['category' => 'Select an existing category or enter a new one.']);
        }

        return $data['category'];
    }

    private function ensureDefaultLedgerCategories(): void
    {
        $defaults = [
            'income' => ['Subscription Revenue', 'SaaS Revenue - Service Charges', 'Setup Fee', 'Onboarding Fee', 'Courier Billing', 'Other Income'],
            'expense' => ['Hosting', 'Software', 'Payroll', 'Marketing', 'Bank Fees', 'Office Expense', 'Warehouse', 'Logistics', 'Professional Services', 'Other Expense'],
        ];

        foreach ($defaults as $type => $categories) {
            foreach ($categories as $name) {
                LedgerCategory::firstOrCreate([
                    'portal' => 'superadmin',
                    'scope_key' => 'superadmin',
                    'type' => $type,
                    'name' => $name,
                ], [
                    'tenant_id' => null,
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]);
            }
        }
    }

    private function summary(Request $request): array
    {
        $entries = $this->filteredEntries($request);
        $manualIncome = (float) (clone $entries)
            ->where('type', 'income')
            ->where('category', '<>', 'SaaS Revenue - Service Charges')
            ->sum('amount');
        $subscriptionIncome = (float) TenantPayment::where('status', 'successful')
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('paid_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('paid_at', '<=', $request->date_to))
            ->sum('amount');
        $expenses = (float) (clone $entries)->where('type', 'expense')->sum('amount');
        $income = $manualIncome + $subscriptionIncome;

        return compact('income', 'manualIncome', 'subscriptionIncome', 'expenses') + ['profitLoss' => $income - $expenses];
    }

    private function chartData(Request $request): array
    {
        $months = collect(range(5, 0))->map(fn ($offset) => now()->copy()->subMonths($offset));
        return [
            'labels' => $months->map(fn ($month) => $month->format('M'))->all(),
            'income' => $months->map(fn ($month) => (float) SuperadminFinanceEntry::where('type', 'income')->where('category', '<>', 'SaaS Revenue - Service Charges')->whereMonth('entry_date', $month->month)->whereYear('entry_date', $month->year)->sum('amount') + (float) TenantPayment::where('status', 'successful')->whereMonth('paid_at', $month->month)->whereYear('paid_at', $month->year)->sum('amount'))->all(),
            'expenses' => $months->map(fn ($month) => (float) SuperadminFinanceEntry::where('type', 'expense')->whereMonth('entry_date', $month->month)->whereYear('entry_date', $month->year)->sum('amount'))->all(),
        ];
    }

    private function ensureDefaultCharges(): void
    {
        foreach ([
            ['Hosting Service Fee', 'hosting_service_fee'],
            ['Website Development Fee', 'website_development_fee'],
            ['Onboarding Fee', 'onboarding_fee'],
            ['Per Package Service Fee', 'per_package_service_fee'],
        ] as [$name, $code]) {
            SuperadminBillingCharge::firstOrCreate(['code' => $code], ['name' => $name, 'is_default' => true, 'is_active' => true]);
        }
    }

    private function nextBillNumber(): string
    {
        $prefix = 'SAB-'.now()->format('Ym').'-';
        $last = SuperadminBill::where('bill_number', 'like', $prefix.'%')->orderByDesc('id')->value('bill_number');
        $next = $last && preg_match('/(\d+)$/', $last, $matches) ? ((int) $matches[1]) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function syncBillStatus(SuperadminBill $bill): SuperadminBill
    {
        if ($bill->isLocked()) {
            return $bill->fresh();
        }

        $approved = (float) $bill->payments()->whereIn('status', ['approved', 'partial'])->sum('amount_jmd');
        $balance = max(0, (float) $bill->total_jmd - $approved);
        $hasReview = $bill->payments()->whereIn('status', ['submitted', 'under_review'])->exists();
        $status = $balance <= 0.01 ? 'paid' : ($approved > 0 ? 'partial' : $bill->status);
        if ($hasReview && $balance > 0.01) {
            $status = 'under_review';
        }
        if (! $hasReview && $approved <= 0 && ! in_array($status, ['cancelled'], true)) {
            $status = 'issued';
        }

        $bill->update([
            'amount_paid_jmd' => $approved,
            'balance_jmd' => $balance,
            'status' => $status,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return $bill->fresh();
    }

    private function applyTenantCredit(SuperadminBill $bill): void
    {
        $bill->loadMissing('tenant');
        if (! $bill->tenant || (float) $bill->tenant->credit_balance_jmd <= 0 || (float) $bill->balance_jmd <= 0) {
            return;
        }

        $credit = min((float) $bill->tenant->credit_balance_jmd, (float) $bill->balance_jmd);
        $newTenantBalance = round((float) $bill->tenant->credit_balance_jmd - $credit, 2);
        $newBillPaid = round((float) $bill->amount_paid_jmd + $credit, 2);
        $newBillBalance = round((float) $bill->balance_jmd - $credit, 2);

        $bill->tenant->update(['credit_balance_jmd' => $newTenantBalance]);
        $bill->update([
            'amount_paid_jmd' => $newBillPaid,
            'balance_jmd' => $newBillBalance,
            'status' => $newBillBalance <= 0.01 ? 'paid' : 'partial',
        ]);

        TenantCreditTransaction::create([
            'tenant_id' => $bill->tenant_id,
            'type' => 'applied',
            'amount_jmd' => -$credit,
            'balance_after_jmd' => $newTenantBalance,
            'reference_type' => SuperadminBill::class,
            'reference_id' => $bill->id,
            'reference_number' => $bill->bill_number,
            'notes' => 'Automatically applied to courier bill at invoice close.',
            'created_by' => auth()->id(),
        ]);
    }

    private function ensureBillIsNotLocked(SuperadminBill $bill): void
    {
        if ($bill->isLocked()) {
            throw ValidationException::withMessages([
                'bill' => 'This bill is voided, permanently locked, and archived. It cannot be reopened or modified.',
            ]);
        }
    }

    private function recordCourierExpense(SuperadminBill $bill, SuperadminBillPayment $payment): void
    {
        app(\App\Services\FinanceEntryService::class)->recordSystemAdminBillExpense($bill, $payment);
    }

    private function recordSystemRevenue(SuperadminBill $bill, SuperadminBillPayment $payment, float $approvedAmount): void
    {
        $bill->loadMissing('tenant.subscription');
        $reference = $bill->bill_number.'-'.$payment->id;
        $method = in_array($payment->payment_method, ['card', 'debit_card', 'bank_transfer', 'cash', 'paypal', 'stripe'], true)
            ? $payment->payment_method
            : 'bank_transfer';

        if ($bill->tenant && $bill->tenant->subscription) {
            TenantPayment::firstOrCreate([
                'transaction_id' => $reference,
            ], [
                'tenant_id' => $bill->tenant_id,
                'subscription_id' => $bill->tenant->subscription->id,
                'amount' => $approvedAmount,
                'currency' => 'JMD',
                'payment_method' => $method,
                'status' => 'successful',
                'receipt_url' => $payment->receipt_path ? route('superadmin.finance.bill-payments.receipt', $payment) : null,
                'paid_at' => $payment->reviewed_at ?: now(),
            ]);
        }

        SuperadminFinanceEntry::updateOrCreate([
            'reference_number' => $reference,
        ], [
            'type' => 'income',
            'category' => 'SaaS Revenue - Service Charges',
            'description' => 'Courier bill payment: '.$bill->title,
            'amount' => $approvedAmount,
            'entry_date' => optional($payment->reviewed_at)->toDateString() ?: now()->toDateString(),
            'notes' => trim(
                'Courier: '.optional($bill->tenant)->company_name."\n".
                'Bill: '.$bill->bill_number."\n".
                'Category: '.$bill->category."\n".
                ($payment->admin_notes ?: '')
            ),
            'created_by' => auth()->id(),
        ]);
    }
}
