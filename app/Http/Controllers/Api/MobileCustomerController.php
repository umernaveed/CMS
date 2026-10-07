<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientNotification;
use App\Models\PurchaseRequest;
use App\Models\MobileDeviceToken;
use App\Models\PreAlert;
use App\Services\CourierNotificationService;
use App\Models\WalletTransaction;
use App\Models\Payment;
use App\Models\CustomerShippingAddress;
use App\Models\DeliveryRequest;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileCustomerController extends Controller
{
    public function resolveTenant(Request $request)
    {
        $data = $request->validate([
            'courier_code' => ['required', 'string', 'max:60'],
        ]);

        $code = preg_replace('/\s+/', '', Str::upper(trim($data['courier_code'])));
        $tenantId = TenantSetting::whereIn('key', ['courier_code', 'hawb_prefix', 'mailbox_prefix'])
            ->whereRaw("REPLACE(UPPER(value), ' ', '') = ?", [$code])
            ->orderByRaw("case `key` when 'courier_code' then 1 when 'hawb_prefix' then 2 else 3 end")
            ->value('tenant_id');

        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        if (! $tenant || $tenant->status === 'suspended') {
            throw ValidationException::withMessages([
                'courier_code' => 'No active courier matched that code.',
            ]);
        }

        return response()->json(['tenant' => $this->tenantPayload($tenant)]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with(['tenant', 'customer'])
            ->where('tenant_id', $data['tenant_id'])
            ->where('email', $data['email'])
            ->where('user_type', 'customer')
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect for this courier.',
            ]);
        }

        $token = $user->createToken('mobile-customer-'.$user->tenant_id)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($user->tenant),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function bootstrap(Request $request)
    {
        $user = $this->customerUser($request);
        $customer = $user->customer;

        return response()->json([
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($user->tenant),
            'modules' => $this->modulePayload($user),
            'summary' => [
                'packages' => Package::where('tenant_id', $user->tenant_id)->where('customer_id', $customer->id)->count(),
                'invoices' => Invoice::where('tenant_id', $user->tenant_id)->where('customer_id', $customer->id)->count(),
                'delivery_requests' => DeliveryRequest::where('tenant_id', $user->tenant_id)->where('customer_id', $customer->id)->count(),
                'unread_notifications' => ClientNotification::where('user_id', $user->id)->where('is_read', false)->count(),
                'wallet_balance' => (float) WalletTransaction::where('tenant_id', $user->tenant_id)->where('customer_id', $customer->id)->where('status', 'approved')->latest()->value('balance_after'),
                'reward_points' => (float) $customer->total_points,
            ],
        ]);
    }

    public function packages(Request $request)
    {
        $user = $this->customerUser($request);

        return Package::with(['status', 'invoiceItems.invoice'])
            ->where('tenant_id', $user->tenant_id)
            ->where('customer_id', $user->customer->id)
            ->latest()
            ->paginate($this->perPage(request(), 20))
            ->through(fn (Package $package) => [
                'id' => $package->id,
                'tracking_number' => $package->tracking_number,
                'supplier_tracking' => $package->supplier_tracking,
                'merchant' => $package->merchant,
                'description' => $package->description,
                'status' => optional($package->status)->name,
                'weight' => $package->weight,
                'total_jmd' => round($package->customerVisibleCostJmd(), 2),
                'date_received' => optional($package->date_received)->toDateString(),
            ]);
    }

    public function invoices(Request $request)
    {
        $user = $this->customerUser($request);

        return Invoice::where('tenant_id', $user->tenant_id)
            ->where('customer_id', $user->customer->id)
            ->latest()
            ->paginate($this->perPage(request(), 20))
            ->through(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_jmd' => $invoice->total_jmd,
                'payment_status' => $invoice->payment_status,
                'invoice_status' => $invoice->invoice_status,
                'due_date' => optional($invoice->due_date)->toDateString(),
            ]);
    }

    public function deliveryRequests(Request $request)
    {
        $user = $this->customerUser($request);

        return DeliveryRequest::where('tenant_id', $user->tenant_id)
            ->where('customer_id', $user->customer->id)
            ->latest()
            ->paginate($this->perPage(request(), 20))
            ->through(fn (DeliveryRequest $delivery) => [
                'id' => $delivery->id,
                'request_number' => $delivery->request_number,
                'status' => $delivery->status,
                'package_count' => $delivery->package_count,
                'delivery_cost' => $delivery->delivery_cost,
                'delivery_address' => $delivery->delivery_address,
                'preferred_date' => optional($delivery->preferred_date)->toDateString(),
            ]);
    }


    public function addresses(Request $request)
    {
        $user = $this->customerUser($request);

        return CustomerShippingAddress::where('tenant_id', $user->tenant_id)
            ->where('customer_id', $user->customer->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('label')
            ->get()
            ->map(fn (CustomerShippingAddress $address) => [
                'id' => $address->id,
                'shipment_type' => $address->shipment_type,
                'label' => $address->label,
                'country_code' => $address->country_code,
                'flag' => $address->display_flag,
                'flag_image' => $address->display_flag_image_url,
                'name' => $address->name,
                'marks' => $address->marks,
                'address_1' => $address->address_1,
                'address_2' => $this->isChinaAddress($address) ? null : $address->address_2,
                'entrance_no' => $address->entrance_no,
                'country' => $address->country,
                'province' => $address->province,
                'city' => $address->city,
                'state' => $address->state,
                'zip_code' => $address->zip_code,
                'detailed_address' => $address->detailed_address,
                'number' => $address->number,
                'note' => $address->note,
            ]);
    }

    public function preAlerts(Request $request)
    {
        $user = $this->customerUser($request);

        return PreAlert::where('tenant_id', $user->tenant_id)
            ->where('customer_id', $user->customer->id)
            ->latest()
            ->paginate($this->perPage(request(), 20))
            ->through(fn (PreAlert $preAlert) => [
                'id' => $preAlert->id,
                'pre_alert_number' => $preAlert->pre_alert_number,
                'supplier_tracking' => $preAlert->supplier_tracking,
                'courier' => $preAlert->courier,
                'merchant' => $preAlert->merchant,
                'description' => $preAlert->description,
                'price_usd' => $preAlert->price_usd,
                'delivery_type' => $preAlert->delivery_type,
                'status' => $preAlert->status,
                'created_at' => optional($preAlert->created_at)->toIso8601String(),
            ]);
    }

    public function purchaseRequests(Request $request)
    {
        $user = $this->customerUser($request);

        return PurchaseRequest::where('tenant_id', $user->tenant_id)
            ->where('customer_id', $user->customer->id)
            ->latest()
            ->paginate($this->perPage(request(), 20))
            ->through(fn (PurchaseRequest $purchaseRequest) => [
                'id' => $purchaseRequest->id,
                'purchase_request_number' => $purchaseRequest->purchase_request_number,
                'supplier_tracking' => $purchaseRequest->supplier_tracking,
                'product_url' => $purchaseRequest->product_url,
                'courier' => $purchaseRequest->courier,
                'merchant' => $purchaseRequest->merchant,
                'description' => $purchaseRequest->description,
                'price_usd' => $purchaseRequest->price_usd,
                'delivery_type' => $purchaseRequest->delivery_type,
                'status' => $purchaseRequest->status,
                'status_label' => $purchaseRequest->statusLabel(),
                'created_at' => optional($purchaseRequest->created_at)->toIso8601String(),
            ]);
    }

    public function wallet(Request $request)
    {
        $user = $this->customerUser($request);
        $balance = (float) WalletTransaction::where('tenant_id', $user->tenant_id)
            ->where('customer_id', $user->customer->id)
            ->where('status', 'approved')
            ->latest()
            ->value('balance_after');

        $transactions = WalletTransaction::where('tenant_id', $user->tenant_id)
            ->where('customer_id', $user->customer->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'balance' => $balance,
            'reward_points' => (float) $user->customer->total_points,
            'referral_points' => (float) $user->customer->total_referral_points,
            'transactions' => $transactions->map(fn (WalletTransaction $transaction) => [
                'id' => $transaction->id,
                'transaction_type' => $transaction->transaction_type,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'description' => $transaction->description,
                'created_at' => optional($transaction->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function uploadInvoiceReceipt(Request $request, Invoice $invoice, CourierNotificationService $courierNotifications)
    {
        $user = $this->customerUser($request);
        abort_if((int) $invoice->tenant_id !== (int) $user->tenant_id || (int) $invoice->customer_id !== (int) $user->customer->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $file = $request->file('receipt');
        $payment = Payment::create([
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'outlet_id' => $invoice->outlet_id,
            'payment_number' => $this->nextCustomerPaymentNumber((int) $invoice->tenant_id),
            'amount' => $data['amount'],
            'reference_number' => $data['reference_number'] ?: $invoice->invoice_number,
            'receipt_path' => $file->store('payment-receipts', 'public'),
            'receipt_original_name' => $file->getClientOriginalName(),
            'receipt_mime_type' => $file->getClientMimeType(),
            'notes' => $data['notes'] ?? 'Uploaded from mobile app.',
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        $courierNotifications->notify(
            $invoice->tenant_id,
            $invoice->outlet_id,
            'payment_upload',
            'New Payment Upload',
            ($user->full_name ?: $user->customer->mailbox).' uploaded payment proof for '.$invoice->invoice_number.'.',
            route('courier.invoices.show', $invoice),
            ['customer_id' => $user->customer->id, 'invoice_id' => $invoice->id, 'payment_id' => $payment->id]
        );

        return response()->json(['payment_id' => $payment->id, 'payment_number' => $payment->payment_number]);
    }

    public function registerDeviceToken(Request $request)
    {
        $user = $this->customerUser($request);
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:150'],
            'fcm_token' => ['required', 'string'],
            'platform' => ['nullable', 'string', 'max:30'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        MobileDeviceToken::updateOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'device_id' => $data['device_id'],
            ],
            [
                'platform' => $data['platform'] ?? null,
                'fcm_token' => $data['fcm_token'],
                'app_version' => $data['app_version'] ?? null,
                'last_active_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }
    public function notifications(Request $request)
    {
        $user = $this->customerUser($request);

        return ClientNotification::where('user_id', $user->id)
            ->latest('created_at')
            ->paginate($this->perPage(request(), 20))
            ->through(fn (ClientNotification $notification) => [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->messagePreview(),
                'is_read' => $notification->is_read,
                'created_at' => optional($notification->created_at)->toIso8601String(),
            ]);
    }

    private function customerUser(Request $request): User
    {
        $user = $request->user();
        abort_if(! $user || $user->user_type !== 'customer' || ! $user->customer, 403);

        return $user->loadMissing(['tenant', 'customer']);
    }

    private function tenantPayload(Tenant $tenant): array
    {
        $settings = TenantSetting::where('tenant_id', $tenant->id)
            ->whereIn('key', [
                'courier_code',
                'hawb_prefix',
                'mailbox_prefix',
                'brand_primary_color',
                'brand_secondary_color',
                'login_background_url',
                'app_logo',
                'company_logo',
            ])
            ->pluck('value', 'key');

        return [
            'tenant_id' => $tenant->id,
            'courier_code' => $settings['courier_code'] ?? $settings['hawb_prefix'] ?? $settings['mailbox_prefix'] ?? Str::upper($tenant->slug),
            'company_name' => $tenant->company_name,
            'logo' => $this->assetUrl($tenant->logo ?: ($settings['app_logo'] ?? $settings['company_logo'] ?? null)),
            'primary_color' => $settings['brand_primary_color'] ?? '#135fd2',
            'secondary_color' => $settings['brand_secondary_color'] ?? '#073b70',
            'login_background' => $this->assetUrl($settings['login_background_url'] ?? null),
            'modules' => [
                'packages' => true,
                'delivery' => true,
                'invoices' => true,
                'payments' => true,
                'live_chat' => true,
            ],
        ];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'mailbox' => $user->mailbox ?: optional($user->customer)->mailbox,
            'image' => $this->assetUrl($user->image),
        ];
    }

    private function modulePayload(User $user): array
    {
        return [
            'packages' => $user->subscriptionAllowsModule('packages'),
            'delivery' => $user->subscriptionAllowsModule('delivery'),
            'invoices' => $user->subscriptionAllowsModule('invoices'),
            'payments' => $user->subscriptionAllowsModule('payments'),
            'live_chat' => $user->subscriptionAllowsModule('live_chat'),
        ];
    }


    private function nextCustomerPaymentNumber(int $tenantId): string
    {
        $prefix = 'PAY-'.now()->format('Ymd').'-';
        $sequence = Payment::where('tenant_id', $tenantId)
            ->where('payment_number', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function isChinaAddress(CustomerShippingAddress $address): bool
    {
        return strtoupper(trim((string) $address->country_code)) === 'CN'
            || strcasecmp(trim((string) $address->country), 'China') === 0;
    }
    private function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset(ltrim($path, '/'));
    }
}
