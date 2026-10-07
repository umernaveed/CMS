<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WmsSyncService
{
    private ?string $lastError = null;

    public function enabled(): bool
    {
        return (bool) $this->baseUrl() && (bool) $this->token();
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function syncTenant(Tenant $tenant): ?int
    {
        $response = $this->post('/api/saas/couriers', [
            'tenant_id' => $tenant->id,
            'company_name' => $tenant->company_name,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'city' => $tenant->city,
            'parish' => $tenant->parish,
            'portal_url' => $tenant->portalUrl(),
            'status' => $tenant->status,
        ]);

        $courierId = data_get($response, 'data.courier_id');
        if ($courierId) {
            TenantSetting::updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => 'wms_courier_id'],
                ['value' => (string) $courierId]
            );
        } elseif (! $this->lastError) {
            $this->lastError = 'WMS did not return a courier ID.';
        }

        return $courierId ? (int) $courierId : null;
    }

    public function syncCustomer(Customer $customer): bool
    {
        $customer->loadMissing(['tenant', 'user', 'outlet']);
        $courierId = $this->wmsCourierId((int) $customer->tenant_id);
        if (! $courierId) {
            $courierId = $customer->tenant ? $this->syncTenant($customer->tenant) : null;
            if (! $courierId) {
                $this->lastError = $this->lastError ?: 'Tenant is not linked to a WMS courier.';
                return false;
            }
        }

        if (! $customer->user) {
            $this->lastError = 'Customer has no linked user record.';
            return false;
        }

        $name = trim((string) optional($customer->user)->full_name);
        [$firstName, $lastName] = $this->splitName($name);

        $response = $this->post('/api/saas/customers', [
            'courier_id' => $courierId,
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'mailbox' => $customer->mailbox,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $name,
            'email' => $this->validEmail(optional($customer->user)->email),
            'phone' => optional($customer->user)->phone,
            'address' => optional($customer->user)->address_line1,
            'city' => optional($customer->user)->city,
            'state' => optional($customer->user)->parish,
            'outlet_id' => optional($customer->outlet)->outlet_code,
            'active' => (bool) $customer->is_active,
        ]);

        return (bool) data_get($response, 'data.user_id');
    }

    public function wmsCourierId(int $tenantId): ?int
    {
        $value = TenantSetting::getValue($tenantId, 'wms_courier_id');

        return $value ? (int) $value : null;
    }

    private function post(string $path, array $payload): ?array
    {
        $this->lastError = null;

        if (! $this->enabled()) {
            $this->lastError = 'WMS sync is not configured.';
            return null;
        }

        try {
            $response = Http::timeout((int) config('services.wms.timeout', 10))
                ->acceptJson()
                ->withToken($this->token())
                ->post($this->baseUrl().$path, $payload);

            if (! $response->successful()) {
                $this->lastError = 'HTTP '.$response->status().': '.$response->body();
                Log::warning('WMS sync request failed', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();
            Log::warning('WMS sync request exception', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    private function baseUrl(): ?string
    {
        return rtrim((string) config('services.wms.url'), '/');
    }

    private function token(): ?string
    {
        return config('services.wms.token');
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function validEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
