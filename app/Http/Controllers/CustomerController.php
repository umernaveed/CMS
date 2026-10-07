<?php

namespace App\Http\Controllers;

use App\Models\AuthorizedUser;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackagePurchaseReceipt;
use App\Models\PackageStatus;
use App\Models\Payment;
use App\Models\ShippingMode;
use App\Models\TenantSetting;
use App\Models\WalletTransaction;
use App\Services\CourierNotificationService;
use App\Services\CustomerShippingAddressService;
use App\Services\LoyaltyService;
use App\Support\InvoicePdf;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    private array $walletPaymentMethods = ['online', 'bank_transfer', 'cash', 'cheque', 'debit_card', 'other'];

    public function dashboard(Request $request, CustomerShippingAddressService $addresses, LoyaltyService $loyalty)
    {
        $customer = $this->customer();
        $packages = $this->filteredCustomerPackages($request, $customer)
            ->paginate($this->perPage($request, 10))
            ->withQueryString();
        $packageStatuses = PackageStatus::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get();
        $openInvoiceQuery = Invoice::where('customer_id', $customer->id)->whereIn('payment_status', ['unpaid', 'partial']);
        $pendingInvoices = (clone $openInvoiceQuery)->count();
        $customerBalance = (float) (clone $openInvoiceQuery)->sum('total_jmd');
        $walletBalance = $loyalty->cashBalance($customer);
        $rewardPoints = $loyalty->pointsBalance($customer);
        $rewardTransactions = $loyalty->rewardTransactions($customer)->latest()->limit(5)->get();
        $referralCode = $loyalty->ensureReferralCode(auth()->user());
        $referralUrl = route('customer.register', ['courier' => auth()->user()->tenant_id, 'ref' => $referralCode]);
        $referralCount = $customer->referralsMade()->where('status', 'completed')->count();
        $referralPoints = (float) $customer->total_referral_points;
        $pointValueJmd = (float) TenantSetting::getValue(auth()->user()->tenant_id, 'loyalty_point_value_jmd', 1);
        $loyaltyEnabled = $loyalty->isEnabled(auth()->user()->tenant_id);
        $inTransitCount = Package::where('customer_id', $customer->id)->whereHas('status', fn ($q) => $q->where('name', 'like', '%Transit%'))->count();
        $shippingAddresses = $customer->shippingAddresses()->where('is_active', true)->get();
        $bankingDetails = TenantSetting::getValue(auth()->user()->tenant_id, 'banking_details', '');
        return view('customer.dashboard', compact('packages','packageStatuses','pendingInvoices','customerBalance','walletBalance','shippingAddresses','bankingDetails','inTransitCount','loyaltyEnabled','rewardTransactions','referralCode','referralUrl','referralCount','referralPoints','rewardPoints','pointValueJmd'));
    }

    public function affiliateLoyalty(LoyaltyService $loyalty)
    {
        $customer = $this->customer();
        $walletBalance = $loyalty->cashBalance($customer);
        $rewardPoints = $loyalty->pointsBalance($customer);
        $rewardTransactions = $loyalty->rewardTransactions($customer)->latest()->limit(5)->get();
        $referralCode = $loyalty->ensureReferralCode(auth()->user());
        $referralUrl = route('customer.register', ['courier' => auth()->user()->tenant_id, 'ref' => $referralCode]);
        $referralCount = $customer->referralsMade()->where('status', 'completed')->count();
        $referralPoints = (float) $customer->total_referral_points;
        $pointValueJmd = (float) TenantSetting::getValue(auth()->user()->tenant_id, 'loyalty_point_value_jmd', 1);
        return view('customer.affiliate-loyalty', compact('customer','walletBalance','rewardPoints','rewardTransactions','referralCode','referralUrl','referralCount','referralPoints','pointValueJmd'));
    }

    public function storeWalletTopUp(Request $request, LoyaltyService $loyalty, CourierNotificationService $notifications)
    {
        $customer = $this->customer();
        $data = $request->validate(['amount' => 'required|numeric|min:0.01', 'payment_method' => 'required|in:'.implode(',', $this->walletPaymentMethods), 'reference_number' => 'nullable|string|max:150', 'receipt' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:4096', 'description' => 'nullable|string|max:1000']);
        $file = $data['receipt'];
        $transaction = WalletTransaction::create(['tenant_id' => $customer->tenant_id, 'customer_id' => $customer->id, 'transaction_type' => 'credit', 'source_type' => 'manual', 'amount' => (float) $data['amount'], 'balance_before' => $loyalty->cashBalance($customer), 'balance_after' => $loyalty->cashBalance($customer), 'description' => $data['description'] ?? 'Wallet top-up submitted', 'status' => 'pending', 'payment_method' => $data['payment_method'], 'reference_number' => $data['reference_number'] ?? null, 'receipt_path' => $file->store('wallet-top-up-receipts', 'public'), 'receipt_original_name' => $file->getClientOriginalName()]);
        $notifications->notify($customer->tenant_id, $customer->outlet_id, 'wallet_top_up', 'New Wallet Top-up', ($customer->user->full_name ?: $customer->mailbox).' submitted a wallet top-up.', route('courier.customers.show', $customer), ['customer_id' => $customer->id, 'wallet_transaction_id' => $transaction->id]);
        return back()->with('success', 'Wallet top-up submitted for review.');
    }

    public function walletTopUpHistory(Request $request) { $topUps = $this->filteredWalletTopUps($request)->latest()->paginate($this->perPage($request, 15))->withQueryString(); return view('customer.wallet-top-ups.index', compact('topUps')); }
    public function printWalletTopUps(Request $request) { $topUps = $this->filteredWalletTopUps($request)->latest()->get(); return view('customer.wallet-top-ups.print', compact('topUps')); }
    public function downloadWalletTopUps(Request $request) { return $this->csv('wallet-top-ups', ['date','amount_jmd','payment_method','reference','status'], $this->filteredWalletTopUps($request)->latest()->get()->map(fn ($t) => [optional($t->created_at)->format('Y-m-d H:i:s'), $t->amount, $t->payment_method, $t->reference_number, $t->status])); }

    public function packages(Request $request)
    {
        $customer = $this->customer();
        $packages = $this->filteredCustomerPackages($request, $customer)
            ->paginate($this->perPage($request, 15))
            ->withQueryString();
        $packageStatuses = PackageStatus::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get();
        $shippingModes = $this->shippingModes($customer->tenant_id); return view('customer.packages.index', compact('packages', 'packageStatuses', 'shippingModes'));
    }

    public function createPackage() { return view('customer.packages.create', ['shippingModes' => $this->shippingModes(auth()->user()->tenant_id)]); }
    private function shippingModes(int $tenantId)
    {
        ShippingMode::ensureDefaultsForTenant($tenantId);

        return ShippingMode::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function storePackage(Request $request)
    {
        $customer = $this->customer();
        $data = $request->validate([
            'tracking_number' => 'required|string|max:150',
            'merchant' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:1000',
            'freight_type' => 'nullable|string|max:80',
            'purchase_receipts' => 'nullable|array|max:5',
            'purchase_receipts.*' => 'file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);
        $files = $data['purchase_receipts'] ?? [];
        unset($data['purchase_receipts']);

        $package = Package::create($data + ['tenant_id' => $customer->tenant_id, 'customer_id' => $customer->id, 'mailbox' => $customer->mailbox]);
        foreach ($files as $file) {
            PackagePurchaseReceipt::create([
                'package_id' => $package->id,
                'path' => $file->store('package-purchase-receipts', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
            ]);
        }

        return redirect()->route('customer.packages')->with('success', 'Pre-alert submitted.');
    }

    public function packageDetails($id) { $package = $this->customerPackage($id)->load(['status','logs.status','purchaseReceipts','invoiceItems.invoice']); return view('customer.packages.show', compact('package')); }
    public function packageReceipt($id) { $package = $this->customerPackage($id); return view('customer.packages.receipt', compact('package')); }
    public function packagePurchaseReceipt($id, PackagePurchaseReceipt $receipt) { $package = $this->customerPackage($id); abort_if((int) $receipt->package_id !== (int) $package->id, 404); return $this->publicFile($receipt->path, $receipt->original_name); }
    public function uploadPackageReceipt(Request $request, $id)
    {
        $package = $this->customerPackage($id);
        $data = $request->validate([
            'purchase_receipts' => 'required|array|min:1|max:5',
            'purchase_receipts.*' => 'file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        foreach ($data['purchase_receipts'] as $file) {
            PackagePurchaseReceipt::create([
                'package_id' => $package->id,
                'path' => $file->store('package-purchase-receipts', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
            ]);
        }

        return back()->with('success', count($data['purchase_receipts']).' purchase invoice file(s) uploaded.');
    }

    public function invoices(Request $request) { $invoices = $this->filteredCustomerInvoices($request)->paginate($this->perPage($request, 15))->withQueryString(); return view('customer.invoices.index', compact('invoices')); }
    public function invoiceDetails($id) { $invoice = $this->customerInvoice($id); return view('customer.invoices.show', compact('invoice')); }
    public function invoicePrint($id) { $invoice = $this->customerInvoice($id); return view('invoices.receipt-download', compact('invoice')); }
    public function invoiceThermal($id) { return $this->invoiceDownload($id); }
    public function invoiceDownload($id) { $invoice = $this->customerInvoice($id); $invoice->load(['tenant','customer.user','items.package','payments.method']); return InvoicePdf::download($invoice); }

    public function uploadInvoiceReceipt(Request $request, $id)
    {
        $invoice = $this->customerInvoice($id);
        $data = $request->validate(['payment_method' => 'nullable|in:'.implode(',', $this->walletPaymentMethods), 'reference_number' => 'nullable|string|max:150', 'receipt' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:4096']);
        $file = $data['receipt'];
        Payment::create(['tenant_id' => $invoice->tenant_id, 'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id, 'payment_number' => 'PAY-'.now()->format('YmdHis'), 'amount' => $invoice->total_jmd, 'status' => 'pending', 'reference_number' => $data['reference_number'] ?? $invoice->invoice_number, 'receipt_path' => $file->store('payment-receipts', 'public'), 'receipt_original_name' => $file->getClientOriginalName(), 'receipt_mime_type' => $file->getClientMimeType(), 'paid_at' => now()]);
        return back()->with('success', 'Payment receipt uploaded.');
    }

    public function authorizedUsers() { $authorizedUsers = $this->customer()->authorizedUsers()->latest()->paginate(15); return view('customer.authorized-users.index', compact('authorizedUsers')); }
    public function createAuthorizedUser() { return view('customer.authorized-users.create'); }
    public function storeAuthorizedUser(Request $request) { $this->customer()->authorizedUsers()->create($this->authorizedUserData($request)); return redirect()->route('customer.authorized-users.index')->with('success', 'Authorized user added.'); }
    public function editAuthorizedUser(AuthorizedUser $authorizedUser) { $this->authorizeAuthorizedUser($authorizedUser); return view('customer.authorized-users.edit', compact('authorizedUser')); }
    public function updateAuthorizedUser(Request $request, AuthorizedUser $authorizedUser) { $this->authorizeAuthorizedUser($authorizedUser); $authorizedUser->update($this->authorizedUserData($request)); return redirect()->route('customer.authorized-users.index')->with('success', 'Authorized user updated.'); }
    public function destroyAuthorizedUser(AuthorizedUser $authorizedUser) { $this->authorizeAuthorizedUser($authorizedUser); $authorizedUser->delete(); return back()->with('success', 'Authorized user removed.'); }
    public function deleteAccount() { return view('customer.account.delete'); }
    public function destroyAccount(Request $request) { $request->validate(['password' => 'required']); abort_unless(Hash::check($request->password, auth()->user()->password), 422); auth()->user()->update(['is_active' => false]); auth()->logout(); return redirect()->route('login')->with('success', 'Account deleted.'); }

    private function customer(): Customer { $customer = auth()->user()->customer; abort_if(! $customer, 404); return $customer; }
    private function filteredWalletTopUps(Request $request) { $customer = $this->customer(); $request->validate(['q' => 'nullable|string|max:150', 'status' => 'nullable|in:pending,approved,cancelled', 'payment_method' => 'nullable|in:'.implode(',', $this->walletPaymentMethods), 'date_from' => 'nullable|date', 'date_to' => 'nullable|date|after_or_equal:date_from']); return WalletTransaction::where('tenant_id', auth()->user()->tenant_id)->where('customer_id', $customer->id)->where('transaction_type', 'credit')->where('source_type', 'manual')->whereIn('payment_method', $this->walletPaymentMethods)->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->payment_method)); }
    private function filteredCustomerPackages(Request $request, Customer $customer): Builder
    {
        $query = Package::where('tenant_id', $customer->tenant_id)
            ->where(function ($query) use ($customer) {
                $query->where('customer_id', $customer->id)
                    ->orWhere('mailbox', $customer->mailbox);
            })
            ->with(['status', 'invoiceItems.invoice'])
            ->withCount('purchaseReceipts')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('tracking_number', 'like', $term)
                        ->orWhere('supplier_tracking', 'like', $term)
                        ->orWhere('merchant', 'like', $term)
                        ->orWhere('courier', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($request->filled('status_id'), fn ($query) => $query->where('status_id', $request->integer('status_id')))
            ->when($request->filled('freight_type'), fn ($query) => $query->where('freight_type', $request->freight_type))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('date_received', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('date_received', '<=', $request->date_to));

        return $this->sortCustomerPackages($query, $request);
    }

    private function sortCustomerPackages(Builder $query, Request $request): Builder
    {
        $sorts = [
            'date_received' => 'date_received',
            'tracking_number' => 'tracking_number',
            'merchant' => 'merchant',
            'freight_type' => 'freight_type',
            'total_jmd' => DB::raw("CASE WHEN manifest_id IS NULL THEN 0 WHEN EXISTS (SELECT 1 FROM invoice_items INNER JOIN invoices ON invoices.id = invoice_items.invoice_id WHERE invoice_items.package_id = packages.id AND invoices.voided_at IS NULL AND invoices.invoice_status != 'cancelled' AND invoices.payment_status != 'cancelled') THEN (SELECT invoice_items.total_jmd FROM invoice_items INNER JOIN invoices ON invoices.id = invoice_items.invoice_id WHERE invoice_items.package_id = packages.id AND invoices.voided_at IS NULL AND invoices.invoice_status != 'cancelled' AND invoices.payment_status != 'cancelled' ORDER BY invoices.created_at DESC LIMIT 1) ELSE 0 END"),
        ];
        $sort = $sorts[$request->input('sort')] ?? 'date_received';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }

    private function filteredCustomerInvoices(Request $request): Builder
    {
        $query = Invoice::where('customer_id', $this->customer()->id)
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('invoice_number', 'like', $term)
                        ->orWhere('mailbox', 'like', $term)
                        ->orWhere('notes', 'like', $term);
                });
            })
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->payment_status))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date_to));

        return $this->sortCustomerInvoices($query, $request);
    }

    private function sortCustomerInvoices(Builder $query, Request $request): Builder
    {
        $sorts = [
            'created_at' => 'created_at',
            'due_date' => 'due_date',
            'invoice_number' => 'invoice_number',
            'total_jmd' => 'total_jmd',
            'payment_status' => 'payment_status',
        ];
        $sort = $sorts[$request->input('sort')] ?? 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }

    private function customerPackage($id): Package { $customer = $this->customer(); return Package::where(function ($q) use ($customer) { $q->where('customer_id', $customer->id)->orWhere('mailbox', $customer->mailbox); })->findOrFail($id); }
    private function customerInvoice($id): Invoice { return Invoice::where('customer_id', $this->customer()->id)->with(['tenant','customer.user','items.package','payments.method'])->findOrFail($id); }
    private function authorizedUserData(Request $request): array { return $request->validate(['full_name' => 'required|string|max:150', 'phone' => 'nullable|string|max:50', 'identification_type' => 'nullable|string|max:50', 'identification_number' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:1000', 'is_active' => 'nullable|boolean']) + ['is_active' => $request->boolean('is_active', true)]; }
    private function authorizeAuthorizedUser(AuthorizedUser $authorizedUser): void { abort_if((int) $authorizedUser->customer_id !== (int) $this->customer()->id, 404); }
    private function publicFile(?string $path, ?string $name = null) { abort_if(! $path || ! Storage::disk('public')->exists($path), 404); return response()->file(Storage::disk('public')->path($path), ['Content-Disposition' => 'inline; filename="'.str_replace('"', '', $name ?: basename($path)).'"']); }
    private function csv(string $name, array $headers, $rows) { return response()->streamDownload(function () use ($headers, $rows) { $out = fopen('php://output', 'w'); fputcsv($out, $headers); foreach ($rows as $row) fputcsv($out, $row); fclose($out); }, $name.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']); }
}

