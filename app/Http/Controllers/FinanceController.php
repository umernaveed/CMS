<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\FinanceEntry;
use App\Models\Invoice;
use App\Models\LedgerCategory;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\Package;
use App\Models\PackageLog;
use App\Models\PackageStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ShippingMode;
use App\Models\SuperadminBill;
use App\Models\SuperadminBillPayment;
use App\Models\SuperadminLogisticsInvoice;
use App\Models\SystemSetting;
use App\Support\LogisticsInvoicePdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    private array $stringPaymentMethods = ['online', 'bank_transfer', 'cash', 'cheque', 'debit_card', 'other'];

    public function transactions(Request $request)
    {
        $transactions = $this->filteredTransactions($request)->with(['customer.user', 'items'])->latest('paid_at')->paginate($this->perPage($request, 15))->withQueryString();
        return view('courier.finance.transactions', compact('transactions'));
    }

    public function payments(Request $request)
    {
        $payments = $this->filteredPayments($request)->with(['customer.user', 'invoice', 'method', 'approver'])->latest('paid_at')->paginate($this->perPage($request, 15))->withQueryString();
        $methods = PaymentMethod::where('is_active', true)->orderBy('name')->get();
        return view('courier.finance.payments', compact('payments', 'methods'));
    }

    public function downloadPaymentReceipt(Payment $payment)
    {
        $this->authorizePayment($payment);
        return $this->publicFile($payment->receipt_path, $payment->receipt_original_name);
    }

    public function approvePayment(Request $request, Payment $payment)
    {
        $this->authorizePayment($payment);
        $data = $request->validate(['receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:4096', 'notes' => 'nullable|string|max:1000']);
        $update = ['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'notes' => $data['notes'] ?? $payment->notes];
        if ($request->hasFile('receipt')) {
            $file = $request->file('receipt');
            $update += ['receipt_path' => $file->store('payment-receipts', 'public'), 'receipt_original_name' => $file->getClientOriginalName(), 'receipt_mime_type' => $file->getClientMimeType()];
        }
        $payment->update($update);
        if ($payment->invoice) {
            $invoice = $payment->invoice->fresh('payments');
            $paid = (float) $invoice->payments->where('status', 'approved')->sum('amount');
            $invoice->update(['payment_status' => $paid + 0.01 >= (float) $invoice->total_jmd ? 'paid' : 'partial', 'paid_at' => now(), 'paid_by' => auth()->id()]);
            if ($invoice->payment_status === 'paid') {
                app(\App\Services\FinanceEntryService::class)->recordCustomerInvoiceIncome($invoice->fresh('customer.user'));
                app(\App\Services\LoyaltyService::class)->awardInvoicePaid($invoice->fresh('customer'));
            }
        }
        ActivityLog::record('approved', 'payments', 'Approved payment '.$payment->payment_number, ['payment_id' => $payment->id]);
        return back()->with('success', 'Payment approved successfully.');
    }

    public function uploadPaymentReceipt(Request $request, Payment $payment)
    {
        $this->authorizePayment($payment);
        $data = $request->validate(['receipt' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:4096']);
        $file = $data['receipt'];
        $payment->update(['receipt_path' => $file->store('payment-receipts', 'public'), 'receipt_original_name' => $file->getClientOriginalName(), 'receipt_mime_type' => $file->getClientMimeType()]);
        return back()->with('success', 'Proof of payment uploaded.');
    }

    public function entries(Request $request)
    {
        $entries = $this->filteredEntries($request)->latest('entry_date')->paginate($this->perPage($request, 15))->withQueryString();
        $summary = $this->entriesSummary($request);
        $this->ensureCourierLedgerCategories();
        $categories = $this->ledgerCategories();
        return view('courier.finance.entries', compact('entries', 'summary', 'categories'));
    }

    public function storeEntry(Request $request)
    {
        $data = $this->entryData($request);
        FinanceEntry::create($data + ['tenant_id' => auth()->user()->tenant_id, 'outlet_id' => auth()->user()->outlet_id, 'created_by' => auth()->id()]);
        return back()->with('success', 'Finance entry saved.');
    }

    public function updateEntry(Request $request, FinanceEntry $entry)
    {
        $this->authorizeEntry($entry);
        $entry->update($this->entryData($request));
        return back()->with('success', 'Finance entry updated.');
    }

    public function destroyEntry(FinanceEntry $entry)
    {
        $this->authorizeEntry($entry);
        $entry->delete();
        return back()->with('success', 'Finance entry deleted.');
    }

    public function categories()
    {
        $this->ensureCourierLedgerCategories();
        $categories = $this->ledgerCategoryQuery()->paginate($this->perPage(request(), 20));
        return view('courier.finance.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['type' => 'required|in:income,expense', 'name' => 'required|string|max:120', 'description' => 'nullable|string|max:255', 'is_active' => 'nullable|boolean']);
        LedgerCategory::create($data + ['portal' => 'courier', 'scope_key' => 'tenant:'.auth()->user()->tenant_id, 'tenant_id' => auth()->user()->tenant_id, 'is_active' => $request->boolean('is_active', true), 'created_by' => auth()->id()]);
        return back()->with('success', 'Ledger category created.');
    }

    public function updateCategory(Request $request, LedgerCategory $category)
    {
        $data = $request->validate(['type' => 'required|in:income,expense', 'name' => 'required|string|max:120', 'description' => 'nullable|string|max:255', 'is_active' => 'nullable|boolean']);
        $category->update($data + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Ledger category updated.');
    }

    public function destroyCategory(LedgerCategory $category)
    {
        $category->delete();
        return back()->with('success', 'Ledger category deleted.');
    }

    public function profitLoss(Request $request)
    {
        return view('courier.finance.profit-loss', $this->profitLossData($request));
    }

    public function reports(Request $request)
    {
        $data = $this->profitLossData($request);
        [$dateFrom, $dateTo] = [$data['dateFrom'], $data['dateTo']];

        return view('courier.finance.reports', $data + [
            'invoiceCount' => $this->tenantInvoices()
                ->whereBetween('created_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
                ->count(),
            'paidInvoiceCount' => $this->tenantInvoices()
                ->where('payment_status', 'paid')
                ->whereBetween('paid_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
                ->count(),
            'openInvoices' => $this->tenantInvoices()
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->sum('total_jmd'),
        ]);
    }

    public function balanceSheet(Request $request)
    {
        return view('courier.finance.balance-sheet', $this->balanceSheetData($request));
    }

    public function bills(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $perPage = $this->perPage($request, 15);
        $status = $request->filled('status') ? (string) $request->input('status') : null;
        $search = trim((string) $request->input('q', ''));
        $billQuery = SuperadminBill::where('tenant_id', $tenantId)
            ->where('source_type', '<>', 'shipped_package');
        $logisticsQuery = SuperadminLogisticsInvoice::where('tenant_id', $tenantId);

        if ($status) {
            $billQuery->where('status', $status);
            $logisticsQuery->where('status', $status);
        }

        if ($search !== '') {
            $billQuery->where(function ($query) use ($search) {
                $query->where('bill_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('source_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
            $logisticsQuery->where(function ($query) use ($search) {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('billing_scope', 'like', "%{$search}%")
                    ->orWhereHas('manifest', function ($manifestQuery) use ($search) {
                        $manifestQuery->where('manifest_number', 'like', "%{$search}%")
                            ->orWhere('warehouse_reference', 'like', "%{$search}%")
                            ->orWhereHas('shipment', fn ($shipmentQuery) => $shipmentQuery->where('master_number', 'like', "%{$search}%"));
                    });
            });
        }

        $bills = (clone $billQuery)->with('payments')->get();
        $logisticsInvoices = (clone $logisticsQuery)->with(['manifest.shipment', 'items.package.courierPackage.manifest', 'payments'])->get();
        $records = $bills->map(fn ($bill) => [
            'type' => 'bill',
            'sort_date' => optional($bill->issue_date)->timestamp ?: optional($bill->created_at)->timestamp ?: 0,
            'record' => $bill,
        ])->concat($logisticsInvoices->map(fn ($invoice) => [
            'type' => 'logistics',
            'sort_date' => optional($invoice->created_at)->timestamp ?: 0,
            'record' => $invoice,
        ]))->sortByDesc('sort_date')->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $billingRecords = new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $summaryBillQuery = SuperadminBill::where('tenant_id', $tenantId)
            ->where('source_type', '<>', 'shipped_package');
        $summaryLogisticsQuery = SuperadminLogisticsInvoice::where('tenant_id', $tenantId);
        $summary = [
            'total' => (float) (clone $summaryBillQuery)->sum('total_jmd') + (float) (clone $summaryLogisticsQuery)->sum('total'),
            'paid' => (float) (clone $summaryBillQuery)->sum('amount_paid_jmd') + (float) (clone $summaryLogisticsQuery)->sum('amount_paid_jmd'),
            'outstanding' => (float) (clone $summaryBillQuery)->sum('balance_jmd') + (float) (clone $summaryLogisticsQuery)->sum('balance_jmd'),
            'underReview' => (clone $summaryBillQuery)->where('status', 'under_review')->count() + (clone $summaryLogisticsQuery)->where('status', 'under_review')->count(),
            'creditBalance' => (float) optional(auth()->user()->tenant)->credit_balance_jmd,
        ];
        return view('courier.finance.bills', compact('billingRecords', 'summary'));
    }

    public function showBill(SuperadminBill $bill)
    {
        $this->authorizeBill($bill);
        $bill->load(['tenant', 'payments.submitter', 'payments.reviewer']);
        return view('courier.finance.bill-show', compact('bill'));
    }

    public function wallet(Request $request) { $tenant = auth()->user()->tenant; $transactions = $tenant->creditTransactions()->latest()->paginate($this->perPage($request, 20)); return view('courier.finance.wallet', compact('tenant', 'transactions')); }
    public function printWallet(Request $request) { $tenant = auth()->user()->tenant; $transactions = $tenant->creditTransactions()->latest()->get(); return view('courier.finance.wallet-print', compact('tenant', 'transactions')); }
    public function downloadWallet(Request $request) { return $this->csv('courier-wallet', ['date','type','reference','amount_jmd'], auth()->user()->tenant->creditTransactions()->latest()->get()->map(fn ($t) => [optional($t->created_at)->format('Y-m-d H:i:s'), $t->type, $t->reference_number, $t->amount_jmd])); }

    public function uploadBillPayment(Request $request, SuperadminBill $bill)
    {
        $this->authorizeBill($bill);
        $data = $request->validate(['amount_jmd' => 'required|numeric|min:0.01', 'payment_method' => 'required|in:'.implode(',', $this->stringPaymentMethods), 'reference_number' => 'nullable|string|max:150', 'receipt' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:4096', 'notes' => 'nullable|string|max:1000']);
        $file = $data['receipt'];
        SuperadminBillPayment::create(['superadmin_bill_id' => $bill->id, 'tenant_id' => $bill->tenant_id, 'amount_jmd' => min((float) $data['amount_jmd'], (float) $bill->balance_jmd), 'payment_method' => $data['payment_method'], 'reference_number' => $data['reference_number'] ?? null, 'receipt_path' => $file->store('superadmin-bill-receipts', 'public'), 'receipt_original_name' => $file->getClientOriginalName(), 'receipt_mime_type' => $file->getClientMimeType(), 'status' => 'submitted', 'notes' => $data['notes'] ?? null, 'submitted_by' => auth()->id(), 'submitted_at' => now()]);
        $bill->update(['status' => 'under_review']);
        return back()->with('success', 'Payment proof uploaded. System Admin will review and confirm the bill balance.');
    }

    public function uploadLogisticsInvoiceReceipt(Request $request, SuperadminLogisticsInvoice $invoice)
    {
        abort_if((int) $invoice->tenant_id !== (int) auth()->user()->tenant_id, 404);
        $data = $request->validate(['receipt' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:4096']);
        $file = $data['receipt'];
        $invoice->update(['receipt_path' => $file->store('logistics-invoice-receipts', 'public'), 'receipt_original_name' => $file->getClientOriginalName(), 'status' => 'under_review']);
        return back()->with('success', 'Payment proof uploaded for logistics invoice.');
    }

    public function uploadLogisticsSupplierInvoice(Request $request, SuperadminLogisticsInvoice $invoice)
    {
        abort_if((int) $invoice->tenant_id !== (int) auth()->user()->tenant_id, 404);
        $data = $request->validate(['supplier_invoice' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:4096']);
        $file = $data['supplier_invoice'];
        $invoice->update(['supplier_invoice_path' => $file->store('logistics-supplier-invoices', 'public'), 'supplier_invoice_original_name' => $file->getClientOriginalName()]);
        return back()->with('success', 'Supplier invoice uploaded.');
    }

    public function downloadBillReceipt(SuperadminBillPayment $payment) { abort_if((int) $payment->tenant_id !== (int) auth()->user()->tenant_id, 404); return $this->publicFile($payment->receipt_path, $payment->receipt_original_name); }
    public function viewLogisticsSupplierInvoice(SuperadminLogisticsInvoice $invoice) { abort_if((int) $invoice->tenant_id !== (int) auth()->user()->tenant_id, 404); return $this->publicFile($invoice->supplier_invoice_path, $invoice->supplier_invoice_original_name); }
    public function downloadLogisticsInvoice(SuperadminLogisticsInvoice $invoice)
    {
        $this->authorizeLogisticsInvoice($invoice);
        $invoice->load(['tenant', 'manifest', 'items.package.courierPackage', 'payments']);
        $platformSettings = SystemSetting::pluck('value', 'key');

        return response()
            ->view('superadmin.logistics.invoice-download', compact('invoice', 'platformSettings'))
            ->header('Content-Type', 'text/html');
    }

    public function downloadThermalLogisticsInvoice(SuperadminLogisticsInvoice $invoice)
    {
        $this->authorizeLogisticsInvoice($invoice);
        $invoice->load(['tenant', 'manifest', 'items.package.courierPackage', 'payments']);

        return LogisticsInvoicePdf::inline($invoice, SystemSetting::pluck('value', 'key'));
    }

    public function downloadThermalLogisticsInvoiceFile(SuperadminLogisticsInvoice $invoice)
    {
        $this->authorizeLogisticsInvoice($invoice);
        $invoice->load(['tenant', 'manifest', 'items.package.courierPackage', 'payments']);

        return LogisticsInvoicePdf::download($invoice, SystemSetting::pluck('value', 'key'));
    }
    public function generateManifestFromLogisticsInvoice(SuperadminLogisticsInvoice $invoice)
    {
        $this->authorizeLogisticsInvoice($invoice);
        $invoice->load(['tenant', 'manifest.shipment', 'items.package.courierPackage.manifest']);

        if ($existingManifest = $this->generatedManifestForLogisticsInvoice($invoice)) {
            return redirect()->route('courier.manifests.show', $existingManifest)->with('success', 'Manifest already generated for this shipment invoice.');
        }

        abort_if($invoice->items->isEmpty(), 422, 'This shipment invoice has no packages to place on a manifest.');

        $manifest = DB::transaction(function () use ($invoice) {
            $sourceManifest = $invoice->manifest;
            $sourceShipment = $sourceManifest?->shipment;
            $tenantId = (int) $invoice->tenant_id;
            $status = $this->receivedAtPortStatusForTenant($tenantId);

            $manifest = Manifest::create([
                'tenant_id' => $tenantId,
                'manifest_number' => $this->nextGeneratedManifestNumber($tenantId),
                'cargo_company' => $sourceShipment?->cargo_company ?: $sourceManifest?->cargo_company,
                'date_of_departure' => optional($sourceShipment?->departure_date)->toDateString(),
                'shipping_agent_code' => $sourceShipment?->shipping_agent_code,
                'exporter_name' => $sourceShipment?->exporter_name,
                'exporter_address' => $sourceShipment?->exporter_address,
                'master_air_bill' => $sourceShipment?->master_number ?: $sourceManifest?->warehouse_reference,
                'voyage_number' => $sourceShipment?->voyage_container_numbers,
                'status' => 'draft',
            ]);

            $attached = 0;
            foreach ($invoice->items as $item) {
                $logisticsPackage = $item->package;
                if (! $logisticsPackage) {
                    continue;
                }

                $courierPackage = $logisticsPackage->courierPackage;
                if (! $courierPackage) {
                    $courierPackage = Package::create([
                        'tenant_id' => $tenantId,
                        'tracking_number' => $this->nextCourierPackageNumber($tenantId),
                        'supplier_tracking' => $logisticsPackage->tracking_number ?: $item->tracking_number,
                        'courier' => $sourceShipment?->cargo_company ?: $sourceManifest?->cargo_company,
                        'merchant' => 'Warehouse',
                        'description' => $logisticsPackage->description ?: 'Shipment invoice package',
                        'weight' => $this->logisticsWeightInPounds((float) ($logisticsPackage->weight ?: $item->weight), $logisticsPackage->weight_unit ?: $item->weight_unit),
                        'freight_type' => $this->tenantDefaultFreightType($tenantId),
                        'delivery_type' => 'pickup',
                        'status_id' => $status?->id,
                        'manifest_id' => $manifest->id,
                        'is_in_manifest' => true,
                        'storage_days' => 0,
                        'storage_fee' => 0,
                        'processing_fee_jmd' => 0,
                        'date_received' => optional($logisticsPackage->checked_in_at)->toDateString() ?: now()->toDateString(),
                        'notes' => 'Generated from shipment invoice '.$invoice->invoice_number.'.',
                    ]);
                } else {
                    $courierPackage->update([
                        'manifest_id' => $manifest->id,
                        'is_in_manifest' => true,
                        'status_id' => $courierPackage->status_id ?: $status?->id,
                    ]);
                }

                $logisticsPackage->update(['courier_package_id' => $courierPackage->id]);
                ManifestItem::firstOrCreate([
                    'tenant_id' => $tenantId,
                    'manifest_id' => $manifest->id,
                    'package_id' => $courierPackage->id,
                ]);
                PackageLog::create([
                    'tenant_id' => $tenantId,
                    'package_id' => $courierPackage->id,
                    'status_id' => $courierPackage->status_id,
                    'note' => 'Added to courier manifest from shipment invoice '.$invoice->invoice_number,
                    'location' => 'Warehouse / Port',
                    'added_by' => auth()->id(),
                    'created_at' => now(),
                ]);
                $attached++;
            }

            abort_if($attached === 0, 422, 'No valid packages were found on this shipment invoice.');
            ActivityLog::record('created', 'manifests', 'Generated manifest '.$manifest->manifest_number.' from shipment invoice '.$invoice->invoice_number, [
                'manifest_id' => $manifest->id,
                'logistics_invoice_id' => $invoice->id,
                'packages' => $attached,
            ]);

            return $manifest;
        });

        return redirect()->route('courier.manifests.show', $manifest)->with('success', 'Manifest generated successfully.');
    }

    public function export(Request $request, string $section) { return $this->csv($section, ['section','generated_at'], collect([[$section, now()->toDateTimeString()]])); }
    public function print(Request $request, string $section)
    {
        return match ($section) {
            'profit-loss', 'reports' => view('courier.finance.print.summary', $this->profitLossData($request) + ['section' => $section]),
            'balance-sheet' => view('courier.finance.print.balance-sheet', $this->balanceSheetData($request)),
            'entries' => view('courier.finance.print.entries', [
                'entries' => $this->filteredEntries($request)->latest('entry_date')->get(),
                'summary' => $this->entriesSummary($request),
            ]),
            'payments' => view('courier.finance.print.payments', [
                'payments' => $this->filteredPayments($request)->with(['customer.user', 'invoice', 'method', 'approver'])->latest('paid_at')->get(),
            ]),
            'transactions' => view('courier.finance.print.transactions', [
                'transactions' => $this->filteredTransactions($request)->with(['customer.user', 'items'])->latest('paid_at')->get(),
            ]),
            default => abort(404),
        };
    }

    private function ensureCourierLedgerCategories(): void
    {
        $tenantId = auth()->user()->tenant_id;
        $scope = 'tenant:'.$tenantId;
        $defaults = [
            'income' => ['Package Revenue', 'Delivery Revenue', 'Storage Fees', 'Service Fees', 'Other Income'],
            'expense' => ['Payroll', 'Rent', 'Utilities', 'Fuel', 'Vehicle Maintenance', 'Warehouse', 'Supplies', 'Bank Fees', 'Other Expense'],
        ];

        foreach ($defaults as $type => $names) {
            foreach ($names as $name) {
                LedgerCategory::firstOrCreate(
                    ['portal' => 'courier', 'scope_key' => $scope, 'type' => $type, 'name' => $name],
                    ['tenant_id' => $tenantId, 'is_active' => true]
                );
            }
        }
    }

    private function ledgerCategoryQuery()
    {
        return LedgerCategory::where('portal', 'courier')
            ->where('scope_key', 'tenant:'.auth()->user()->tenant_id)
            ->orderBy('type')
            ->orderBy('name');
    }

    private function ledgerCategories()
    {
        return $this->ledgerCategoryQuery()
            ->where('is_active', true)
            ->get()
            ->groupBy('type')
            ->map(fn ($items) => $items->pluck('name')->values())
            ->union(['income' => collect(), 'expense' => collect()]);
    }
    private function tenantInvoices() { return Invoice::where('tenant_id', auth()->user()->tenant_id); }
    private function tenantPayments() { return Payment::where('tenant_id', auth()->user()->tenant_id); }
    private function filteredPayments(Request $request)
    {
        return $this->tenantPayments()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('payment_method_id'), fn ($q) => $q->where('payment_method_id', $request->integer('payment_method_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->date('date_to')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim((string) $request->input('q')).'%';
                $q->where(function ($nested) use ($term) {
                    $nested->where('payment_number', 'like', $term)
                        ->orWhere('reference_number', 'like', $term)
                        ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', $term))
                        ->orWhereHas('customer.user', fn ($user) => $user->where('full_name', 'like', $term)->orWhere('email', 'like', $term));
                });
            });
    }

    private function filteredTransactions(Request $request)
    {
        return $this->tenantInvoices()
            ->where('payment_status', 'paid')
            ->when($request->filled('verified'), function ($q) use ($request) {
                $request->input('verified') === 'yes' ? $q->whereNotNull('verified_at') : $q->whereNull('verified_at');
            })
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->date('date_to')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim((string) $request->input('q')).'%';
                $q->where(function ($nested) use ($term) {
                    $nested->where('invoice_number', 'like', $term)
                        ->orWhere('mailbox', 'like', $term)
                        ->orWhereHas('customer.user', fn ($user) => $user->where('full_name', 'like', $term)->orWhere('email', 'like', $term));
                });
            });
    }

    private function filteredEntries(Request $request)
    {
        return FinanceEntry::where('tenant_id', auth()->user()->tenant_id)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('category'), fn ($q) => $q->where('category', 'like', '%'.trim((string) $request->input('category')).'%'))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->date('date_to')));
    }
    private function entryData(Request $request): array
    {
        $this->ensureCourierLedgerCategories();
        $tenantId = auth()->user()->tenant_id;

        return $request->validate([
            'type' => 'required|in:income,expense',
            'category' => ['required', 'string', 'max:120', Rule::exists('ledger_categories', 'name')->where(fn ($query) => $query->where('portal', 'courier')->where('scope_key', 'tenant:'.$tenantId)->where('type', $request->type)->where('is_active', true))],
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'entry_date' => 'required|date',
            'reference_number' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);
    }
    private function profitLossData(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->financeDateRange($request);
        $entries = FinanceEntry::where('tenant_id', auth()->user()->tenant_id)
            ->whereBetween('entry_date', [$dateFrom, $dateTo]);
        $manualIncomeQuery = (clone $entries)->where('type', 'income');
        $this->excludeInvoiceIncomeEntries($manualIncomeQuery);
        $manualIncome = (float) $manualIncomeQuery->sum('amount');
        $expenses = (float) (clone $entries)->where('type', 'expense')->sum('amount');
        $invoiceIncome = (float) $this->tenantInvoices()
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
            ->sum('total_jmd');
        $income = $invoiceIncome + $manualIncome;
        $profitLoss = $income - $expenses;

        return [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'invoiceIncome' => $invoiceIncome,
            'manualIncome' => $manualIncome,
            'income' => $income,
            'expenses' => $expenses,
            'profit' => $profitLoss,
            'profitLoss' => $profitLoss,
        ];
    }

    private function excludeInvoiceIncomeEntries($query): void
    {
        // Paid customer invoices are counted separately as invoice income in Profit / Loss.
        $query->where('category', '<>', 'Customer Invoice');
    }

    private function financeDateRange(Request $request): array
    {
        $dateFrom = $request->filled('date_from') ? $request->date('date_from')->toDateString() : now()->startOfMonth()->toDateString();
        $dateTo = $request->filled('date_to') ? $request->date('date_to')->toDateString() : now()->toDateString();

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [$dateFrom, $dateTo];
    }

    private function balanceSheetData(Request $request): array
    {
        $data = $this->profitLossData($request);
        [$dateFrom, $dateTo] = [$data['dateFrom'], $data['dateTo']];
        $cash = (float) $this->tenantPayments()
            ->where('status', 'approved')
            ->whereBetween('paid_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
            ->sum('amount');
        $receivables = (float) $this->tenantInvoices()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->sum('total_jmd');
        $expenseCategories = FinanceEntry::where('tenant_id', auth()->user()->tenant_id)
            ->where('type', 'expense')
            ->whereBetween('entry_date', [$dateFrom, $dateTo])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderBy('category')
            ->pluck('total', 'category');
        $totalAssets = $cash + $receivables;
        $liabilities = (float) $data['expenses'];
        $equity = $totalAssets - $liabilities;

        return $data + [
            'cash' => $cash,
            'receivables' => $receivables,
            'totalAssets' => $totalAssets,
            'expenseCategories' => $expenseCategories,
            'equity' => $equity,
            'liabilitiesAndEquity' => $liabilities + $equity,
        ];
    }

    private function entriesSummary(Request $request): array
    {
        $income = (float) (clone $this->filteredEntries($request))->where('type', 'income')->sum('amount');
        $expenses = (float) (clone $this->filteredEntries($request))->where('type', 'expense')->sum('amount');

        return [
            'income' => $income,
            'expenses' => $expenses,
            'expense' => $expenses,
            'profitLoss' => $income - $expenses,
        ];
    }
    private function tenantDefaultFreightType(int $tenantId): string
    {
        ShippingMode::ensureDefaultsForTenant($tenantId);

        return ShippingMode::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('code') ?: 'air';
    }
    private function generatedManifestForLogisticsInvoice(SuperadminLogisticsInvoice $invoice): ?Manifest
    {
        return $invoice->items
            ->map(fn ($item) => $item->package?->courierPackage?->manifest)
            ->filter()
            ->unique('id')
            ->first();
    }

    private function logisticsWeightInPounds(float $weight, ?string $unit): float
    {
        return strtolower((string) $unit) === 'kg' ? round($weight * 2.20462262, 2) : round($weight, 2);
    }

    private function receivedAtPortStatusForTenant(int $tenantId): ?PackageStatus
    {
        $status = PackageStatus::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get()
            ->first(function (PackageStatus $status) {
                $slug = strtolower((string) $status->slug);
                $name = strtolower(trim((string) $status->name));

                return in_array($slug, ['received-at-the-port', 'received-at-port'], true)
                    || in_array($name, ['received at the port', 'received at port'], true);
            });

        return $status ?: PackageStatus::create([
            'tenant_id' => $tenantId,
            'name' => 'Received at the Port',
            'slug' => 'received-at-the-port',
            'color' => '#0ea5e9',
            'icon' => 'fa-warehouse',
            'is_default' => false,
            'sort_order' => ((int) PackageStatus::where('tenant_id', $tenantId)->max('sort_order')) + 10,
            'is_active' => true,
        ]);
    }

    private function nextGeneratedManifestNumber(int $tenantId): string
    {
        $prefix = 'MF-'.now()->format('Ymd').'-';
        $sequence = Manifest::where('tenant_id', $tenantId)->where('manifest_number', 'like', $prefix.'%')->count() + 1;

        do {
            $number = $prefix.str_pad((string) $sequence++, 4, '0', STR_PAD_LEFT);
        } while (Manifest::where('tenant_id', $tenantId)->where('manifest_number', $number)->exists());

        return $number;
    }

    private function nextCourierPackageNumber(int $tenantId): string
    {
        $configured = \App\Models\TenantSetting::getValue($tenantId, 'hawb_prefix')
            ?: \App\Models\TenantSetting::getValue($tenantId, 'courier_code')
            ?: \App\Models\TenantSetting::getValue($tenantId, 'mailbox_prefix');
        $prefix = strtoupper(trim(preg_replace('/[^A-Z0-9-]/i', '', (string) $configured)));
        if ($prefix === '') {
            $prefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', (string) optional(auth()->user()->tenant)->company_name), 0, 4)) ?: 'COU';
        }

        $sequence = Package::where('tenant_id', $tenantId)->where('tracking_number', 'like', $prefix.'%')->count() + 1;
        do {
            $tracking = $prefix.str_pad((string) $sequence++, 6, '0', STR_PAD_LEFT);
        } while (Package::where('tenant_id', $tenantId)->where('tracking_number', $tracking)->exists());

        return $tracking;
    }
    private function authorizePayment(Payment $payment): void { abort_if((int) $payment->tenant_id !== (int) auth()->user()->tenant_id, 404); }
    private function authorizeEntry(FinanceEntry $entry): void { abort_if((int) $entry->tenant_id !== (int) auth()->user()->tenant_id, 404); }
    private function authorizeBill(SuperadminBill $bill): void { abort_if((int) $bill->tenant_id !== (int) auth()->user()->tenant_id || $bill->source_type === 'shipped_package', 404); }
    private function authorizeLogisticsInvoice(SuperadminLogisticsInvoice $invoice): void { abort_if((int) $invoice->tenant_id !== (int) auth()->user()->tenant_id, 404); }
    private function publicFile(?string $path, ?string $name = null) { abort_if(! $path || ! Storage::disk('public')->exists($path), 404); return response()->file(Storage::disk('public')->path($path), ['Content-Disposition' => 'inline; filename="'.str_replace('"', '', $name ?: basename($path)).'"']); }
    private function csv(string $name, array $headers, $rows): StreamedResponse { return response()->streamDownload(function () use ($headers, $rows) { $out = fopen('php://output', 'w'); fputcsv($out, $headers); foreach ($rows as $row) fputcsv($out, $row); fclose($out); }, $name.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']); }
}












