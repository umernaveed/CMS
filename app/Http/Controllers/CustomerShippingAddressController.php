<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerShippingAddress;
use App\Models\TenantSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CustomerShippingAddressController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        abort_if($customer->tenant_id !== auth()->user()->tenant_id, 404);

        $data = $request->validate($this->addressRules([
            'apply_to' => 'required|in:specific,all',
            'mailbox_line' => 'nullable|string|max:255',
        ]));

        $tenantId = auth()->user()->tenant_id;
        $applyToAll = $data['apply_to'] === 'all';
        $targets = $applyToAll
            ? Customer::where('tenant_id', $tenantId)->with(['user', 'outlet'])->get()
            : collect([$customer->loadMissing(['user', 'outlet'])]);

        unset($data['apply_to']);
        $mailboxLine = $data['mailbox_line'] ?? null;
        unset($data['mailbox_line']);
        if ($request->hasFile('flag_upload')) {
            $data['flag_image'] = $this->storeFlagImage($request, $tenantId);
        }
        unset($data['flag_upload']);

        foreach ($targets as $target) {
            $addressData = $this->personalizeAddressData($data, $target, $mailboxLine);

            CustomerShippingAddress::updateOrCreate([
                'tenant_id' => $tenantId,
                'customer_id' => $target->id,
                'shipment_type' => $addressData['shipment_type'],
                'is_system' => false,
            ], $this->filterAddressColumns($addressData + [
                'tenant_id' => $tenantId,
                'customer_id' => $target->id,
                'is_system' => false,
                'is_active' => true,
            ]));
        }

        ActivityLog::record('created', 'customer_addresses', 'Added shipping address for '.($applyToAll ? 'all customers' : $customer->mailbox), [
            'customer_id' => $customer->id,
            'apply_to_all' => $applyToAll,
            'address_count' => $targets->count(),
        ]);

        return back()->with('success', 'Shipping address added to '.$targets->count().' customer'.($targets->count() === 1 ? '' : 's').'.');
    }

    public function update(Request $request, Customer $customer, CustomerShippingAddress $address)
    {
        $this->authorizeAddress($customer, $address);
        $data = $request->validate($this->addressRules([
            'remove_flag_image' => 'nullable|boolean',
        ]));
        $previousShipmentType = $address->shipment_type;

        $data['shipment_type'] = Str::limit(Str::slug($data['shipment_type'], '_'), 50, '');
        $data['country_code'] = strtoupper((string) ($data['country_code'] ?? ''));
        if (blank($data['flag_icon'] ?? null)) {
            $data['flag_icon'] = $this->flagFromCountryCode($data['country_code'] ?? null);
        }

        if ($request->boolean('remove_flag_image')) {
            $this->deleteFlagImage($address->flag_image, $address->id, $customer->tenant_id);
            $data['flag_image'] = null;
        }
        unset($data['remove_flag_image']);

        if ($request->hasFile('flag_upload')) {
            $this->deleteFlagImage($address->flag_image, $address->id, $customer->tenant_id);
            $data['flag_image'] = $this->storeFlagImage($request, $customer->tenant_id);
        }
        unset($data['flag_upload']);

        $address->update($this->filterAddressColumns($data + [
            'is_system' => false,
            'is_active' => true,
        ]));

        if ($previousShipmentType !== $address->shipment_type) {
            CustomerShippingAddress::updateOrCreate([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'shipment_type' => $previousShipmentType,
                'is_system' => false,
            ], [
                'label' => $address->label,
                'name' => $address->name,
                'is_active' => false,
            ]);
        }

        CustomerShippingAddress::where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->whereIn('shipment_type', array_unique([$previousShipmentType, $address->shipment_type]))
            ->where('is_system', true)
            ->whereKeyNot($address->id)
            ->update(['is_active' => false]);

        ActivityLog::record('updated', 'customer_addresses', 'Updated shipping address for '.$customer->mailbox, [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
        ]);

        return back()->with('success', 'Shipping address updated.');
    }

    public function destroy(Customer $customer, CustomerShippingAddress $address)
    {
        $this->authorizeAddress($customer, $address);
        $this->deleteFlagImage($address->flag_image, $address->id, $customer->tenant_id);

        $address->update([
            'is_system' => false,
            'is_active' => false,
            'flag_image' => null,
        ]);

        ActivityLog::record('deleted', 'customer_addresses', 'Deleted shipping address for '.$customer->mailbox, [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
        ]);

        return back()->with('success', 'Shipping address deleted.');
    }

    private function personalizeAddressData(array $data, Customer $customer, ?string $mailboxLine): array
    {
        $customerName = optional($customer->user)->full_name ?: 'Customer';
        $branch = optional($customer->outlet)->outlet_code ?: optional($customer->outlet)->short_name ?: optional($customer->outlet)->name ?: 'MAIN';
        $addressMailbox = $this->addressMailbox($customer->tenant_id, (string) $customer->mailbox);
        $tokens = [
            '{mailbox}' => $addressMailbox,
            '{name}' => $customerName,
            '{branch}' => $branch,
        ];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = strtr($value, $tokens);
            }
        }

        if ($mailboxLine) {
            $data['address_2'] = strtr($mailboxLine, $tokens);
        } elseif (blank($data['address_2'] ?? null)) {
            $data['address_2'] = $addressMailbox;
        }

        if (blank($data['flag_icon'] ?? null)) {
            $data['flag_icon'] = $this->flagFromCountryCode($data['country_code'] ?? null);
        }

        $data['country_code'] = strtoupper((string) ($data['country_code'] ?? ''));
        $data['shipment_type'] = Str::limit(Str::slug($data['shipment_type'], '_'), 50, '');

        return $data;
    }

    private function filterAddressColumns(array $data): array
    {
        if (! Schema::hasColumn('customer_shipping_addresses', 'flag_icon')) {
            unset($data['flag_icon']);
        }
        if (! Schema::hasColumn('customer_shipping_addresses', 'flag_image')) {
            unset($data['flag_image']);
        }

        return $data;
    }

    private function addressRules(array $extra = []): array
    {
        return $extra + [
            'shipment_type' => 'required|string|max:50',
            'label' => 'required|string|max:100',
            'country_code' => 'nullable|string|max:10',
            'flag_icon' => 'nullable|string|max:20',
            'flag_upload' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'name' => 'required|string|max:255',
            'marks' => 'nullable|string|max:255',
            'address_1' => 'nullable|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'entrance_no' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:150',
            'city' => 'nullable|string|max:150',
            'detailed_address' => 'nullable|string',
            'state' => 'nullable|string|max:150',
            'zip_code' => 'nullable|string|max:50',
            'number' => 'nullable|string|max:100',
            'note' => 'nullable|string',
        ];
    }

    private function authorizeAddress(Customer $customer, CustomerShippingAddress $address): void
    {
        $tenantId = (int) auth()->user()->tenant_id;
        abort_if(
            (int) $customer->tenant_id !== $tenantId
            || (int) $address->tenant_id !== $tenantId
            || (int) $address->customer_id !== (int) $customer->id,
            404
        );
    }

    private function storeFlagImage(Request $request, int $tenantId): string
    {
        $file = $request->file('flag_upload');
        $directory = public_path('uploads/warehouse-flags/'.$tenantId);
        File::ensureDirectoryExists($directory);
        $filename = 'flag-'.Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return 'uploads/warehouse-flags/'.$tenantId.'/'.$filename;
    }

    private function deleteFlagImage(?string $path, int $addressId, int $tenantId): void
    {
        $path = trim((string) $path);
        if ($path === '' || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        $usedByAnotherAddress = CustomerShippingAddress::where('flag_image', $path)
            ->whereKeyNot($addressId)
            ->exists();
        $usedByWarehouseTemplate = TenantSetting::where('tenant_id', $tenantId)
            ->where('key', 'warehouse_addresses')
            ->where('value', 'like', '%'.$path.'%')
            ->exists();
        if ($usedByAnotherAddress || $usedByWarehouseTemplate) {
            return;
        }

        $fullPath = public_path(ltrim($path, '/'));
        $uploadsRoot = public_path('uploads/warehouse-flags');
        if (Str::startsWith($fullPath, $uploadsRoot) && File::isFile($fullPath)) {
            File::delete($fullPath);
        }
    }

    private function flagFromCountryCode(?string $countryCode): ?string
    {
        $countryCode = strtoupper((string) $countryCode);
        if (strlen($countryCode) !== 2) {
            return null;
        }

        return mb_chr(127397 + ord($countryCode[0]), 'UTF-8').mb_chr(127397 + ord($countryCode[1]), 'UTF-8');
    }

    private function addressMailbox(int $tenantId, string $mailbox): string
    {
        $prefix = 'OL-';
        $mailbox = trim($mailbox);

        if ($prefix === '' || Str::startsWith(strtoupper($mailbox), $prefix)) {
            return $mailbox;
        }

        if (preg_match('/(\d+)$/', $mailbox, $matches)) {
            return $prefix.$matches[1];
        }

        return $prefix.$mailbox;
    }
}
