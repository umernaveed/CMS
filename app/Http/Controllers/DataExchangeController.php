<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageLog;
use App\Models\PackageStatus;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Models\WeightRate;
use App\Services\CustomerShippingAddressService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExchangeController extends Controller
{
    private const IMPORT_TIMEOUT_SECONDS = 300;
    private array $exports = [
        'customers' => Customer::class,
        'packages' => Package::class,
        'invoices' => Invoice::class,
        'users' => User::class,
        'weight_rates' => WeightRate::class,
        'package_statuses' => PackageStatus::class,
    ];

    private array $exportColumns = [
        'customers' => ['id', 'mailbox', 'outlet_id', 'pricing_tier_id', 'loyalty_tier', 'packages_collected_count', 'total_spent_jmd', 'is_active', 'created_at'],
        'packages' => ['id', 'customer_id', 'outlet_id', 'tracking_number', 'supplier_tracking', 'mailbox', 'courier', 'merchant', 'description', 'weight', 'price_usd', 'price_jmd', 'freight_type', 'delivery_type', 'status_id', 'storage_days', 'storage_fee', 'processing_fee_jmd', 'date_received', 'date_delivered', 'created_at'],
        'invoices' => ['id', 'customer_id', 'outlet_id', 'invoice_number', 'mailbox', 'subtotal_jmd', 'service_fee_jmd', 'customs_fee_jmd', 'storage_fee_jmd', 'gct_amount_jmd', 'discount_amount_jmd', 'total_jmd', 'payment_status', 'invoice_status', 'paid_at', 'due_date', 'created_at'],
        'users' => ['id', 'outlet_id', 'user_type', 'mailbox', 'full_name', 'email', 'telephone_1', 'telephone_2', 'trn_number', 'date_of_birth', 'address_line1', 'city', 'parish', 'country', 'postal_code', 'is_active', 'last_login_at', 'created_at'],
        'weight_rates' => ['id', 'pricing_tier_id', 'min_weight', 'max_weight', 'minimum_billable_measure', 'processing_fee_jmd', 'price_jmd', 'express_price_jmd', 'freight_type', 'is_active', 'created_at'],
        'package_statuses' => ['id', 'name', 'slug', 'color', 'icon', 'sort_order', 'is_default', 'is_active', 'created_at'],
    ];

    private array $massDeletable = [
        'packages' => Package::class,
        'invoices' => Invoice::class,
        'weight_rates' => WeightRate::class,
        'package_statuses' => PackageStatus::class,
    ];

    private array $importTemplates = [
        'packages' => [
            'columns' => ['tracking_number', 'supplier_tracking', 'mailbox', 'customer_email', 'status', 'freight_type', 'delivery_type', 'weight', 'price_usd', 'price_jmd', 'storage_days', 'storage_fee', 'processing_fee_jmd', 'merchant', 'courier', 'description', 'date_received', 'notes'],
            'sample' => ['NCLS000001', 'SUP12345', 'MBX1001', 'customer@example.com', 'Received', 'air', 'pickup', '2.5', '25.00', '3850.00', '0', '0.00', '0.00', 'Amazon', 'DHL', 'Shoes', '2026-05-21', 'Imported test row'],
        ],
        'users' => [
            'columns' => ['user_type', 'full_name', 'email', 'telephone_1', 'telephone_2', 'trn_number', 'date_of_birth', 'mailbox', 'password', 'outlet_id', 'outlet_code', 'address_line1', 'city', 'parish', 'country', 'postal_code', 'is_active'],
            'sample' => ['customer', 'Jane Customer', 'jane@example.com', '8765550100', '8765550199', '123456789', '1990-01-15', 'MBX1001', 'TempPass123', '', '', '12 Main Street', 'Kingston', 'St Andrew', 'Jamaica', '', 'yes'],
        ],
        'weight_rates' => [
            'columns' => ['freight_type', 'min_weight', 'max_weight', 'minimum_billable_measure', 'processing_fee_jmd', 'price_jmd', 'express_price_jmd', 'is_active'],
            'sample' => ['air', '0', '1', '0', '300.00', '850.00', '1200.00', 'yes'],
        ],
    ];

    public function export(string $table): StreamedResponse
    {
        abort_unless(isset($this->exports[$table]), 404);
        $model = $this->exports[$table];
        $rows = $model::where('tenant_id', auth()->user()->tenant_id)->get();
        ActivityLog::record('exported', $table, 'Exported '.$table.' CSV');

        $columns = $this->exportColumns[$table];

        return response()->streamDownload(function () use ($rows, $columns) {
            echo "\xEF\xBB\xBF";
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            if ($rows->isNotEmpty()) {
                foreach ($rows as $row) {
                    fputcsv($handle, collect($columns)->map(fn ($column) => $this->exportValue($row, $column))->all());
                }
            }
            fclose($handle);
        }, $table.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportValue($row, string $column)
    {
        if ($column === 'date_of_birth') {
            $dob = data_get($row, 'dob');

            return $dob instanceof \Carbon\CarbonInterface ? $dob->format('Y-m-d') : $dob;
        }

        return match ($column) {
            'telephone_1' => data_get($row, 'phone'),
            'telephone_2' => data_get($row, 'phone_2'),
            'trn_number' => data_get($row, 'trn'),
            default => data_get($row, $column),
        };
    }
    public function template(string $table): StreamedResponse
    {
        abort_unless(isset($this->importTemplates[$table]), 404);

        $template = $this->importTemplates[$table];

        return response()->streamDownload(function () use ($template) {
            echo "\xEF\xBB\xBF";
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $template['columns']);
            fputcsv($handle, $template['sample']);
            fclose($handle);
        }, $table.'-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request, string $table)
    {
        abort_unless(in_array($table, ['weight_rates', 'packages', 'users']), 404);
        $importTimeout = $this->extendImportRuntime();
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:20480']);

        $handle = $this->openCsvFile($request->file('file'));
        $header = array_map(fn ($column) => $this->normalizeImportHeader($column), fgetcsv($handle) ?: []);
        $created = 0;
        $processed = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $processed++;
            if ($processed % 100 === 0) {
                $this->extendImportRuntime($importTimeout);
            }

            $data = $this->combineImportRow($header, $row);
            if (! $data) {
                $skipped++;
                continue;
            }

            $imported = match ($table) {
                'weight_rates' => $this->importWeightRate($data),
                'packages' => $this->importPackage($data),
                'users' => $this->importUser($data),
            };

            $imported ? $created++ : $skipped++;
        }
        fclose($handle);

        ActivityLog::record('imported', $table, 'Imported '.$created.' rows into '.$table.'; skipped '.$skipped);

        return back()->with('success', 'Imported '.$created.' rows. Skipped '.$skipped.' rows.');
    }

    private function extendImportRuntime(?int $seconds = null): int
    {
        $seconds = max((int) ($seconds ?? self::IMPORT_TIMEOUT_SECONDS), 120);

        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }

        @ini_set('max_execution_time', (string) $seconds);
        DB::disableQueryLog();

        return $seconds;
    }

    private function openCsvFile($file)
    {
        $content = file_get_contents($file->getRealPath());
        $content = $this->normalizeCsvContent($content === false ? '' : $content);
        $handle = tmpfile();
        fwrite($handle, $content);
        rewind($handle);

        return $handle;
    }

    private function repairWindowsSmartBytes(string $content): string
    {
        return preg_replace_callback('/[\x80-\x9F]/', function (array $match) {
            if (function_exists('mb_convert_encoding')) {
                return mb_convert_encoding($match[0], 'UTF-8', 'Windows-1252');
            }

            $converted = iconv('Windows-1252', 'UTF-8//IGNORE', $match[0]);
            return $converted === false ? '' : $converted;
        }, $content) ?? $content;
    }
    private function normalizeCsvContent(string $content): string
    {
        if ($content === '') {
            return $content;
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        $encoding = null;
        if (str_starts_with($content, "\xFF\xFE")) {
            $encoding = 'UTF-16LE';
            $content = substr($content, 2);
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            $encoding = 'UTF-16BE';
            $content = substr($content, 2);
        } elseif (function_exists('mb_check_encoding') && ! mb_check_encoding($content, 'UTF-8')) {
            $repaired = $this->repairWindowsSmartBytes($content);
            if (mb_check_encoding($repaired, 'UTF-8')) {
                $content = $repaired;
            } else {
                $encoding = 'Windows-1252';
            }
        }

        if (! $encoding && substr_count(substr($content, 0, 512), "\x00") > 8) {
            $encoding = 'UTF-16LE';
        }

        if ($encoding && function_exists('mb_convert_encoding')) {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        } elseif ($encoding) {
            $converted = iconv($encoding, 'UTF-8//IGNORE', $content);
            $content = $converted === false ? $content : $converted;
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
    }
    public function massDelete(Request $request, string $table)
    {
        abort_unless(isset($this->massDeletable[$table]), 404);

        $data = $request->validate([
            'delete_scope' => 'required|in:selected,all',
            'ids' => 'nullable|string',
            'confirm_text' => 'required|string',
        ]);

        if (strtoupper(trim($data['confirm_text'])) !== 'DELETE') {
            return back()->withErrors(['confirm_text' => 'Type DELETE to confirm mass deletion.']);
        }

        $model = $this->massDeletable[$table];
        $query = $model::where('tenant_id', auth()->user()->tenant_id);

        if ($data['delete_scope'] === 'selected') {
            $ids = collect(preg_split('/[\s,]+/', (string) ($data['ids'] ?? '')))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                return back()->withErrors(['ids' => 'Enter one or more record IDs for selected deletion.']);
            }

            $query->whereIn('id', $ids);
        }

        $count = (clone $query)->count();
        $query->delete();

        ActivityLog::record('mass_deleted', $table, 'Mass deleted '.$count.' rows from '.$table, [
            'scope' => $data['delete_scope'],
        ]);

        return back()->with('success', 'Deleted '.$count.' '.$table.' record'.($count === 1 ? '' : 's').'.');
    }

    private function importWeightRate(array $data): bool
    {
        $minWeight = $this->decimalFromCsv($data['min_weight'] ?? 0);
        $minimumBillable = trim((string) ($data['minimum_billable_measure'] ?? '')) === ''
            ? $minWeight
            : $this->decimalFromCsv($data['minimum_billable_measure']);

        WeightRate::create([
            'tenant_id' => auth()->user()->tenant_id,
            'freight_type' => $this->allowed($data['freight_type'] ?? 'air', ['air', 'sea', 'local'], 'air'),
            'min_weight' => $minWeight,
            'max_weight' => $this->nullableDecimalFromCsv($data['max_weight'] ?? null),
            'minimum_billable_measure' => $minimumBillable,
            'processing_fee_jmd' => $this->decimalFromCsv($data['processing_fee_jmd'] ?? 0),
            'price_jmd' => $this->decimalFromCsv($data['price_jmd'] ?? $data['regular_price'] ?? $data['price'] ?? 0),
            'express_price_jmd' => $this->decimalFromCsv($data['express_price_jmd'] ?? $data['express_price'] ?? 0),
            'is_active' => $this->truthy($data['is_active'] ?? true),
        ]);

        return true;
    }

    private function normalizeImportHeader($column): string
    {
        $header = Str::snake(strtolower(trim((string) $column)));

        return match ($header) {
            'regular', 'regular_rate', 'regular_price', 'standard_price', 'standard_rate', 'price', 'rate', 'price_per_lb', 'regular_price_jmd' => 'price_jmd',
            'express', 'express_rate', 'express_price', 'express_price_jmd' => 'express_price_jmd',
            'minimum_weight', 'from_weight', 'from', 'min' => 'min_weight',
            'maximum_weight', 'to_weight', 'to', 'max' => 'max_weight',
            'minimum_billable', 'min_billable', 'billable_minimum', 'minimum_billable_weight', 'minimum_billable_volume' => 'minimum_billable_measure',
            'processing_fee', 'processing_fee', 'processing_fee_jmd', 'processing_charge', 'rate_processing_fee' => 'processing_fee_jmd',
            'telephone_1', 'telephone1', 'phone_1', 'phone1', 'primary_phone' => 'phone',
            'telephone_2', 'telephone2', 'phone_2', 'phone2', 'secondary_phone' => 'phone_2',
            'trn_number', 'trn_no', 'tax_number' => 'trn',
            'date_of_birth', 'birth_date' => 'dob',
            'freight', 'type', 'shipment_type' => 'freight_type',
            default => $header,
        };
    }

    private function combineImportRow(array $header, array $row): ?array
    {
        if (count($row) > count($header) && in_array('price_jmd', $header, true) && in_array('express_price_jmd', $header, true)) {
            return $this->combineWeightRateRow($header, $row);
        }

        if (count($row) < count($header)) {
            $row = array_pad($row, count($header), '');
        }

        return array_combine($header, array_slice($row, 0, count($header))) ?: null;
    }

    private function combineWeightRateRow(array $header, array $row): ?array
    {
        $priceStart = collect($header)->search(fn ($column) => in_array($column, ['price_jmd', 'express_price_jmd'], true));
        if ($priceStart === false) {
            return array_combine($header, array_slice($row, 0, count($header))) ?: null;
        }

        $record = [];
        for ($index = 0; $index < $priceStart; $index++) {
            $record[$header[$index]] = $row[$index] ?? '';
        }

        $priceColumns = array_values(array_filter(array_slice($header, $priceStart), fn ($column) => in_array($column, ['price_jmd', 'express_price_jmd'], true)));
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

    private function importPackage(array $data): bool
    {
        $tenantId = auth()->user()->tenant_id;
        $tracking = trim((string) ($data['tracking_number'] ?? ''));
        $tracking = $tracking === '' ? $this->nextHawbNumber($tenantId) : strtoupper($tracking);
        if (Package::where('tenant_id', $tenantId)->where('tracking_number', $tracking)->exists()) {
            return false;
        }

        app(SubscriptionLimitService::class)->ensureCanCreate($tenantId, 'packages_month');
        $customer = $this->customerFromRow($data);
        $statusId = $this->statusFromRow($data);

        $package = Package::create([
            'tenant_id' => $tenantId,
            'customer_id' => optional($customer)->id,
            'outlet_id' => optional($customer)->outlet_id,
            'tracking_number' => $tracking,
            'supplier_tracking' => $data['supplier_tracking'] ?? null,
            'mailbox' => optional($customer)->mailbox ?: Customer::normalizeMailbox($data['mailbox'] ?? null),
            'courier' => $data['courier'] ?? null,
            'merchant' => $data['merchant'] ?? null,
            'description' => $data['description'] ?? null,
            'weight' => (float) ($data['weight'] ?? 0),
            'price_usd' => (float) ($data['price_usd'] ?? 0),
            'price_jmd' => (float) ($data['price_jmd'] ?? 0),
            'freight_type' => $this->allowed($data['freight_type'] ?? 'air', ['air', 'sea', 'local'], 'air'),
            'delivery_type' => $this->allowed($data['delivery_type'] ?? 'pickup', ['pickup', 'delivery'], 'pickup'),
            'status_id' => $statusId,
            'storage_days' => (int) ($data['storage_days'] ?? 0),
            'storage_fee' => (float) ($data['storage_fee'] ?? 0),
            'processing_fee_jmd' => (float) ($data['processing_fee_jmd'] ?? 0),
            'date_received' => ! empty($data['date_received']) ? $data['date_received'] : now()->toDateString(),
            'notes' => $data['notes'] ?? null,
        ]);

        PackageLog::create([
            'tenant_id' => $tenantId,
            'package_id' => $package->id,
            'status_id' => $package->status_id,
            'note' => 'Imported package',
            'location' => optional(auth()->user()->outlet)->name,
            'added_by' => auth()->id(),
            'created_at' => now(),
        ]);

        return true;
    }

    private function importUser(array $data): bool
    {
        $tenantId = auth()->user()->tenant_id;
        $email = trim((string) ($data['email'] ?? ''));
        $type = $this->allowed($data['user_type'] ?? 'customer', ['customer', 'staff', 'tenant_admin'], 'customer');
        if ($email === '') {
            return false;
        }
        if ($type === 'customer' && $this->customerIdentityExists($tenantId, $data + ['email' => $email])) {
            return false;
        }
        if ($type !== 'customer' && User::where('email', $email)->exists()) {
            return false;
        }

        if ($type === 'customer') {
            app(SubscriptionLimitService::class)->ensureCanCreate($tenantId, 'customers');
        } else {
            app(SubscriptionLimitService::class)->ensureCanCreate($tenantId, 'users');
        }

        return DB::transaction(function () use ($data, $tenantId, $email, $type) {
            $mailbox = Customer::normalizeMailbox($data['mailbox'] ?? null) ?? '';
            if ($type === 'customer') {
                if ($mailbox === '') {
                    $mailbox = app(\App\Services\MailboxService::class)->generate(Tenant::findOrFail($tenantId));
                }

                if (Customer::where('tenant_id', $tenantId)->whereNormalizedMailbox($mailbox)->exists()) {
                    return false;
                }
            }

            $user = User::create([
                'tenant_id' => $tenantId,
                'outlet_id' => $this->outletIdFromRow($data) ?: $this->defaultOutletId(),
                'user_type' => $type,
                'mailbox' => $type === 'customer' ? $mailbox : null,
                'full_name' => $data['full_name'] ?? trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'trn' => $data['trn'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'city' => $data['city'] ?? null,
                'parish' => $data['parish'] ?? null,
                'password' => Hash::make($data['password'] ?? Str::random(12)),
                'is_active' => $this->truthy($data['is_active'] ?? true),
            ]);

            if ($type === 'customer') {
                $customer = Customer::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'mailbox' => $mailbox,
                    'outlet_id' => $user->outlet_id,
                    'is_active' => true,
                ]);
                app(CustomerShippingAddressService::class)->syncDefaultAddresses($customer);
            }

            return true;
        });
    }

    private function customerFromRow(array $data): ?Customer
    {
        $query = Customer::where('tenant_id', auth()->user()->tenant_id);
        if (! empty($data['customer_id'])) {
            return (clone $query)->whereKey($data['customer_id'])->first();
        }
        if (! empty($data['mailbox'])) {
            return (clone $query)->whereNormalizedMailbox($data['mailbox'])->first();
        }
        if (! empty($data['customer_email'])) {
            return (clone $query)->whereHas('user', fn ($userQuery) => $userQuery->where('email', $data['customer_email']))->first();
        }

        return null;
    }

    private function customerIdentityExists(int $tenantId, array $data): bool
    {
        foreach (['trn', 'email', 'phone'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            if (User::where('tenant_id', $tenantId)->where('user_type', 'customer')->where($field, $value)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function outletIdFromRow(array $data): ?int
    {
        if (! empty($data['outlet_id'])) {
            return \App\Models\Outlet::where('tenant_id', auth()->user()->tenant_id)->whereKey($data['outlet_id'])->value('id');
        }
        if (! empty($data['outlet_code'])) {
            return \App\Models\Outlet::where('tenant_id', auth()->user()->tenant_id)->where('outlet_code', $data['outlet_code'])->value('id');
        }

        return null;
    }

    private function defaultOutletId(): ?int
    {
        return \App\Models\Outlet::where('tenant_id', auth()->user()->tenant_id)->where('is_main', true)->value('id')
            ?: \App\Models\Outlet::where('tenant_id', auth()->user()->tenant_id)->value('id');
    }

    private function statusFromRow(array $data): ?int
    {
        $query = PackageStatus::where('tenant_id', auth()->user()->tenant_id);
        if (! empty($data['status_id'])) {
            return (clone $query)->whereKey($data['status_id'])->value('id');
        }
        if (! empty($data['status'])) {
            return (clone $query)
                ->where(fn ($statusQuery) => $statusQuery->where('slug', Str::slug($data['status']))->orWhere('name', $data['status']))
                ->value('id');
        }

        return (clone $query)->orderByDesc('is_default')->orderBy('sort_order')->value('id');
    }

    private function allowed(string $value, array $allowed, string $default): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function nextHawbNumber(int $tenantId): string
    {
        $prefix = $this->hawbPrefix($tenantId);
        $sequence = Package::where('tenant_id', $tenantId)
            ->where('tracking_number', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $hawb = $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Package::where('tenant_id', $tenantId)->where('tracking_number', $hawb)->exists());

        return $hawb;
    }

    private function hawbPrefix(int $tenantId): string
    {
        $configured = TenantSetting::getValue($tenantId, 'hawb_prefix')
            ?: TenantSetting::getValue($tenantId, 'courier_code')
            ?: TenantSetting::getValue($tenantId, 'mailbox_prefix');
        $prefix = strtoupper(trim(preg_replace('/[^A-Z0-9-]/i', '', (string) $configured)));

        if ($prefix !== '') {
            return $prefix;
        }

        $companyName = optional(Tenant::find($tenantId))->company_name ?: optional(auth()->user()->tenant)->company_name ?: 'COU';
        $fallback = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $companyName), 0, 4));

        return $fallback !== '' ? $fallback : 'COU';
    }

    private function truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'active', 'on'], true);
    }
}




