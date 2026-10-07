<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\PricingTier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerShippingAddressService;
use App\Services\CustomerNotificationService;
use App\Services\LoyaltyService;
use App\Services\MailboxService;
use App\Services\SubscriptionLimitService;
use App\Services\WmsSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerRegisterController extends Controller
{
    public function create(Request $request)
    {
        $courier = app()->bound('currentTenant') ? app('currentTenant') : null;
        if ($request->filled('courier')) {
            $courier = Tenant::whereIn('status', ['active', 'trial'])
                ->where(function ($query) use ($request) {
                    $query->where('subdomain', $request->courier)
                        ->orWhere('slug', $request->courier)
                        ->orWhere('id', $request->courier);
                })
                ->first();
        }

        $couriers = $courier ? collect([$courier]) : Tenant::whereIn('status', ['active', 'trial'])->orderBy('company_name')->get();

        return view('auth.register-customer', compact('couriers', 'courier'));
    }

    public function store(Request $request, MailboxService $mailboxes, SubscriptionLimitService $limits, CustomerShippingAddressService $addresses, LoyaltyService $loyalty, CustomerNotificationService $notifications)
    {
        $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        Log::info('Customer signup submitted', [
            'email' => $request->input('email'),
            'current_tenant_id' => optional($currentTenant)->id,
            'requested_tenant_id' => $request->input('tenant_id'),
            'ip' => $request->ip(),
        ]);

        $rules = [
            'tenant_id' => $currentTenant ? 'nullable|exists:tenants,id' : 'required|exists:tenants,id',
            'account_type' => 'required|in:personal,business',
            'company_name' => 'required_if:account_type,business|nullable|string|max:255',
            'company_registration' => 'required_if:account_type,business|nullable|string|max:150',
            'representative_role' => 'required_if:account_type,business|nullable|string|max:150',
            'government_id_document' => 'required_if:account_type,business|nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'tax_document' => 'required_if:account_type,business|nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'business_doc' => 'required_if:account_type,business|nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'trn' => 'nullable|string|max:100',
            'password' => 'required|string|min:8|confirmed',
            'address_line1' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'parish' => 'nullable|string|max:100',
            'referral_code' => 'nullable|string|max:50',
            'terms' => 'accepted',
        ];
        $data = $request->validate($rules);
        $data['email'] = Str::lower(trim($data['email']));

        $tenant = $currentTenant ?: Tenant::whereIn('status', ['active', 'trial'])->findOrFail($data['tenant_id']);
        Log::info('Customer signup tenant resolved', [
            'email' => $data['email'],
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->company_name,
        ]);

        $this->ensureNoDuplicateCustomerIdentity($tenant->id, $data);
        $limits->ensureCanCreate($tenant->id, 'customers');
        $registrationDocuments = $this->storeRegistrationDocuments($request, $tenant->id);
        $businessDocument = collect($registrationDocuments['business_documents'] ?? [])->first();
        $referrer = null;
        if (! empty($data['referral_code'])) {
            $referrer = Customer::where('tenant_id', $tenant->id)
                ->whereHas('user', fn ($query) => $query->where('referral_code', strtoupper(trim($data['referral_code']))))
                ->with('user')
                ->first();
        }

        $user = DB::transaction(function () use ($data, $tenant, $mailboxes, $addresses, $loyalty, $referrer, $businessDocument, $registrationDocuments) {
            $outletId = Outlet::where('tenant_id', $tenant->id)->where('is_main', true)->value('id')
                ?: Outlet::where('tenant_id', $tenant->id)->value('id');
            $mailbox = $mailboxes->nextForTenant($tenant);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'outlet_id' => $outletId,
                'user_type' => 'customer',
                'mailbox' => $mailbox,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'trn' => $data['trn'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'city' => $data['city'] ?? null,
                'parish' => $data['parish'] ?? null,
                'password' => Hash::make($data['password']),
                'referred_by' => $referrer?->user?->referral_code,
                'is_active' => true,
            ]);
            $loyalty->ensureReferralCode($user);

            $customer = Customer::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'mailbox' => $mailbox,
                'outlet_id' => $outletId,
                'pricing_tier_id' => PricingTier::where('tenant_id', $tenant->id)->where('is_default', true)->value('id'),
                'account_type' => $data['account_type'],
                'company_name' => $data['account_type'] === 'business' ? ($data['company_name'] ?? null) : null,
                'company_registration' => $data['account_type'] === 'business' ? ($data['company_registration'] ?? null) : null,
                'representative_role' => $data['account_type'] === 'business' ? ($data['representative_role'] ?? null) : null,
                'business_document_path' => $businessDocument['path'] ?? null,
                'business_document_original_name' => $businessDocument['original_name'] ?? null,
                'business_document_mime_type' => $businessDocument['mime_type'] ?? null,
                'registration_documents' => $registrationDocuments,
                'is_active' => true,
            ]);

            $addresses->syncDefaultAddresses($customer);
            $loyalty->awardSignup($customer);
            if ($referrer && $referrer->id !== $customer->id) {
                $loyalty->completeReferral($referrer, $customer);
            }

            return $user;
        });

        Auth::login($user);
        Log::info('Customer signup account created; attempting welcome email', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'email' => $user->email,
        ]);

        $welcomeSent = $notifications->customerWelcome($user->fresh(['tenant', 'customer']));
        if ($welcomeSent) {
            Log::info('Customer signup welcome email sent', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'email' => $user->email,
            ]);
        } else {
            Log::error('Customer signup welcome email was not sent', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'email' => $user->email,
            ]);
        }

        $customer = $user->customer;
        $wmsSync = app(WmsSyncService::class);
        if ($customer && ! $wmsSync->syncCustomer($customer->fresh(['tenant', 'user', 'outlet']))) {
            Log::warning('Customer signup WMS sync failed', [
                'user_id' => $user->id,
                'customer_id' => $customer->id,
                'tenant_id' => $user->tenant_id,
                'mailbox' => $customer->mailbox,
                'error' => $wmsSync->lastError(),
            ]);
        }

        return redirect()->route('customer.dashboard')->with('success', 'Your customer account is ready. Your mailbox was created automatically.');
    }

    private function storeRegistrationDocuments(Request $request, int $tenantId): array
    {
        $fields = [
            'government_id_document' => 'government_id_documents',
            'tax_document' => 'tax_documents',
            'business_doc' => 'business_documents',
        ];

        $documents = [];

        foreach ($fields as $input => $key) {
            if (! $request->hasFile($input)) {
                continue;
            }

            $documents[$key][] = $this->storeRegistrationDocument($request->file($input), $tenantId, $key);
        }

        return $documents;
    }

    private function storeRegistrationDocument($file, int $tenantId, string $type): array
    {
        $directory = 'private/customer-registration/'.$tenantId.'/'.Str::uuid();
        $absoluteDirectory = storage_path('app/'.$directory);
        File::ensureDirectoryExists($absoluteDirectory);

        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'business-document';
        $filename = Str::slug($type).'-'.$base.'.'.$file->getClientOriginalExtension();
        $file->move($absoluteDirectory, $filename);

        return [
            'path' => $directory.'/'.$filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
        ];
    }

    private function ensureNoDuplicateCustomerIdentity(int $tenantId, array $data): void
    {
        $checks = [
            'trn' => ['label' => 'TRN/SSN', 'message' => 'A customer with this TRN/SSN already exists under this courier.'],
            'email' => ['label' => 'email address', 'message' => 'A customer with this email address already exists under this courier.'],
            'phone' => ['label' => 'telephone number', 'message' => 'A customer with this telephone number already exists under this courier.'],
        ];

        $errors = [];
        foreach ($checks as $field => $meta) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            $exists = User::where('tenant_id', $tenantId)
                ->where('user_type', 'customer')
                ->where($field, $value)
                ->exists();

            if ($exists) {
                $errors[$field] = $meta['message'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
