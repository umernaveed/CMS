<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CourierSupportAccessCode;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\PricingTier;
use App\Models\SystemNotification;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\WeightRate;
use App\Services\CustomerShippingAddressService;
use App\Services\EmailTemplateService;
use App\Services\TenantMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Throwable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourierSettingsController extends Controller
{
    public function index(EmailTemplateService $emailTemplatesService)
    {
        $tenantId = auth()->user()->tenant_id;
        if (! auth()->user()->security_code_hash) {
            $plainCode = Str::upper(Str::random(8));
            auth()->user()->forceFill([
                'security_code_hash' => Hash::make($plainCode),
                'security_code_regenerated_at' => now(),
                'security_code_reusable' => false,
            ])->save();
            session()->flash('fresh_security_code', $plainCode);
        }
        $this->ensureDefaultTiers($tenantId);
        $settings = TenantSetting::where('tenant_id', $tenantId)->pluck('value', 'key');
        $warehouseAddresses = $this->warehouseAddressesForForm($settings);
        $tiers = PricingTier::where('tenant_id', $tenantId)->orderBy('min_packages_collected')->get();
        $notifications = SystemNotification::where('tenant_id', $tenantId)->latest()->paginate($this->perPage(request(), 8));
        $emailTemplates = $emailTemplatesService->templatesForTenant($tenantId);
        $supportCodes = CourierSupportAccessCode::where('tenant_id', $tenantId)->latest()->limit(10)->get();
        $supportPermissions = Permission::whereIn('name', [
            'view_dashboard',
            'manage_customers',
            'manage_authorized_users',
            'manage_crm_emails',
            'manage_tickets',
            'manage_packages',
            'manage_pre_alerts',
            'manage_purchase_requests',
            'manage_package_exceptions',
            'manage_warehouse',
            'manage_invoices',
            'manage_payments',
            'manage_transactions',
            'manage_billing',
            'view_reports',
            'manage_delivery',
            'manage_manifests',
            'manage_activity_logs',
        ])->orderBy('module')->orderBy('display_name')->get();

        return view('courier.settings.index', compact('settings', 'warehouseAddresses', 'tiers', 'notifications', 'emailTemplates', 'supportCodes', 'supportPermissions'));
    }

    public function update(Request $request, CustomerShippingAddressService $shippingAddresses, EmailTemplateService $emailTemplatesService, TenantMailService $mail)
    {
        $data = $request->validate([
            'us_warehouse_address_1' => 'nullable|string|max:255',
            'us_warehouse_city' => 'nullable|string|max:150',
            'us_warehouse_state' => 'nullable|string|max:150',
            'us_warehouse_zip' => 'nullable|string|max:50',
            'china_entrance_no' => 'nullable|string|max:255',
            'china_province' => 'nullable|string|max:150',
            'china_city' => 'nullable|string|max:150',
            'china_detailed_address' => 'nullable|string',
            'china_number' => 'nullable|string|max:100',
            'china_zip' => 'nullable|string|max:50',
            'banking_details' => 'nullable|string',
            'storage_free_days' => 'nullable|integer|min:0',
            'storage_flat_fee_jmd' => 'nullable|numeric|min:0',
            'storage_daily_increment_jmd' => 'nullable|numeric|min:0',
            'processing_fee_jmd' => 'nullable|numeric|min:0',
            'billing_weight_mode' => 'nullable|in:actual,round_up,round_down',
            'loyalty_enabled' => 'nullable|boolean',
            'loyalty_package_points' => 'nullable|numeric|min:0',
            'loyalty_invoice_points_per_amount' => 'nullable|numeric|min:0',
            'loyalty_invoice_amount_step_jmd' => 'nullable|numeric|min:0.01',
            'loyalty_signup_points' => 'nullable|numeric|min:0',
            'loyalty_referral_points' => 'nullable|numeric|min:0',
            'referral_points_minimum_to_apply' => 'nullable|numeric|min:0',
            'loyalty_point_value_jmd' => 'nullable|numeric|min:0',
            'dime_api_key' => 'nullable|string|max:500',
            'paypal_client_id' => 'nullable|string|max:500',
            'paypal_secret' => 'nullable|string|max:500',
            'amber_pay_api_key' => 'nullable|string|max:500',
            'email_footer_html' => 'nullable|string',
            'email_footer_text' => 'nullable|string|max:1000',
            'email_attachment_url' => 'nullable|url|max:1000',
            'sound_prompts_enabled' => 'nullable|boolean',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|in:none,tls,ssl',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:500',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
            'email_templates' => 'nullable|array',
            'email_templates.*.enabled' => 'nullable|boolean',
            'email_templates.*.subject' => 'nullable|string|max:255',
            'email_templates.*.headline' => 'nullable|string|max:255',
            'email_templates.*.body' => 'nullable|string|max:50000',
            'email_templates.*.button_label' => 'nullable|string|max:100',
            'email_templates.*.button_url' => 'nullable|string|max:1000',
            'email_templates.*.attachment_urls' => 'nullable|string|max:5000',
            'email_templates.*.existing_attachments' => 'nullable|array',
            'email_template_attachments.*' => 'nullable|array',
            'email_template_attachments.*.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp|max:8192',
            'mailbox_prefix' => 'nullable|string|max:20',
            'hawb_prefix' => 'nullable|string|max:20',
            'brand_primary_color' => 'nullable|string|max:20',
            'brand_secondary_color' => 'nullable|string|max:20',
            'invoice_logo_upload' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'tenant_logo_upload' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'login_kicker' => 'nullable|string|max:120',
            'login_headline' => 'nullable|string|max:160',
            'login_intro_text' => 'nullable|string|max:500',
            'login_feature_1' => 'nullable|string|max:120',
            'login_feature_2' => 'nullable|string|max:120',
            'login_feature_3' => 'nullable|string|max:120',
            'login_feature_icon_1' => 'nullable|string|max:80',
            'login_feature_icon_2' => 'nullable|string|max:80',
            'login_feature_icon_3' => 'nullable|string|max:80',
            'login_panel_image_url' => 'nullable|string|max:1000',
            'login_background_url' => 'nullable|string|max:1000',
            'login_panel_image_upload' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'login_background_upload' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'warehouse_addresses' => 'nullable|array',
            'warehouse_addresses.*.label' => 'nullable|string|max:100',
            'warehouse_addresses.*.country_code' => 'nullable|string|max:10',
            'warehouse_addresses.*.flag_icon' => 'nullable|string|max:20',
            'warehouse_addresses.*.flag_image' => 'nullable|string|max:500',
            'warehouse_flag_uploads' => 'nullable|array',
            'warehouse_flag_uploads.*' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
            'warehouse_addresses.*.freight_type' => 'nullable|string|max:50',
            'warehouse_addresses.*.country' => 'nullable|string|max:100',
            'warehouse_addresses.*.name_suffix' => 'nullable|string|max:80',
            'warehouse_addresses.*.address_1' => 'nullable|string|max:255',
            'warehouse_addresses.*.address_2_suffix' => 'nullable|string|max:80',
            'warehouse_addresses.*.city' => 'nullable|string|max:150',
            'warehouse_addresses.*.state' => 'nullable|string|max:150',
            'warehouse_addresses.*.province' => 'nullable|string|max:150',
            'warehouse_addresses.*.zip_code' => 'nullable|string|max:50',
            'warehouse_addresses.*.entrance_no' => 'nullable|string|max:255',
            'warehouse_addresses.*.detailed_address' => 'nullable|string',
            'warehouse_addresses.*.number' => 'nullable|string|max:100',
            'warehouse_addresses.*.marks_suffix' => 'nullable|string|max:80',
            'warehouse_addresses.*.note' => 'nullable|string',
            'warehouse_addresses.*.is_active' => 'nullable|boolean',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $data['loyalty_enabled'] = $request->boolean('loyalty_enabled') ? 1 : 0;
        if ($request->has('sound_prompts_enabled')) {
            $data['sound_prompts_enabled'] = $request->boolean('sound_prompts_enabled') ? 1 : 0;
        }

        if ($request->hasFile('tenant_logo_upload')) {
            Tenant::whereKey($tenantId)->update([
                'logo' => $this->storeTenantLogo(
                    $request,
                    $tenantId,
                    optional(auth()->user()->tenant)->logo
                ),
            ]);
        }

        unset($data['tenant_logo_upload']);

        if ($request->hasFile('login_panel_image_upload')) {
            $data['login_panel_image_url'] = $this->storeTenantLoginImage($request, $tenantId, 'login_panel_image_upload', $data['login_panel_image_url'] ?? null);
        }

        unset($data['login_panel_image_upload']);

        if ($request->hasFile('login_background_upload')) {
            $data['login_background_url'] = $this->storeTenantLoginImage($request, $tenantId, 'login_background_upload', $data['login_background_url'] ?? null);
        }

        unset($data['login_background_upload']);

        if ($request->hasFile('invoice_logo_upload')) {
            $data['invoice_logo'] = $this->storeInvoiceLogo(
                $request,
                $tenantId,
                TenantSetting::getValue($tenantId, 'invoice_logo')
            );
        }

        unset($data['invoice_logo_upload']);

        if (array_key_exists('mailbox_prefix', $data)) {
            $data['mailbox_prefix'] = $data['mailbox_prefix']
                ? strtoupper(trim($data['mailbox_prefix']))
                : null;
        }
        if (array_key_exists('hawb_prefix', $data)) {
            $data['hawb_prefix'] = $data['hawb_prefix']
                ? strtoupper(trim(preg_replace('/[^A-Z0-9-]/i', '', $data['hawb_prefix'])))
                : null;
        }
        foreach (['brand_primary_color', 'brand_secondary_color'] as $colorKey) {
            if (array_key_exists($colorKey, $data)) {
                $color = trim((string) $data[$colorKey]);
                $data[$colorKey] = preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : null;
            }
        }

        if (array_key_exists('warehouse_addresses', $data)) {
            $addresses = $this->storeWarehouseFlagImages(
                $request,
                $tenantId,
                $data['warehouse_addresses']
            );
            $data['warehouse_addresses'] = json_encode($this->normalizeWarehouseAddressSettings($addresses), JSON_UNESCAPED_UNICODE);
        }
        unset($data['warehouse_flag_uploads']);

        if (array_key_exists('email_templates', $data)) {
            $data['email_templates'] = $this->prepareEmailTemplates($request, $tenantId, $data['email_templates']);
            $emailTemplatesService->saveTemplates($tenantId, $data['email_templates']);
            unset($data['email_templates']);
        }

        if (blank($data['smtp_password'] ?? null)) {
            unset($data['smtp_password']);
        } else {
            $data['smtp_password'] = 'encrypted:'.encrypt($data['smtp_password']);
        }

        if ($request->input('settings_action') === 'test_smtp') {
            return $this->testAndSaveSmtpSettings($data, $tenantId, $mail);
        }

        if ($this->smtpSettingsChanged($data, $tenantId)) {
            $data['smtp_status'] = filled($data['smtp_host'] ?? null) ? 'untested' : null;
            $data['smtp_last_error'] = null;
        }

        foreach ($data as $key => $value) {
            TenantSetting::putValue($tenantId, $key, $value);
        }

        Customer::where('tenant_id', $tenantId)->with(['user', 'outlet'])->chunkById(100, function ($customers) use ($shippingAddresses) {
            foreach ($customers as $customer) {
                $shippingAddresses->syncDefaultAddresses($customer);
            }
        });

        ActivityLog::record('updated', 'settings', 'Updated courier settings');

        return back()->with('success', 'Settings updated successfully.');
    }

    private function testAndSaveSmtpSettings(array $data, int $tenantId, TenantMailService $mail)
    {
        $settings = $this->smtpSettingsForTest($data, $tenantId);
        $to = auth()->user()->email ?: optional(auth()->user()->tenant)->email;

        if (! $to) {
            return back()->withErrors(['smtp_test' => 'Your account needs an email address before sending an SMTP test.'])->withInput();
        }

        try {
            $mail->testSmtpSettings($tenantId, $settings, $to);
        } catch (Throwable $exception) {
            TenantSetting::putValue($tenantId, 'smtp_status', 'failed');
            TenantSetting::putValue($tenantId, 'smtp_last_tested_at', now()->toDateTimeString());
            TenantSetting::putValue($tenantId, 'smtp_last_error', Str::limit($exception->getMessage(), 1000, ''));

            ActivityLog::record('failed', 'settings', 'Courier SMTP test failed', [
                'smtp_host' => $settings['smtp_host'] ?? null,
                'test_to' => $to,
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors(['smtp_test' => 'SMTP test failed: '.$exception->getMessage()])->withInput();
        }

        foreach ([
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_password',
            'mail_from_address',
            'mail_from_name',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                TenantSetting::putValue($tenantId, $key, $data[$key]);
            }
        }

        $usingCourierSmtp = filled($data['smtp_host'] ?? null);
        TenantSetting::putValue($tenantId, 'smtp_status', $usingCourierSmtp ? 'active' : null);
        TenantSetting::putValue($tenantId, 'smtp_last_tested_at', now()->toDateTimeString());
        TenantSetting::putValue($tenantId, 'smtp_last_error', null);

        ActivityLog::record('tested', 'settings', 'Courier SMTP test email sent', [
            'smtp_host' => $settings['smtp_host'] ?? null,
            'test_to' => $to,
        ]);

        return back()->with('success', $usingCourierSmtp
            ? 'SMTP test email sent successfully. Courier SMTP settings are active.'
            : 'SMTP test email sent successfully using System Admin defaults.');
    }

    private function smtpSettingsForTest(array $data, int $tenantId): array
    {
        $existingPassword = $this->storedSmtpPassword(TenantSetting::getValue($tenantId, 'smtp_password'));

        return [
            'smtp_host' => array_key_exists('smtp_host', $data) ? $data['smtp_host'] : TenantSetting::getValue($tenantId, 'smtp_host'),
            'smtp_port' => array_key_exists('smtp_port', $data) ? $data['smtp_port'] : TenantSetting::getValue($tenantId, 'smtp_port'),
            'smtp_encryption' => array_key_exists('smtp_encryption', $data) ? $data['smtp_encryption'] : TenantSetting::getValue($tenantId, 'smtp_encryption'),
            'smtp_username' => array_key_exists('smtp_username', $data) ? $data['smtp_username'] : TenantSetting::getValue($tenantId, 'smtp_username'),
            'smtp_password' => array_key_exists('smtp_password', $data) ? $this->storedSmtpPassword($data['smtp_password']) : $existingPassword,
            'mail_from_address' => array_key_exists('mail_from_address', $data) ? $data['mail_from_address'] : TenantSetting::getValue($tenantId, 'mail_from_address'),
            'mail_from_name' => array_key_exists('mail_from_name', $data) ? $data['mail_from_name'] : TenantSetting::getValue($tenantId, 'mail_from_name'),
        ];
    }

    private function smtpSettingsChanged(array $data, int $tenantId): bool
    {
        foreach (['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'mail_from_address', 'mail_from_name'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $incoming = trim((string) $data[$key]);
            $existing = trim((string) TenantSetting::getValue($tenantId, $key));
            if ($incoming !== $existing) {
                return true;
            }
        }

        return array_key_exists('smtp_password', $data);
    }

    private function storedSmtpPassword($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (! str_starts_with($value, 'encrypted:')) {
            return $value;
        }

        try {
            return decrypt(substr($value, strlen('encrypted:')));
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function storeTenantLoginImage(Request $request, int $tenantId, string $field, ?string $currentPath = null): string
    {
        $file = $request->file($field);
        $directory = public_path('uploads/tenant-login');

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = 'tenant-'.$tenantId.'-'.Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        if ($currentPath && ! Str::startsWith($currentPath, ['http://', 'https://'])) {
            $oldPath = public_path(ltrim($currentPath, '/'));
            if (Str::startsWith($oldPath, $directory) && File::isFile($oldPath)) {
                File::delete($oldPath);
            }
        }

        return 'uploads/tenant-login/'.$filename;
    }

    private function storeTenantLogo(Request $request, int $tenantId, ?string $currentPath = null): string
    {
        $file = $request->file('tenant_logo_upload');
        $directory = public_path('uploads/tenant-logos');

        File::ensureDirectoryExists($directory);

        $filename = 'tenant-'.$tenantId.'-'.Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        if ($currentPath && ! Str::startsWith($currentPath, ['http://', 'https://'])) {
            $oldPath = public_path(ltrim($currentPath, '/'));
            if (Str::startsWith($oldPath, $directory) && File::isFile($oldPath)) {
                File::delete($oldPath);
            }
        }

        return 'uploads/tenant-logos/'.$filename;
    }

    private function storeInvoiceLogo(Request $request, int $tenantId, ?string $currentPath = null): string
    {
        $file = $request->file('invoice_logo_upload');
        $directory = public_path('uploads/tenant-invoice-logos');

        File::ensureDirectoryExists($directory);

        $filename = 'tenant-'.$tenantId.'-'.Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        if ($currentPath && ! Str::startsWith($currentPath, ['http://', 'https://'])) {
            $oldPath = public_path(ltrim($currentPath, '/'));
            if (Str::startsWith($oldPath, $directory) && File::isFile($oldPath)) {
                File::delete($oldPath);
            }
        }

        return 'uploads/tenant-invoice-logos/'.$filename;
    }

    private function storeWarehouseFlagImages(Request $request, int $tenantId, array $addresses): array
    {
        foreach ($addresses as $index => &$address) {
            $file = $request->file("warehouse_flag_uploads.$index");
            if (! $file) {
                continue;
            }

            $directory = public_path('uploads/warehouse-flags/'.$tenantId);
            File::ensureDirectoryExists($directory);
            $filename = 'flag-'.Str::uuid().'.'.$file->getClientOriginalExtension();
            $file->move($directory, $filename);

            $currentPath = trim((string) ($address['flag_image'] ?? ''));
            if ($currentPath !== '' && ! Str::startsWith($currentPath, ['http://', 'https://'])) {
                $oldPath = public_path(ltrim($currentPath, '/'));
                if (Str::startsWith($oldPath, $directory) && File::isFile($oldPath)) {
                    File::delete($oldPath);
                }
            }

            $address['flag_image'] = 'uploads/warehouse-flags/'.$tenantId.'/'.$filename;
        }
        unset($address);

        return $addresses;
    }

    private function prepareEmailTemplates(Request $request, int $tenantId, array $templates): array
    {
        foreach ($templates as $key => $template) {
            $attachments = collect($template['existing_attachments'] ?? [])
                ->filter(fn ($attachment) => ! empty($attachment['keep']))
                ->filter(fn ($attachment) => filled($attachment['url'] ?? null) || filled($attachment['path'] ?? null))
                ->map(fn ($attachment) => [
                    'name' => trim((string) ($attachment['name'] ?? 'Attachment')),
                    'url' => trim((string) ($attachment['url'] ?? '')),
                    'path' => trim((string) ($attachment['path'] ?? '')),
                ]);

            foreach (preg_split('/\r\n|\r|\n/', (string) ($template['attachment_urls'] ?? '')) as $url) {
                $url = trim($url);
                if ($url !== '') {
                    $attachments->push([
                        'name' => basename(parse_url($url, PHP_URL_PATH) ?: 'Download'),
                        'url' => $url,
                        'path' => '',
                    ]);
                }
            }

            foreach ((array) $request->file("email_template_attachments.$key", []) as $file) {
                if (! $file) {
                    continue;
                }

                $directory = public_path('uploads/email-template-attachments/'.$tenantId);
                File::ensureDirectoryExists($directory);
                $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::uuid().'.'.$file->getClientOriginalExtension();
                $file->move($directory, $filename);
                $attachments->push([
                    'name' => $file->getClientOriginalName(),
                    'url' => asset('uploads/email-template-attachments/'.$tenantId.'/'.$filename),
                    'path' => 'uploads/email-template-attachments/'.$tenantId.'/'.$filename,
                ]);
            }

            $templates[$key]['attachments'] = $attachments->values()->all();
            unset($templates[$key]['attachment_urls'], $templates[$key]['existing_attachments']);
        }

        return $templates;
    }

    public function storeTier(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'min_packages_collected' => 'required|integer|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $tenantId = auth()->user()->tenant_id;
        if (! empty($data['is_default'])) {
            PricingTier::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        PricingTier::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'min_packages_collected' => $data['min_packages_collected'],
            'discount_percent' => $data['discount_percent'] ?? 0,
            'is_default' => ! empty($data['is_default']),
            'is_active' => ! empty($data['is_active']),
        ]);

        ActivityLog::record('created', 'pricing_tiers', 'Created pricing tier '.$data['name']);

        return back()->with('success', 'Pricing tier saved.');
    }

    public function importRates(Request $request, PricingTier $tier)
    {
        abort_if($tier->tenant_id !== auth()->user()->tenant_id, 404);

        $request->validate(['rate_file' => 'required|file|mimes:csv,txt|max:4096']);
        $handle = fopen($request->file('rate_file')->getRealPath(), 'r');
        $header = array_map(fn ($column) => $this->normalizeRateHeader($column), fgetcsv($handle) ?: []);
        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $record = $this->combineRateCsvRow($header, $row);
            if (! $record) {
                $skipped++;
                continue;
            }

            WeightRate::create([
                'tenant_id' => auth()->user()->tenant_id,
                'pricing_tier_id' => $tier->id,
                'freight_type' => strtolower(trim((string) ($record['freight_type'] ?? 'air'))) ?: 'air',
                'min_weight' => $this->decimalFromCsv($record['min_weight'] ?? 0),
                'max_weight' => $this->nullableDecimalFromCsv($record['max_weight'] ?? null),
                'price_jmd' => $this->decimalFromCsv($record['price_jmd'] ?? $record['regular_price'] ?? $record['price'] ?? 0),
                'express_price_jmd' => $this->decimalFromCsv($record['express_price_jmd'] ?? $record['express_price'] ?? 0),
                'is_active' => true,
            ]);
            $created++;
        }
        fclose($handle);

        ActivityLog::record('imported', 'weights', 'Imported '.$created.' rate rows for '.$tier->name.'; skipped '.$skipped);

        return back()->with('success', 'Imported '.$created.' rate rows. Skipped '.$skipped.' rows.');
    }

    public function ratesTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['freight_type', 'min_weight', 'max_weight', 'price_jmd', 'express_price_jmd']);
            fputcsv($handle, ['air', '0.01', '1.00', '$650.00', '$800.00']);
            fputcsv($handle, ['air', '5.01', '', '$450.00', '$550.00']);
            fputcsv($handle, ['sea', '1.01', '5.00', '$2,500.00', '$3,000.00']);
            fclose($handle);
        }, 'loyalty-tier-rates-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function updateTier(Request $request, PricingTier $tier)
    {
        abort_if($tier->tenant_id !== auth()->user()->tenant_id, 404);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'min_packages_collected' => 'required|integer|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $tenantId = auth()->user()->tenant_id;
        if (! empty($data['is_default'])) {
            PricingTier::where('tenant_id', $tenantId)->where('id', '<>', $tier->id)->update(['is_default' => false]);
        }

        $tier->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'min_packages_collected' => $data['min_packages_collected'],
            'discount_percent' => $data['discount_percent'] ?? 0,
            'is_default' => ! empty($data['is_default']),
            'is_active' => ! empty($data['is_active']),
        ]);

        ActivityLog::record('updated', 'pricing_tiers', 'Updated pricing tier '.$tier->name);

        return back()->with('success', 'Pricing tier updated.');
    }

    public function destroyTier(PricingTier $tier)
    {
        abort_if($tier->tenant_id !== auth()->user()->tenant_id, 404);

        if ($tier->is_default) {
            return back()->withErrors(['tier' => 'Set another tier as default before deleting this one.']);
        }

        if (Customer::where('tenant_id', $tier->tenant_id)->where('pricing_tier_id', $tier->id)->exists()) {
            return back()->withErrors(['tier' => 'This tier is assigned to customers. Move those customers to another tier first.']);
        }

        $name = $tier->name;
        WeightRate::where('tenant_id', $tier->tenant_id)->where('pricing_tier_id', $tier->id)->delete();
        $tier->delete();

        ActivityLog::record('deleted', 'pricing_tiers', 'Deleted pricing tier '.$name);

        return back()->with('success', 'Pricing tier deleted.');
    }

    public function storeNotification(Request $request)
    {
        $data = $this->notificationData($request);

        SystemNotification::create($data + [
            'tenant_id' => auth()->user()->tenant_id,
            'is_active' => ! empty($data['is_active']),
        ]);

        ActivityLog::record('created', 'notifications', 'Created login alert '.$data['title']);

        return back()->with('success', 'Login alert created.');
    }

    public function updateNotification(Request $request, SystemNotification $notification)
    {
        abort_if((int) $notification->tenant_id !== (int) auth()->user()->tenant_id, 404);

        $data = $this->notificationData($request);
        $notification->update($data + [
            'is_active' => ! empty($data['is_active']),
        ]);

        ActivityLog::record('updated', 'notifications', 'Updated login alert '.$notification->title);

        return back()->with('success', 'Login alert updated.');
    }

    public function destroyNotification(SystemNotification $notification)
    {
        abort_if((int) $notification->tenant_id !== (int) auth()->user()->tenant_id, 404);

        $title = $notification->title;
        $notification->delete();

        ActivityLog::record('deleted', 'notifications', 'Deleted login alert '.$title);

        return back()->with('success', 'Login alert deleted.');
    }

    private function notificationData(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:low,medium,high',
            'audience' => 'required|in:all,courier,customer',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
        ]);

        $data['message'] = SystemNotification::sanitizeMessage($data['message']);

        return $data;
    }

    private function ensureDefaultTiers(int $tenantId): void
    {
        foreach ([
            ['name' => 'Regular', 'min' => 0, 'discount' => 0, 'default' => true],
            ['name' => 'Silver', 'min' => 25, 'discount' => 2.5, 'default' => false],
            ['name' => 'Gold', 'min' => 75, 'discount' => 5, 'default' => false],
        ] as $tier) {
            PricingTier::firstOrCreate([
                'tenant_id' => $tenantId,
                'slug' => Str::slug($tier['name']),
            ], [
                'name' => $tier['name'],
                'min_packages_collected' => $tier['min'],
                'discount_percent' => $tier['discount'],
                'is_default' => $tier['default'],
                'is_active' => true,
            ]);
        }
    }

    private function warehouseAddressesForForm($settings): array
    {
        $configured = $settings['warehouse_addresses'] ?? null;
        if ($configured) {
            $decoded = json_decode($configured, true);
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }

        return [
            [
                'label' => 'USA Air',
                'country_code' => 'US',
                'flag_icon' => '🇺🇸',
                'freight_type' => 'air',
                'country' => 'United States',
                'name_suffix' => 'OLOG',
                'address_1' => $settings['us_warehouse_address_1'] ?? '1807 SW 31st Ave',
                'address_2_suffix' => '',
                'city' => $settings['us_warehouse_city'] ?? 'Hallandale',
                'state' => $settings['us_warehouse_state'] ?? 'Florida',
                'zip_code' => $settings['us_warehouse_zip'] ?? '33009',
                'marks_suffix' => 'AIR',
                'is_active' => true,
            ],
            [
                'label' => 'USA Sea',
                'country_code' => 'US',
                'flag_icon' => '🇺🇸',
                'freight_type' => 'sea',
                'country' => 'United States',
                'name_suffix' => 'OLOG',
                'address_1' => $settings['us_warehouse_address_1'] ?? '1807 SW 31st Ave',
                'address_2_suffix' => 'SEA',
                'city' => $settings['us_warehouse_city'] ?? 'Hallandale',
                'state' => $settings['us_warehouse_state'] ?? 'Florida',
                'zip_code' => $settings['us_warehouse_zip'] ?? '33009',
                'marks_suffix' => 'SEA',
                'is_active' => true,
            ],
            [
                'label' => 'China Air',
                'country_code' => 'CN',
                'flag_icon' => '🇨🇳',
                'freight_type' => 'air',
                'country' => 'China',
                'entrance_no' => $settings['china_entrance_no'] ?? 'Otisian',
                'province' => $settings['china_province'] ?? '',
                'city' => $settings['china_city'] ?? '',
                'detailed_address' => $settings['china_detailed_address'] ?? '',
                'number' => $settings['china_number'] ?? '',
                'zip_code' => $settings['china_zip'] ?? '',
                'marks_suffix' => 'AIR',
                'is_active' => true,
            ],
            [
                'label' => 'China Sea',
                'country_code' => 'CN',
                'flag_icon' => '🇨🇳',
                'freight_type' => 'sea',
                'country' => 'China',
                'entrance_no' => $settings['china_entrance_no'] ?? 'Otisian',
                'province' => $settings['china_province'] ?? '',
                'city' => $settings['china_city'] ?? '',
                'detailed_address' => $settings['china_detailed_address'] ?? '',
                'number' => $settings['china_number'] ?? '',
                'zip_code' => $settings['china_zip'] ?? '',
                'marks_suffix' => 'SEA',
                'is_active' => true,
            ],
        ];
    }

    private function normalizeRateHeader($column): string
    {
        $header = Str::snake(strtolower(trim((string) $column)));

        return match ($header) {
            'regular', 'regular_rate', 'regular_price', 'standard_price', 'standard_rate', 'price', 'rate', 'price_per_lb', 'regular_price_jmd' => 'price_jmd',
            'express', 'express_rate', 'express_price', 'express_price_jmd' => 'express_price_jmd',
            'minimum_weight', 'from_weight', 'from', 'min' => 'min_weight',
            'maximum_weight', 'to_weight', 'to', 'max' => 'max_weight',
            'freight', 'type', 'shipment_type' => 'freight_type',
            default => $header,
        };
    }

    private function combineRateCsvRow(array $header, array $row): ?array
    {
        if (count($header) === count($row)) {
            return array_combine($header, $row) ?: null;
        }

        if (count($row) < count($header)) {
            $row = array_pad($row, count($header), '');

            return array_combine($header, $row) ?: null;
        }

        $priceStart = collect($header)->search(fn ($column) => in_array($column, ['price_jmd', 'express_price_jmd'], true));
        if ($priceStart === false) {
            return array_combine($header, array_slice($row, 0, count($header))) ?: null;
        }

        $record = [];
        for ($index = 0; $index < $priceStart; $index++) {
            $record[$header[$index]] = $row[$index] ?? '';
        }

        $priceColumns = array_values(array_filter(array_slice($header, $priceStart), fn ($column) => in_array($column, ['price_jmd', 'express_price_jmd'], true)));
        $tailColumns = array_values(array_filter(array_slice($header, $priceStart), fn ($column) => ! in_array($column, ['price_jmd', 'express_price_jmd'], true)));
        $remaining = array_slice($row, $priceStart);
        $prices = [];
        $current = '';

        foreach ($remaining as $cell) {
            $cell = trim((string) $cell);
            if ($current !== '' && count($prices) < count($priceColumns) && preg_match('/^\s*(?:[$]|jmd|usd)\s*\d/i', $cell)) {
                $prices[] = $current;
                $current = $cell;
                continue;
            }

            if (count($prices) < count($priceColumns) - 1 && $current !== '' && preg_match('/^\s*(?:[$]|jmd|usd)?\s*\d+(?:\.\d+)?\s*$/i', $cell)) {
                $current .= ','.$cell;
                continue;
            }

            if ($current === '') {
                $current = $cell;
            } elseif (count($prices) < count($priceColumns)) {
                $current .= ','.$cell;
            } else {
                $tailColumns && $record[array_shift($tailColumns)] = $cell;
            }
        }

        if ($current !== '' && count($prices) < count($priceColumns)) {
            $prices[] = $current;
        }

        foreach ($priceColumns as $index => $column) {
            $record[$column] = $prices[$index] ?? '';
        }

        return $record;
    }

    private function decimalFromCsv($value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $negative = preg_match('/^\(.*\)$/', $value) === 1;
        $value = preg_replace('/[^\d.\-]/', '', $value);

        if ($value === '' || $value === '-' || $value === '.') {
            return 0.0;
        }

        $number = (float) $value;

        return $negative ? -1 * abs($number) : $number;
    }

    private function nullableDecimalFromCsv($value): ?float
    {
        if (trim((string) $value) === '') {
            return null;
        }

        return $this->decimalFromCsv($value);
    }

    private function normalizeWarehouseAddressSettings(array $addresses): array
    {
        return collect($addresses)
            ->filter(fn ($address) => filled($address['label'] ?? null) || filled($address['address_1'] ?? null) || filled($address['detailed_address'] ?? null))
            ->map(function ($address, $index) {
                $country = trim((string) ($address['country'] ?? ''));
                $countryCode = strtoupper(trim((string) ($address['country_code'] ?? '')));
                $countryCode = $countryCode ?: $this->countryCodeFor($country);
                $flagIcon = $countryCode ? $this->flagForCountryCode($countryCode) : trim((string) ($address['flag_icon'] ?? ''));

                return [
                    'shipment_type' => Str::slug(($address['label'] ?? 'warehouse').' '.$index, '_'),
                    'label' => trim((string) ($address['label'] ?? 'Warehouse '.($index + 1))),
                    'country_code' => $countryCode,
                    'flag_icon' => $flagIcon,
                    'flag_image' => trim((string) ($address['flag_image'] ?? '')),
                    'freight_type' => strtolower(trim((string) ($address['freight_type'] ?? ''))),
                    'country' => $country,
                    'name_suffix' => trim((string) ($address['name_suffix'] ?? '')),
                    'address_1' => trim((string) ($address['address_1'] ?? '')),
                    'address_2_suffix' => trim((string) ($address['address_2_suffix'] ?? '')),
                    'city' => trim((string) ($address['city'] ?? '')),
                    'state' => trim((string) ($address['state'] ?? '')),
                    'province' => trim((string) ($address['province'] ?? '')),
                    'zip_code' => trim((string) ($address['zip_code'] ?? '')),
                    'entrance_no' => trim((string) ($address['entrance_no'] ?? '')),
                    'detailed_address' => trim((string) ($address['detailed_address'] ?? '')),
                    'number' => trim((string) ($address['number'] ?? '')),
                    'marks_suffix' => trim((string) ($address['marks_suffix'] ?? '')),
                    'note' => trim((string) ($address['note'] ?? '')),
                    'is_active' => ! empty($address['is_active']),
                ];
            })
            ->values()
            ->all();
    }

    private function countryCodeFor(string $country): string
    {
        $countries = [
            'united states' => 'US',
            'china' => 'CN',
            'jamaica' => 'JM',
            'united kingdom' => 'GB',
            'canada' => 'CA',
            'panama' => 'PA',
            'trinidad and tobago' => 'TT',
            'barbados' => 'BB',
            'guyana' => 'GY',
            'bahamas' => 'BS',
            'dominican republic' => 'DO',
            'mexico' => 'MX',
            'india' => 'IN',
            'japan' => 'JP',
            'germany' => 'DE',
            'france' => 'FR',
            'spain' => 'ES',
            'italy' => 'IT',
        ];

        return $countries[strtolower($country)] ?? '';
    }

    private function flagForCountryCode(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));
        if (! preg_match('/^[A-Z]{2}$/', $countryCode) || ! function_exists('mb_chr')) {
            return '';
        }

        return mb_chr(127397 + ord($countryCode[0]), 'UTF-8').mb_chr(127397 + ord($countryCode[1]), 'UTF-8');
    }
}
