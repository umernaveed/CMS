<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantPayment;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionGatewayService
{
    public function settings()
    {
        return SystemSetting::pluck('value', 'key');
    }

    public function trialDays(): int
    {
        return max(0, (int) ($this->settings()['subscription_trial_days'] ?? 14));
    }

    public function activeGateway(): string
    {
        return $this->settings()['subscription_payment_gateway'] ?? 'paypal';
    }

    public function availableGateways(): array
    {
        $configured = $this->settings()['subscription_payment_gateways'] ?? 'paypal,dime,amber_pay';

        return collect(explode(',', $configured))
            ->map(fn ($gateway) => trim($gateway))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function registrationGateways(): array
    {
        return collect($this->availableGateways())
            ->reject(fn ($gateway) => in_array($gateway, ['test_gateway', 'manual', 'free_trial'], true))
            ->filter(fn ($gateway) => filled($this->settings()[$gateway.'_checkout_url'] ?? null))
            ->values()
            ->all();
    }

    public function activeRegistrationGateway(): ?string
    {
        $gateways = $this->registrationGateways();
        $active = $this->activeGateway();

        return in_array($active, $gateways, true) ? $active : ($gateways[0] ?? null);
    }

    public function gatewayLabels(): array
    {
        return [
            'test_gateway' => 'Test Gateway',
            'paypal' => 'PayPal',
            'dime' => 'Dime',
            'amber_pay' => 'Amber Pay',
            'manual' => 'Manual Review',
        ];
    }

    public function checkoutUrl(TenantPayment $payment, Tenant $tenant, SubscriptionPlan $plan, string $gateway): string
    {
        if (! in_array($gateway, $this->registrationGateways(), true)) {
            throw ValidationException::withMessages([
                'gateway' => 'Please select a configured live payment gateway.',
            ]);
        }

        $settings = $this->settings();
        $template = $settings[$gateway.'_checkout_url'] ?? null;

        if (! $template) {
            throw ValidationException::withMessages([
                'gateway' => ucfirst(str_replace('_', ' ', $gateway)).' checkout URL is not configured in Super Admin Settings.',
            ]);
        }

        $returnUrl = URL::temporarySignedRoute('tenant.register.payment.success', now()->addHours(2), $payment);
        $cancelUrl = URL::temporarySignedRoute('tenant.register.payment.cancel', now()->addHours(2), $payment);
        $apiKey = $settings[$gateway.'_api_key'] ?? null;
        $clientId = $settings[$gateway.'_client_id'] ?? null;
        $params = [
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
            'reference' => $payment->transaction_id,
            'description' => 'Subscription for '.$plan->name,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $tenant->email,
            'customer_name' => $tenant->company_name,
            'api_key' => $apiKey ?: $clientId,
        ];

        $params = array_filter($params, fn ($value) => filled($value));

        $url = $template;
        $usedPlaceholders = [];
        foreach ($params as $key => $value) {
            $placeholder = '{'.$key.'}';
            if (Str::contains($template, $placeholder)) {
                $usedPlaceholders[] = $key;
                $url = str_replace($placeholder, rawurlencode($value), $url);
            }
        }

        $missingParams = array_diff_key($params, array_flip($usedPlaceholders));
        if (! empty($missingParams)) {
            $url .= (Str::contains($url, '?') ? '&' : '?').http_build_query($missingParams);
        }

        return $url;
    }
}
