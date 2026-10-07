<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PricingTier;
use App\Models\Referral;
use App\Models\TenantSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoyaltyService
{
    public function refreshCustomerTier(Customer $customer): void
    {
        $collected = Package::where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->whereNotNull('date_delivered')
            ->count();

        $tier = PricingTier::where('tenant_id', $customer->tenant_id)
            ->where('is_active', true)
            ->where('min_packages_collected', '<=', $collected)
            ->orderByDesc('min_packages_collected')
            ->first();

        if ($tier && $customer->pricing_tier_id !== $tier->id) {
            $customer->update(['pricing_tier_id' => $tier->id]);
        }
    }

    public function isEnabled(int $tenantId): bool
    {
        return (bool) (int) TenantSetting::getValue($tenantId, 'loyalty_enabled', 0);
    }

    public function pointsBalance(Customer $customer): float
    {
        return (float) $this->pointsTransactions($customer)
            ->where('status', 'approved')
            ->selectRaw("coalesce(sum(case when transaction_type = 'credit' then amount else -amount end), 0) as balance")
            ->value('balance');
    }

    public function cashBalance(Customer $customer): float
    {
        return (float) $this->cashTransactions($customer)
            ->where('status', 'approved')
            ->selectRaw("coalesce(sum(case when transaction_type = 'credit' then amount else -amount end), 0) as balance")
            ->value('balance');
    }

    public function rewardTransactions(Customer $customer)
    {
        return $this->pointsTransactions($customer)
            ->where('status', 'approved');
    }

    public function debitCash(Customer $customer, float $amount, string $sourceType, int $referenceId, string $description): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($customer, $amount, $sourceType, $referenceId, $description) {
            $locked = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = $this->cashBalance($locked);
            if ($balanceBefore + 0.001 < $amount) {
                throw new \RuntimeException('Insufficient customer wallet balance.');
            }

            WalletTransaction::create([
                'tenant_id' => $locked->tenant_id,
                'customer_id' => $locked->id,
                'transaction_type' => 'debit',
                'source_type' => $sourceType,
                'reference_id' => $referenceId,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => round($balanceBefore - $amount, 2),
                'description' => $description,
                'status' => 'approved',
                'payment_method' => 'wallet',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });
    }

    public function creditCash(Customer $customer, float $amount, string $sourceType, ?int $referenceId, string $description, string $paymentMethod = 'courier_credit'): void
    {
        if ($amount <= 0) {
            return;
        }

        $locked = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
        $balanceBefore = $this->cashBalance($locked);

        WalletTransaction::create([
            'tenant_id' => $locked->tenant_id,
            'customer_id' => $locked->id,
            'transaction_type' => 'credit',
            'source_type' => $sourceType,
            'reference_id' => $referenceId,
            'amount' => round($amount, 2),
            'balance_before' => $balanceBefore,
            'balance_after' => round($balanceBefore + $amount, 2),
            'description' => $description,
            'status' => 'approved',
            'payment_method' => $paymentMethod,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    public function ensureReferralCode(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        do {
            $base = strtoupper(Str::substr(preg_replace('/[^A-Z0-9]/i', '', $user->full_name ?: 'REF'), 0, 4)) ?: 'REF';
            $code = $base.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT).Str::upper(Str::random(2));
        } while (User::where('referral_code', $code)->whereKeyNot($user->id)->exists());

        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    public function awardPackageCollected(Package $package): void
    {
        if (! $package->customer || ! $this->isEnabled($package->tenant_id)) {
            return;
        }

        $points = (float) TenantSetting::getValue($package->tenant_id, 'loyalty_package_points', 0);
        if ($points <= 0) {
            return;
        }

        $this->creditOnce(
            $package->customer,
            'reward',
            $package->id,
            $points,
            'Package collected reward for '.$package->tracking_number
        );
    }

    public function awardInvoicePaid(Invoice $invoice): void
    {
        if (! $invoice->customer || ! $this->isEnabled($invoice->tenant_id)) {
            return;
        }

        $perAmount = (float) TenantSetting::getValue($invoice->tenant_id, 'loyalty_invoice_points_per_amount', 0);
        $amountStep = (float) TenantSetting::getValue($invoice->tenant_id, 'loyalty_invoice_amount_step_jmd', 1000);
        if ($perAmount <= 0 || $amountStep <= 0) {
            return;
        }

        $points = floor(((float) $invoice->total_jmd / $amountStep) * $perAmount);
        if ($points <= 0) {
            return;
        }

        $this->creditOnce(
            $invoice->customer,
            'invoice_payment',
            $invoice->id,
            $points,
            'Invoice payment reward for '.$invoice->invoice_number
        );
    }

    public function awardSignup(Customer $customer): void
    {
        if (! $this->isEnabled($customer->tenant_id)) {
            return;
        }

        $points = (float) TenantSetting::getValue($customer->tenant_id, 'loyalty_signup_points', 0);
        if ($points > 0) {
            $this->creditOnce($customer, 'manual', $customer->id, $points, 'New customer signup reward');
        }
    }

    public function debitPoints(Customer $customer, float $points, string $sourceType, int $referenceId, string $description): void
    {
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($customer, $points, $sourceType, $referenceId, $description) {
            $locked = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = $this->pointsBalance($locked);
            if ($balanceBefore < $points) {
                throw new \RuntimeException('Insufficient reward points.');
            }

            $balanceAfter = $balanceBefore - $points;

            WalletTransaction::create([
                'tenant_id' => $locked->tenant_id,
                'customer_id' => $locked->id,
                'transaction_type' => 'debit',
                'source_type' => $sourceType,
                'reference_id' => $referenceId,
                'amount' => $points,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $locked->decrement('total_points', $points);
        });
    }

    public function completeReferral(Customer $referrer, Customer $referred): void
    {
        if ($referrer->tenant_id !== $referred->tenant_id || ! $this->isEnabled($referrer->tenant_id)) {
            return;
        }

        $points = (float) TenantSetting::getValue($referrer->tenant_id, 'loyalty_referral_points', 0);

        $referral = Referral::firstOrCreate([
            'tenant_id' => $referrer->tenant_id,
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
        ], [
            'points_earned' => $points,
            'status' => 'pending',
        ]);

        if ($referral->status === 'completed') {
            return;
        }

        if ($points > 0) {
            $this->creditOnce($referrer, 'referral', $referral->id, $points, 'Referral reward for '.$referred->mailbox);
            $referrer->increment('total_referral_points', $points);
        }

        $referral->update([
            'points_earned' => $points,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    private function creditOnce(Customer $customer, string $sourceType, int $referenceId, float $points, string $description): void
    {
        if (WalletTransaction::where('customer_id', $customer->id)
            ->where('source_type', $sourceType)
            ->where('reference_id', $referenceId)
            ->where('transaction_type', 'credit')
            ->exists()) {
            return;
        }

        DB::transaction(function () use ($customer, $sourceType, $referenceId, $points, $description) {
            $locked = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = $this->pointsBalance($locked);
            $balanceAfter = $balanceBefore + $points;

            WalletTransaction::create([
                'tenant_id' => $locked->tenant_id,
                'customer_id' => $locked->id,
                'transaction_type' => 'credit',
                'source_type' => $sourceType,
                'reference_id' => $referenceId,
                'amount' => $points,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            $locked->increment('total_points', $points);
        });
    }

    private function cashTransactions(Customer $customer)
    {
        return $customer->walletTransactions()->where(function ($query) {
            $query->whereNotNull('payment_method')
                ->orWhere(function ($manual) {
                    $manual->where('source_type', 'manual')
                        ->whereNotNull('approved_by');
                });
        })->when(
            \Illuminate\Support\Facades\Schema::hasColumn('wallet_transactions', 'converted_to_points'),
            fn ($query) => $query->where('converted_to_points', false)
        );
    }

    private function pointsTransactions(Customer $customer)
    {
        return $customer->walletTransactions()
            ->whereNull('payment_method')
            ->where(function ($query) {
                $query->where('source_type', '!=', 'manual')
                    ->orWhereNull('approved_by');
            });
    }
}
