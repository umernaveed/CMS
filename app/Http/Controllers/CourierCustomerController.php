<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ActivityLog;
use App\Models\Outlet;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\SubscriptionLimitService;
use App\Services\CustomerShippingAddressService;
use App\Services\CustomerNotificationService;
use App\Services\CourierNotificationService;
use App\Services\MailboxService;
use App\Services\LoyaltyService;
use App\Services\WmsSyncService;
use App\Models\PricingTier;
use App\Models\TenantSetting;

class CourierCustomerController extends Controller
{
    public function index(Request $request)
    {
        $customersQuery = $this->filteredCustomers($request);
        $perPage = $this->perPage($request, 15, (clone $customersQuery)->count());
        $customers = $customersQuery
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
        $outlets = $this->tenantOutlets()->orderBy('name')->get();

        return view('courier.customers.index', compact('customers', 'outlets'));
    }

    public function print(Request $request)
    {
        $customers = $this->filteredCustomers($request)
            ->latest()
            ->get();

        return view('courier.customers.print', compact('customers'));
    }

    public function importToWms(WmsSyncService $sync)
    {
        if (! $sync->enabled()) {
            return back()->withErrors(['wms_import' => 'WMS sync is not configured. Set WMS_BASE_URL and WMS_SYNC_TOKEN first.']);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $synced = 0;
        $failed = 0;
        $errors = [];

        $this->tenantCustomers()
            ->with(['tenant', 'user', 'outlet'])
            ->orderBy('id')
            ->chunkById(50, function ($customers) use ($sync, &$synced, &$failed, &$errors) {
                foreach ($customers as $customer) {
                    try {
                        if ($sync->syncCustomer($customer)) {
                            $synced++;
                        } else {
                            $failed++;
                            if (count($errors) < 5) {
                                $errors[] = $customer->mailbox.': '.($sync->lastError() ?: 'Unknown WMS sync error.');
                            }
                        }
                    } catch (\Throwable $exception) {
                        $failed++;
                        report($exception);
                        if (count($errors) < 5) {
                            $errors[] = $customer->mailbox.': '.$exception->getMessage();
                        }
                    }
                }
            });

        ActivityLog::record('synced', 'customers', 'Imported customers to WMS', [
            'synced' => $synced,
            'failed' => $failed,
        ]);

        $message = 'WMS import completed. Synced '.$synced.' customer'.($synced === 1 ? '' : 's').'.';
        if ($failed > 0) {
            return back()
                ->with('warning', $message.' Failed '.$failed.'.')
                ->with('wms_import_errors', $errors);
        }

        return back()->with('success', $message);
    }

    protected function perPage(Request $request, int $default, ?int $total = null): int
    {
        if ($request->input('per_page') === 'all') {
            return max((int) ($total ?? $default), 1);
        }

        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : $default;
    }
    public function create()
    {
        $outlets = $this->tenantOutlets()->orderBy('name')->get();
        $pricingTiers = PricingTier::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('courier.customers.create', compact('outlets', 'pricingTiers'));
    }

    public function store(Request $request, SubscriptionLimitService $limits, CustomerShippingAddressService $addresses, MailboxService $mailboxes, CustomerNotificationService $notifications)
    {
        $tenantId = auth()->user()->tenant_id;
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'mailbox' => 'nullable|string|max:100',
            'outlet_id' => 'nullable|exists:outlets,id',
            'address_line1' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'parish' => 'nullable|string|max:100',
            'trn' => 'nullable|string|max:100',
            'password' => 'required|string|min:8',
            'notes' => 'nullable|string',
            'email_group' => 'nullable|string|max:100',
            'account_type' => 'nullable|in:personal,business',
            'company_name' => 'nullable|string|max:255',
            'company_registration' => 'nullable|string|max:150',
            'representative_role' => 'nullable|string|max:150',
            'pricing_tier_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $data['outlet_id'] = auth()->user()->outlet_id;
        }
        $this->ensureTenantOutlet($data['outlet_id'] ?? null);

        $limits->ensureCanCreate($tenantId, 'customers');
        $this->ensureNoDuplicateCustomerIdentity($tenantId, $data);
        if (! empty($data['mailbox'])) {
            $this->ensureMailboxIsAvailable($tenantId, $data['mailbox']);
        }

        $tenant = auth()->user()->tenant;

        $customer = DB::transaction(function () use ($data, $tenant, $tenantId, $mailboxes) {
            $mailbox = $data['mailbox'] ?: $mailboxes->nextForTenant($tenant);
            $tierId = ! empty($data['pricing_tier_id'])
                ? PricingTier::where('tenant_id', $tenantId)->whereKey($data['pricing_tier_id'])->value('id')
                : PricingTier::where('tenant_id', $tenantId)->where('is_default', true)->value('id');
            $user = User::create([
                'tenant_id' => $tenantId,
                'outlet_id' => $data['outlet_id'] ?? null,
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
                'is_active' => $data['is_active'] ?? true,
            ]);

            return Customer::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'mailbox' => $mailbox,
                'outlet_id' => $data['outlet_id'] ?? null,
                'pricing_tier_id' => $tierId,
                'account_type' => $data['account_type'] ?? 'personal',
                'company_name' => ($data['account_type'] ?? 'personal') === 'business' ? ($data['company_name'] ?? null) : null,
                'company_registration' => ($data['account_type'] ?? 'personal') === 'business' ? ($data['company_registration'] ?? null) : null,
                'representative_role' => ($data['account_type'] ?? 'personal') === 'business' ? ($data['representative_role'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
                'email_group' => $data['email_group'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
        $addresses->syncDefaultAddresses($customer);
        $notifications->customerWelcome($customer->user->fresh(['tenant', 'customer']));
        app(WmsSyncService::class)->syncCustomer($customer->fresh(['tenant', 'user', 'outlet']));

        ActivityLog::record('created', 'customers', 'Created customer '.$data['full_name'].' / '.$customer->mailbox, [
            'customer_id' => $customer->id,
            'mailbox' => $customer->mailbox,
        ]);

        return redirect()->route('courier.customers.index')->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer, CustomerShippingAddressService $addresses, LoyaltyService $loyalty, CourierNotificationService $courierNotifications)
    {
        $this->authorizeTenant($customer);
        $courierNotifications->markRead('wallet_top_up', ['customer_id' => $customer->id]);
        $addresses->syncDefaultAddresses($customer->fresh(['user', 'outlet']));
        $customer->load([
            'user',
            'outlet',
            'packages.status',
            'invoices',
            'shippingAddresses' => fn ($query) => $query->where('is_active', true)->orderBy('display_order')->orderBy('label'),
            'pricingTier',
            'walletTransactions' => fn ($query) => $query->latest()->limit(8),
        ]);
        $customer->loadSum(['invoices as balance_due' => function ($query) {
            $query->whereIn('payment_status', ['unpaid', 'partial']);
        }], 'total_jmd');
        $walletBalance = $loyalty->cashBalance($customer);
        $rewardPoints = $loyalty->pointsBalance($customer);

        return view('courier.customers.show', compact('customer', 'walletBalance', 'rewardPoints'));
    }

    public function downloadBusinessDocument(Customer $customer)
    {
        return $this->downloadRegistrationDocument($customer, 'business_documents', 0);
    }

    public function downloadRegistrationDocument(Customer $customer, string $field, int $index)
    {
        $this->authorizeTenant($customer);

        abort_unless(array_key_exists($field, $this->registrationDocumentLabels()), 404);

        $document = $this->customerRegistrationDocuments($customer)[$field][$index] ?? null;
        $path = is_array($document) ? (string) ($document['path'] ?? '') : (string) $document;
        abort_unless($path !== '' && ! str_contains($path, '..') && str_starts_with(str_replace('\\', '/', $path), 'private/customer-registration/'), 404);

        $absolutePath = storage_path('app/'.$path);
        abort_unless(File::exists($absolutePath), 404);

        $name = is_array($document) ? ($document['original_name'] ?? basename($path)) : ($customer->business_document_original_name ?: basename($path));

        return response()->download($absolutePath, $name);
    }
    public function uploadRegistrationDocuments(Request $request, Customer $customer)
    {
        $this->authorizeTenant($customer);

        $labels = $this->registrationDocumentLabels();
        $data = $request->validate([
            'document_type' => 'required|string|in:'.implode(',', array_keys($labels)),
            'documents' => 'required|array|min:1|max:12',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:10240',
        ]);

        $documents = $this->customerRegistrationDocuments($customer);
        $field = $data['document_type'];
        $documents[$field] = $documents[$field] ?? [];

        foreach ($request->file('documents', []) as $file) {
            $documents[$field][] = $this->storeCustomerRegistrationDocument($file, $customer, $field);
        }

        $customer->forceFill(['registration_documents' => $documents])->save();

        ActivityLog::record('uploaded', 'customer_documents', 'Uploaded '.$labels[$field].' document(s) for '.$customer->mailbox, [
            'customer_id' => $customer->id,
            'document_type' => $field,
            'document_count' => count($request->file('documents', [])),
        ]);

        return back()->with('success', 'Document uploaded to the client account.');
    }


    public function edit(Customer $customer)
    {
        $this->authorizeTenant($customer);
        $outlets = $this->tenantOutlets()->orderBy('name')->get();
        $pricingTiers = PricingTier::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
        $customer->load('user');

        return view('courier.customers.edit', compact('customer', 'outlets', 'pricingTiers'));
    }

    public function update(Request $request, Customer $customer, CustomerShippingAddressService $addresses)
    {
        $this->authorizeTenant($customer);
        $tenantId = auth()->user()->tenant_id;

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'mailbox' => 'required|string|max:100',
            'outlet_id' => 'nullable|exists:outlets,id',
            'address_line1' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'parish' => 'nullable|string|max:100',
            'trn' => 'nullable|string|max:100',
            'password' => 'nullable|string|min:8',
            'notes' => 'nullable|string',
            'email_group' => 'nullable|string|max:100',
            'account_type' => 'nullable|in:personal,business',
            'company_name' => 'nullable|string|max:255',
            'company_registration' => 'nullable|string|max:150',
            'representative_role' => 'nullable|string|max:150',
            'pricing_tier_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $this->ensureNoDuplicateCustomerIdentity($tenantId, $data, $customer->user_id);
        $this->ensureMailboxIsAvailable($tenantId, $data['mailbox'], $customer->id, $customer->user_id);
        $this->ensureTenantOutlet($data['outlet_id'] ?? null);

        DB::transaction(function () use ($data, $customer) {
            $userData = [
                'outlet_id' => $data['outlet_id'] ?? null,
                'mailbox' => $data['mailbox'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'trn' => $data['trn'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'city' => $data['city'] ?? null,
                'parish' => $data['parish'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ];

            if (! empty($data['password'])) {
                $userData['password'] = Hash::make($data['password']);
            }

            $customer->user->update($userData);
            $customer->update([
                'mailbox' => $data['mailbox'],
                'outlet_id' => $data['outlet_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'email_group' => $data['email_group'] ?? null,
                'pricing_tier_id' => ! empty($data['pricing_tier_id']) ? PricingTier::where('tenant_id', $customer->tenant_id)->whereKey($data['pricing_tier_id'])->value('id') : null,
                'account_type' => $data['account_type'] ?? 'personal',
                'company_name' => ($data['account_type'] ?? 'personal') === 'business' ? ($data['company_name'] ?? null) : null,
                'company_registration' => ($data['account_type'] ?? 'personal') === 'business' ? ($data['company_registration'] ?? null) : null,
                'representative_role' => ($data['account_type'] ?? 'personal') === 'business' ? ($data['representative_role'] ?? null) : null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
        $addresses->syncDefaultAddresses($customer->fresh(['user', 'outlet']));
        app(WmsSyncService::class)->syncCustomer($customer->fresh(['tenant', 'user', 'outlet']));

        ActivityLog::record('updated', 'customers', 'Updated customer '.$data['full_name'].' / '.$data['mailbox'], [
            'customer_id' => $customer->id,
            'mailbox' => $customer->mailbox,
        ]);

        return redirect()->route('courier.customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorizeTenant($customer);
        $mailbox = $customer->mailbox;
        $customer->user()->delete();
        ActivityLog::record('deleted', 'customers', 'Deleted customer '.$mailbox);
        return redirect()->route('courier.customers.index')->with('success', 'Customer deleted successfully.');
    }

    public function sendPasswordReset(Customer $customer, CustomerNotificationService $notifications)
    {
        $this->authorizeTenant($customer);
        $customer->loadMissing('user');

        $user = $customer->user;
        abort_if(! $user || ! $user->email, 422);

        $token = Password::broker()->createToken($user);
        $sent = $notifications->passwordReset($user->fresh(['tenant']), $token);

        ActivityLog::record($sent ? 'sent' : 'failed', 'customers', 'Sent password reset email to '.$customer->mailbox, [
            'customer_id' => $customer->id,
            'email' => $user->email,
        ]);

        if (! $sent) {
            return back()->withErrors(['password_reset' => 'The password reset email could not be sent. Check the email log and mail settings.']);
        }

        return back()->with('success', 'Password reset email sent to client.');
    }

    public function resendWelcomeEmail(Customer $customer, CustomerNotificationService $notifications)
    {
        $this->authorizeTenant($customer);
        $customer->loadMissing('user');

        $user = $customer->user;
        abort_if(! $user || ! $user->email, 422);

        $sent = $notifications->customerWelcome($user->fresh(['tenant', 'customer']));

        ActivityLog::record($sent ? 'sent' : 'failed', 'customers', 'Resent welcome email to '.$customer->mailbox, [
            'customer_id' => $customer->id,
            'email' => $user->email,
        ]);

        if (! $sent) {
            return back()->withErrors(['welcome_email' => 'The welcome email could not be sent. Check the email log and mail settings.']);
        }

        return back()->with('success', 'Welcome email sent to client.');
    }

    public function creditWallet(Request $request, Customer $customer, LoyaltyService $loyalty)
    {
        $this->authorizeTenant($customer);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($customer, $data, $loyalty) {
            $loyalty->creditCash(
                $customer,
                (float) $data['amount'],
                'manual',
                null,
                $data['description'] ?? 'Courier wallet credit'
            );
        });

        ActivityLog::record('credited', 'customer_wallet', 'Credited customer wallet '.$customer->mailbox, [
            'customer_id' => $customer->id,
            'amount' => (float) $data['amount'],
        ]);

        return back()->with('success', 'Customer wallet credited.');
    }

    public function setWalletBalance(Request $request, Customer $customer, LoyaltyService $loyalty)
    {
        $this->authorizeTenant($customer);

        $data = $request->validate([
            'balance' => 'required|numeric|min:0',
            'description' => 'required|string|max:1000',
        ]);

        $targetBalance = round((float) $data['balance'], 2);
        $adjustment = DB::transaction(function () use ($customer, $loyalty, $targetBalance, $data) {
            $locked = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = round($loyalty->cashBalance($locked), 2);
            $difference = round($targetBalance - $balanceBefore, 2);

            if (abs($difference) < 0.01) {
                return [
                    'changed' => false,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $targetBalance,
                    'difference' => 0.0,
                ];
            }

            WalletTransaction::create([
                'tenant_id' => $locked->tenant_id,
                'customer_id' => $locked->id,
                'transaction_type' => $difference > 0 ? 'credit' : 'debit',
                'source_type' => 'manual',
                'reference_id' => null,
                'amount' => abs($difference),
                'balance_before' => $balanceBefore,
                'balance_after' => $targetBalance,
                'description' => 'Courier wallet balance adjustment: '.$data['description'],
                'status' => 'approved',
                'payment_method' => 'courier_adjustment',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return [
                'changed' => true,
                'balance_before' => $balanceBefore,
                'balance_after' => $targetBalance,
                'difference' => $difference,
            ];
        });

        if (! $adjustment['changed']) {
            return back()->with('success', 'Customer wallet balance is already JMD '.number_format($targetBalance, 2).'.');
        }

        ActivityLog::record('adjusted', 'customer_wallet', 'Adjusted customer wallet '.$customer->mailbox, [
            'customer_id' => $customer->id,
            'balance_before' => $adjustment['balance_before'],
            'balance_after' => $adjustment['balance_after'],
            'difference' => $adjustment['difference'],
        ]);

        return back()->with('success', 'Customer wallet balance set to JMD '.number_format($targetBalance, 2).'.');
    }
    public function approveWalletTopUp(Customer $customer, WalletTransaction $transaction, LoyaltyService $loyalty)
    {
        $this->authorizeTenant($customer);
        abort_if((int) $transaction->customer_id !== (int) $customer->id, 404);

        DB::transaction(function () use ($customer, $transaction, $loyalty) {
            $locked = WalletTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending' || ! $locked->payment_method) {
                throw ValidationException::withMessages(['wallet' => 'This top-up is no longer pending approval.']);
            }

            $pointValue = (float) TenantSetting::getValue($customer->tenant_id, 'loyalty_point_value_jmd', 1);
            if ($pointValue <= 0) {
                throw ValidationException::withMessages(['wallet' => 'Set a valid Point Value JMD in Loyalty settings before approving top-ups.']);
            }

            $lockedCustomer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $points = round((float) $locked->amount / $pointValue, 2);
            $pointsBefore = $loyalty->pointsBalance($lockedCustomer);

            WalletTransaction::create([
                'tenant_id' => $lockedCustomer->tenant_id,
                'customer_id' => $lockedCustomer->id,
                'transaction_type' => 'credit',
                'source_type' => 'manual',
                'reference_id' => $locked->id,
                'amount' => $points,
                'balance_before' => $pointsBefore,
                'balance_after' => round($pointsBefore + $points, 2),
                'description' => 'Points from approved wallet top-up #'.$locked->id,
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            $lockedCustomer->increment('total_points', $points);
            $locked->update([
                'converted_to_points' => true,
                'points_awarded' => $points,
                'point_value_jmd' => $pointValue,
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        return back()->with('success', 'Customer top-up approved and converted to reward points.');
    }

    public function rejectWalletTopUp(Customer $customer, WalletTransaction $transaction)
    {
        $this->authorizeTenant($customer);
        abort_if((int) $transaction->customer_id !== (int) $customer->id, 404);
        abort_unless($transaction->status === 'pending' && $transaction->payment_method, 422);

        $transaction->update(['status' => 'cancelled']);

        return back()->with('success', 'Customer wallet top-up rejected.');
    }

    public function walletTopUpReceipt(Customer $customer, WalletTransaction $transaction)
    {
        $this->authorizeTenant($customer);
        abort_if((int) $transaction->tenant_id !== (int) $customer->tenant_id, 404);
        abort_if((int) $transaction->customer_id !== (int) $customer->id, 404);
        abort_if(! $transaction->receipt_path || ! Storage::disk('public')->exists($transaction->receipt_path), 404);

        $path = Storage::disk('public')->path($transaction->receipt_path);
        $name = $transaction->receipt_original_name ?: basename($transaction->receipt_path);
        $mime = Storage::disk('public')->mimeType($transaction->receipt_path);

        return response()->file($path, [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $name).'"',
        ]);
    }

    private function authorizeTenant(Customer $customer): void
    {
        abort_if($customer->tenant_id !== auth()->user()->tenant_id, 404);
        abort_if(auth()->user()->user_type === 'staff' && auth()->user()->outlet_id && $customer->outlet_id !== auth()->user()->outlet_id, 404);
    }

    private function customerRegistrationDocuments(Customer $customer): array
    {
        $documents = is_array($customer->registration_documents) ? $customer->registration_documents : [];

        if ($customer->business_document_path && empty($documents['business_documents'])) {
            $documents['business_documents'][] = [
                'path' => $customer->business_document_path,
                'original_name' => $customer->business_document_original_name ?: basename($customer->business_document_path),
                'mime_type' => $customer->business_document_mime_type,
            ];
        }

        return $documents;
    }

    private function registrationDocumentLabels(): array
    {
        return [
            'identification_documents' => 'Identification',
            'proof_of_address_documents' => 'Proof of address',
            'trn_documents' => 'TRN',
            'business_documents' => 'Business registration',
            'authorization_documents' => 'Authorization',
            'other_documents' => 'Other',
        ];
    }
    private function tenantCustomers()
    {
        $query = Customer::where('tenant_id', auth()->user()->tenant_id);

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $query->where('outlet_id', auth()->user()->outlet_id);
        }

        return $query;
    }

    private function filteredCustomers(Request $request)
    {
        return $this->tenantCustomers()
            ->with(['user', 'outlet'])
            ->withSum(['invoices as balance_due' => function ($query) {
                $query->whereIn('payment_status', ['unpaid', 'partial']);
            }], 'total_jmd')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('q')).'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('mailbox', 'like', $term)
                        ->orWhereHas('user', function ($user) use ($term) {
                            $user->where('full_name', 'like', $term)
                                ->orWhere('email', 'like', $term)
                                ->orWhere('phone', 'like', $term);
                        });
                });
            })
            ->when($request->filled('outlet_id'), fn ($query) => $query->where('outlet_id', $request->integer('outlet_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'));
    }

    private function tenantOutlets()
    {
        $query = Outlet::where('tenant_id', auth()->user()->tenant_id);

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $query->where('id', auth()->user()->outlet_id);
        }

        return $query;
    }

    private function ensureTenantOutlet(?int $outletId): void
    {
        if (! $outletId) {
            return;
        }

        abort_unless($this->tenantOutlets()->whereKey($outletId)->exists(), 422, 'Selected outlet is not available.');
    }

    private function ensureMailboxIsAvailable(int $tenantId, ?string $mailbox, ?int $exceptCustomerId = null, ?int $exceptUserId = null): void
    {
        $mailbox = Customer::normalizeMailbox($mailbox);
        if ($mailbox === null) {
            return;
        }

        $customerExists = Customer::where('tenant_id', $tenantId)
            ->when($exceptCustomerId, fn ($query) => $query->whereKeyNot($exceptCustomerId))
            ->whereNormalizedMailbox($mailbox)
            ->exists();
        $userExists = User::where('tenant_id', $tenantId)
            ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->where('mailbox', $mailbox)
            ->exists();

        if ($customerExists || $userExists) {
            throw ValidationException::withMessages([
                'mailbox' => 'This mailbox number is already assigned under this courier. Choose another mailbox or leave it blank to auto-generate the next available one.',
            ]);
        }
    }

    private function ensureNoDuplicateCustomerIdentity(int $tenantId, array $data, ?int $exceptUserId = null): void
    {
        $checks = [
            'trn' => 'A customer with this TRN/SSN already exists under this courier.',
            'email' => 'A customer with this email address already exists under this courier.',
            'phone' => 'A customer with this telephone number already exists under this courier.',
        ];

        $errors = [];
        foreach ($checks as $field => $message) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            $exists = User::where('tenant_id', $tenantId)
                ->where('user_type', 'customer')
                ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
                ->where($field, $value)
                ->exists();

            if ($exists) {
                $errors[$field] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}










