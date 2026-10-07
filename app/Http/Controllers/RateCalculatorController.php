<?php

namespace App\Http\Controllers;

use App\Models\DeliveryArea;
use App\Models\HsCode;
use App\Models\Outlet;
use App\Models\ShippingMode;
use App\Models\TenantSetting;
use App\Models\WeightRate;
use Illuminate\Http\Request;

class RateCalculatorController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $customer = $user->user_type === 'customer' ? $user->customer : null;
        $weight = (float) $request->input('weight', 0);
        $seaVolume = (float) $request->input('sea_volume_cuft', 0);
        $length = (float) $request->input('length_in', 0);
        $width = (float) $request->input('width_in', 0);
        $height = (float) $request->input('height_in', 0);
        $freightType = ShippingMode::normalizeCode((string) $request->input('freight_type', ''));
        $deliveryType = $request->input('delivery_type', 'pickup');
        $outletId = $request->integer('outlet_id') ?: optional($customer)->outlet_id ?: null;
        $deliveryAreaId = $request->integer('delivery_area_id') ?: null;
        $localDistanceKm = (float) $request->input('local_distance_km', 0);
        $pickupLocation = trim((string) $request->input('pickup_location', ''));
        $deliveryLocation = trim((string) $request->input('delivery_location', ''));
        $declaredValue = (float) $request->input('declared_value', 0);
        $accountType = $request->input('account_type', 'personal');
        $cafType = $request->input('caf_type', 'residential');
        $selectedHsCodeId = $request->integer('hs_code_id') ?: null;
        $selectedHsCode = null;
        $result = null;
        $calculationError = null;
        $billingWeightMode = $this->billingWeightMode($tenantId);

        ShippingMode::ensureDefaultsForTenant($tenantId);
        $shippingModes = ShippingMode::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $selectedShippingMode = $shippingModes->firstWhere('code', $freightType) ?: $shippingModes->first();
        $freightType = optional($selectedShippingMode)->code ?: 'air';
        $freightCategory = optional($selectedShippingMode)->category ?: 'air';

        $outlets = Outlet::where('tenant_id', $tenantId)->orderByDesc('is_main')->orderBy('name')->get();
        $deliveryAreas = DeliveryArea::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get();
        $hsCodes = HsCode::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('code')->get();
        $customsConfig = $this->customsConfig($tenantId);
        $destinationCountry = TenantSetting::getValue($tenantId, 'destination_country_code', 'JA');

        if ($selectedHsCodeId) {
            $selectedHsCode = $hsCodes->firstWhere('id', $selectedHsCodeId);
        }

        if ($freightCategory === 'sea' && $seaVolume <= 0 && $length > 0 && $width > 0 && $height > 0) {
            $seaVolume = round(($length * $width * $height) / 1728, 2);
        }

        $enteredMeasure = $freightCategory === 'sea' ? $seaVolume : $weight;
        $billableMeasure = $freightCategory === 'sea'
            ? $enteredMeasure
            : $this->billableWeight($enteredMeasure, $billingWeightMode);

        if ($freightCategory === 'local') {
            if ($deliveryAreaId) {
                $area = $deliveryAreas->firstWhere('id', $deliveryAreaId);
                if (! $area) {
                    $calculationError = 'Choose a valid active delivery rate.';
                } else {
                    $distance = ($area->pricing_mode === 'distance' && $localDistanceKm > 0) ? $localDistanceKm : 0;
                    $base = round((float) $area->base_cost + ($area->pricing_mode === 'distance' ? $distance * (float) $area->per_km_cost : 0), 2);
                    $result = [
                        'range_price' => (float) $area->base_cost,
                        'base' => $base,
                        'processing_fee' => 0,
                        'handling_fee' => 0,
                        'entered_measure' => $distance,
                        'billable_measure' => $distance,
                        'billing_weight_mode' => $billingWeightMode,
                        'rate_unit' => $area->pricing_mode === 'distance' ? 'km' : 'fixed',
                        'customs_applies' => false,
                        'customs_value' => 0,
                        'duty' => 0,
                        'duty_rate' => 0,
                        'hs_code' => null,
                        'insurance' => 0,
                        'scf' => 0,
                        'envl' => 0,
                        'caf' => 0,
                        'stamp_duty' => 0,
                        'gct' => 0,
                        'customs' => 0,
                        'total' => $base,
                        'delivery_area' => $area,
                        'pickup_location' => $pickupLocation,
                        'delivery_location' => $deliveryLocation,
                    ];
                }
            } elseif ($request->hasAny(['delivery_area_id', 'pickup_location', 'delivery_location', 'local_distance_km'])) {
                $calculationError = 'Choose a delivery rate/zone for local delivery.';
            }
        } elseif (($freightCategory === 'sea' && $billableMeasure > 0) || ($freightCategory !== 'sea' && $request->filled('weight'))) {
            $rate = $this->matchingRate($tenantId, $freightType, $freightCategory, $billableMeasure, $outletId, optional($customer)->pricing_tier_id);

            if (! $rate) {
                $calculationError = 'No active rate is configured for '.number_format($billableMeasure, 2).' '.($freightCategory === 'sea' ? 'cu.ft' : 'lb').'. Add a matching range in Rates Management.';
            } else {
                $rangePrice = $rate->unitRateFor($deliveryType);
                $base = $rate->priceFor($billableMeasure, $deliveryType);
                $displayBillableMeasure = $rate->billableMeasure($billableMeasure);
                $processingFee = round((float) ($rate->processing_fee_jmd ?? 0), 2);
                $handlingFee = 0;
                $customsValue = $declaredValue * $customsConfig['exchange_rate'];
                $customsApplies = $declaredValue > $customsConfig['de_minimis_usd'];
                $dutyRate = $selectedHsCode ? ((float) $selectedHsCode->duty_rate / 100) : $customsConfig['duty_rate'];
                $duty = $customsApplies ? $customsValue * $dutyRate : 0;
                $insurance = $customsApplies ? $customsValue * $customsConfig['insurance_rate'] : 0;
                $scf = $customsApplies ? $customsValue * $customsConfig['scf_rate'] : 0;
                $envl = $customsApplies ? $customsValue * $customsConfig['envl_rate'] : 0;
                $caf = $customsApplies ? ($cafType === 'commercial' ? $customsConfig['caf_commercial'] : $customsConfig['caf_residential']) : 0;
                $stampDuty = $customsApplies ? $customsConfig['stamp_duty_jmd'] : 0;
                $gctRate = $accountType === 'company' ? $customsConfig['gct_company'] : $customsConfig['gct_personal'];
                $gctBase = $customsValue + $duty + $insurance + $scf + $envl + $caf + $stampDuty;
                $gct = $customsApplies ? $gctBase * $gctRate : 0;
                $customs = $duty + $insurance + $scf + $envl + $caf + $stampDuty + $gct;

                $result = [
                    'range_price' => $rangePrice,
                    'base' => $base,
                    'processing_fee' => $processingFee,
                    'handling_fee' => $handlingFee,
                    'entered_measure' => $enteredMeasure,
                    'billable_measure' => $displayBillableMeasure,
                    'billing_weight_mode' => $billingWeightMode,
                    'rate_unit' => $rate->unitLabel(),
                    'entered_cuft' => $freightCategory === 'sea' ? $seaVolume : null,
                    'entered_cbm' => $freightCategory === 'sea' ? $seaVolume / WeightRate::CBM_TO_CUFT : null,
                    'customs_applies' => $customsApplies,
                    'customs_value' => $customsApplies ? $customsValue : 0,
                    'duty' => $duty,
                    'duty_rate' => $dutyRate,
                    'hs_code' => $selectedHsCode,
                    'insurance' => $insurance,
                    'scf' => $scf,
                    'envl' => $envl,
                    'caf' => $caf,
                    'stamp_duty' => $stampDuty,
                    'gct_rate' => $gctRate,
                    'gct' => $gct,
                    'customs' => $customs,
                    'total' => $base + $processingFee + $handlingFee + $customs,
                ];
            }
        }

        return view('shared.calculator', compact('weight', 'seaVolume', 'length', 'width', 'height', 'freightType', 'freightCategory', 'shippingModes', 'deliveryType', 'declaredValue', 'result', 'calculationError', 'billingWeightMode', 'outlets', 'outletId', 'deliveryAreas', 'deliveryAreaId', 'localDistanceKm', 'pickupLocation', 'deliveryLocation', 'accountType', 'cafType', 'customsConfig', 'hsCodes', 'selectedHsCodeId', 'selectedHsCode', 'destinationCountry'));
    }

    private function customsConfig(int $tenantId): array
    {
        $defaults = [
            'customs_exchange_rate' => 158.5,
            'customs_gct_company_rate' => 25,
            'customs_gct_personal_rate' => 15,
            'customs_caf_residential_jmd' => 2500,
            'customs_caf_commercial_jmd' => 5000,
            'customs_de_minimis_usd' => 100,
            'customs_default_duty_rate' => 20,
            'customs_insurance_rate' => 1,
            'customs_scf_rate' => 0,
            'customs_envl_rate' => 1,
            'customs_stamp_duty_jmd' => 100,
        ];
        $settings = TenantSetting::where('tenant_id', $tenantId)
            ->whereIn('key', array_keys($defaults))
            ->pluck('value', 'key')
            ->all();
        $settings = array_merge($defaults, $settings);

        return [
            'exchange_rate' => (float) $settings['customs_exchange_rate'],
            'gct_company' => (float) $settings['customs_gct_company_rate'] / 100,
            'gct_personal' => (float) $settings['customs_gct_personal_rate'] / 100,
            'caf_residential' => (float) $settings['customs_caf_residential_jmd'],
            'caf_commercial' => (float) $settings['customs_caf_commercial_jmd'],
            'de_minimis_usd' => (float) $settings['customs_de_minimis_usd'],
            'duty_rate' => (float) $settings['customs_default_duty_rate'] / 100,
            'insurance_rate' => (float) $settings['customs_insurance_rate'] / 100,
            'scf_rate' => (float) $settings['customs_scf_rate'] / 100,
            'envl_rate' => (float) $settings['customs_envl_rate'] / 100,
            'stamp_duty_jmd' => (float) $settings['customs_stamp_duty_jmd'],
        ];
    }

    private function rateRangeClause(string $freightCategory, float $measure): \Closure
    {
        return function ($query) use ($freightCategory, $measure) {
            if ($freightCategory !== 'sea') {
                $query->where('min_weight', '<=', $measure)
                    ->where(fn ($range) => $range->whereNull('max_weight')->orWhere('max_weight', '>=', $measure));
                return;
            }

            $cbm = $measure / WeightRate::CBM_TO_CUFT;
            $query->where(function ($unitQuery) use ($measure, $cbm) {
                $unitQuery->where(function ($cuftQuery) use ($measure) {
                    $cuftQuery->where(fn ($unit) => $unit->whereNull('volume_unit')->orWhere('volume_unit', 'cuft'))
                        ->where('min_weight', '<=', $measure)
                        ->where(fn ($range) => $range->whereNull('max_weight')->orWhere('max_weight', '>=', $measure));
                })->orWhere(function ($cbmQuery) use ($cbm) {
                    $cbmQuery->where('volume_unit', 'cbm')
                        ->where('min_weight', '<=', $cbm)
                        ->where(fn ($range) => $range->whereNull('max_weight')->orWhere('max_weight', '>=', $cbm));
                });
            });
        };
    }

    private function finalContinuousRateClause(string $freightCategory, float $measure): \Closure
    {
        return function ($query) use ($freightCategory, $measure) {
            if ($freightCategory !== 'sea') {
                $query->where('min_weight', '<=', $measure);
                return;
            }

            $cbm = $measure / WeightRate::CBM_TO_CUFT;
            $query->where(function ($unitQuery) use ($measure, $cbm) {
                $unitQuery->where(function ($cuftQuery) use ($measure) {
                    $cuftQuery->where(fn ($unit) => $unit->whereNull('volume_unit')->orWhere('volume_unit', 'cuft'))
                        ->where('min_weight', '<=', $measure);
                })->orWhere(function ($cbmQuery) use ($cbm) {
                    $cbmQuery->where('volume_unit', 'cbm')
                        ->where('min_weight', '<=', $cbm);
                });
            });
        };
    }
    private function matchingRate(int $tenantId, string $freightType, string $freightCategory, float $billableMeasure, ?int $outletId, ?int $pricingTierId): ?WeightRate
    {
        $useOutletRates = false;

        if ($outletId) {
            $useOutletRates = Outlet::where('tenant_id', $tenantId)
                ->whereKey($outletId)
                ->where('rate_group_mode', 'custom')
                ->exists();
        }

        $baseQuery = fn () => WeightRate::where('tenant_id', $tenantId)
            ->where('freight_type', $freightType)
            ->where('is_active', true)
            ->where(function ($query) use ($outletId, $useOutletRates) {
                if ($outletId && $useOutletRates) {
                    $query->where('outlet_id', $outletId)->orWhereNull('outlet_id');
                    return;
                }

                $query->whereNull('outlet_id');
            })
            ->where(function ($query) use ($pricingTierId) {
                if ($pricingTierId) {
                    $query->where('pricing_tier_id', $pricingTierId)->orWhereNull('pricing_tier_id');
                    return;
                }

                $query->whereNull('pricing_tier_id');
            })
            ->when(
                $outletId && $useOutletRates,
                fn ($query) => $query->orderByRaw('CASE WHEN outlet_id = ? THEN 0 WHEN outlet_id IS NULL THEN 1 ELSE 2 END', [$outletId])
            )
            ->orderByRaw('pricing_tier_id is null');

        $rate = $baseQuery()
            ->where($this->rateRangeClause($freightCategory, $billableMeasure))
            ->orderBy('min_weight')
            ->first();

        return $rate;
    }

    private function billingWeightMode(int $tenantId): string
    {
        $mode = TenantSetting::getValue($tenantId, 'billing_weight_mode', 'actual');

        return in_array($mode, ['actual', 'round_up', 'round_down'], true) ? $mode : 'actual';
    }

    private function billableWeight(float $weight, string $mode): float
    {
        if ($weight <= 0) {
            return 0;
        }

        return match ($mode) {
            'round_up' => ceil($weight),
            'round_down' => max(floor($weight), 1),
            default => $weight,
        };
    }
}







