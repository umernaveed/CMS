<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Outlet;
use App\Models\PricingTier;
use App\Models\ShippingMode;
use App\Models\WeightRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class WeightRateController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;
        ShippingMode::ensureDefaultsForTenant($tenantId);
        $rates = WeightRate::where('tenant_id', $tenantId)
            ->with('pricingTier')
            ->with('outlet')
            ->orderBy('freight_type')
            ->orderBy('min_weight')
            ->paginate($this->perPage(request(), 15));
        $shippingModes = $this->shippingModes();

        return view('courier.weights.index', compact('rates', 'shippingModes'));
    }

    public function create()
    {
        return view('courier.weights.create', ['rate' => null, 'tiers' => $this->tiers(), 'outlets' => $this->outlets(), 'shippingModes' => $this->shippingModes()]);
    }

    public function createMode()
    {
        return view('courier.weights.create-mode');
    }

    public function show(WeightRate $weight)
    {
        abort_unless($weight->tenant_id === auth()->user()->tenant_id, 404);
        $weight->load(['pricingTier', 'outlet']);

        return view('courier.weights.show', ['rate' => $weight]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $rate = WeightRate::create(array_merge($data, ['tenant_id' => auth()->user()->tenant_id]));
        ActivityLog::record('created', 'weights', 'Created weight rate '.$rate->min_weight.'-'.$rate->max_weight.' lb');

        return redirect()->route('courier.weights.index')->with('success', 'Weight rate created successfully.');
    }

    public function edit(WeightRate $weight)
    {
        abort_unless($weight->tenant_id === auth()->user()->tenant_id, 404);

        return view('courier.weights.edit', ['rate' => $weight, 'tiers' => $this->tiers(), 'outlets' => $this->outlets(), 'shippingModes' => $this->shippingModes()]);
    }

    public function update(Request $request, WeightRate $weight)
    {
        abort_unless($weight->tenant_id === auth()->user()->tenant_id, 404);
        $weight->update($this->validatedData($request));
        ActivityLog::record('updated', 'weights', 'Updated weight rate '.$weight->min_weight.'-'.$weight->max_weight.' lb');

        return redirect()->route('courier.weights.index')->with('success', 'Weight rate updated successfully.');
    }

    public function destroy(WeightRate $weight)
    {
        abort_unless($weight->tenant_id === auth()->user()->tenant_id, 404);
        $description = 'Deleted weight rate '.$weight->min_weight.'-'.$weight->max_weight.' lb';
        $weight->delete();
        ActivityLog::record('deleted', 'weights', $description);

        return redirect()->route('courier.weights.index')->with('success', 'Weight rate deleted successfully.');
    }

    public function storeMode(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        ShippingMode::ensureDefaultsForTenant($tenantId);
        $data = $this->validatedModeData($request, $tenantId);

        $mode = ShippingMode::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'code' => $data['code'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'icon' => $this->iconForCategory($data['category']),
            'sort_order' => $data['sort_order'] ?? 100,
            'is_active' => (bool) $data['is_active'],
            'is_default' => (bool) $data['is_default'],
        ]);
        $this->syncCategoryDefault($mode);
        ActivityLog::record('created', 'weights', 'Created shipping mode '.$mode->name);

        return redirect()->to(route('courier.weights.index').'#modes-panel')->with('success', 'Shipping mode created. Add rate ranges for it below.');
    }

    public function updateMode(Request $request, ShippingMode $shippingMode)
    {
        $tenantId = auth()->user()->tenant_id;
        abort_unless($shippingMode->tenant_id === $tenantId, 404);
        $oldCode = $shippingMode->code;
        $data = $this->validatedModeData($request, $tenantId, $shippingMode);

        DB::transaction(function () use ($shippingMode, $tenantId, $oldCode, $data) {
            $shippingMode->update([
                'name' => $data['name'],
                'code' => $data['code'],
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
                'icon' => $this->iconForCategory($data['category']),
                'sort_order' => $data['sort_order'] ?? $shippingMode->sort_order,
                'is_active' => (bool) $data['is_active'],
                'is_default' => (bool) $data['is_default'],
            ]);

            if ($oldCode !== $shippingMode->code) {
                $this->replaceFreightTypeReferences($tenantId, $oldCode, $shippingMode->code);
            }

            $this->syncCategoryDefault($shippingMode->fresh());
        });
        ActivityLog::record('updated', 'weights', 'Updated shipping mode '.$shippingMode->name);

        return redirect()->to(route('courier.weights.index').'#modes-panel')->with('success', 'Shipping mode updated.');
    }

    public function destroyMode(ShippingMode $shippingMode)
    {
        $tenantId = auth()->user()->tenant_id;
        abort_unless($shippingMode->tenant_id === $tenantId, 404);
        $replacement = ShippingMode::where('tenant_id', $tenantId)
            ->where('id', '<>', $shippingMode->id)
            ->where('category', $shippingMode->category)
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();
        $hasReferences = $this->freightTypeHasReferences($tenantId, $shippingMode->code);
        abort_if($hasReferences && ! $replacement, 422, 'Create another '.$shippingMode->category.' shipping mode before deleting this referenced mode.');

        $name = $shippingMode->name;
        DB::transaction(function () use ($shippingMode, $tenantId, $replacement) {
            if ($replacement) {
                $this->replaceFreightTypeReferences($tenantId, $shippingMode->code, $replacement->code);
            }

            $wasDefault = (bool) $shippingMode->is_default;
            $category = $shippingMode->category;
            $shippingMode->delete();

            if ($wasDefault && $replacement) {
                $replacement->forceFill(['is_default' => true])->save();
                $this->syncCategoryDefault($replacement->fresh());
            } elseif ($wasDefault) {
                $newDefault = ShippingMode::where('tenant_id', $tenantId)
                    ->where('category', $category)
                    ->orderByDesc('is_active')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->first();
                if ($newDefault) {
                    $newDefault->forceFill(['is_default' => true])->save();
                    $this->syncCategoryDefault($newDefault);
                }
            }
        });
        ActivityLog::record('deleted', 'weights', 'Deleted shipping mode '.$name);

        return redirect()->to(route('courier.weights.index').'#modes-panel')->with('success', 'Shipping mode deleted.');
    }
    private function validatedModeData(Request $request, int $tenantId, ?ShippingMode $shippingMode = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'nullable|string|max:80',
            'category' => 'required|in:air,sea,local',
            'description' => 'nullable|string|max:160',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]) + ['is_active' => false, 'is_default' => false];

        $data['code'] = ShippingMode::normalizeCode($data['code'] ?: $data['name']);
        $duplicate = ShippingMode::where('tenant_id', $tenantId)->where('code', $data['code'])
            ->when($shippingMode, fn ($query) => $query->whereKeyNot($shippingMode->id))
            ->exists();
        abort_if($duplicate, 422, 'A shipping mode with this code already exists.');

        return $data;
    }

    private function syncCategoryDefault(ShippingMode $mode): void
    {
        if ($mode->is_default) {
            ShippingMode::where('tenant_id', $mode->tenant_id)
                ->where('category', $mode->category)
                ->whereKeyNot($mode->id)
                ->update(['is_default' => false]);

            return;
        }

        $hasDefault = ShippingMode::where('tenant_id', $mode->tenant_id)
            ->where('category', $mode->category)
            ->where('is_default', true)
            ->exists();

        if (! $hasDefault) {
            $mode->forceFill(['is_default' => true])->save();
        }
    }

    private function freightTypeHasReferences(int $tenantId, string $code): bool
    {
        foreach ($this->freightTypeReferenceTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'freight_type')) {
                continue;
            }

            $query = DB::table($table)->where('freight_type', $code);
            if (Schema::hasColumn($table, 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    private function replaceFreightTypeReferences(int $tenantId, string $fromCode, string $toCode): void
    {
        if ($fromCode === $toCode) {
            return;
        }

        foreach ($this->freightTypeReferenceTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'freight_type')) {
                continue;
            }

            $query = DB::table($table)->where('freight_type', $fromCode);
            if (Schema::hasColumn($table, 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }

            $query->update(['freight_type' => $toCode]);
        }
    }

    private function freightTypeReferenceTables(): array
    {
        return ['weight_rates', 'packages', 'invoice_items', 'pre_alerts'];
    }
    private function validatedData(Request $request): array
    {
        $tenantId = auth()->user()->tenant_id;
        ShippingMode::ensureDefaultsForTenant($tenantId);
        $data = $request->validate([
            'min_weight' => 'required|numeric|min:0',
            'outlet_id' => 'nullable|exists:outlets,id',
            'pricing_tier_id' => 'nullable|exists:pricing_tiers,id',
            'region' => 'nullable|string|max:120',
            'volume_unit' => 'nullable|in:cuft,cbm',
            'max_weight' => 'nullable|numeric|gte:min_weight',
            'minimum_billable_measure' => 'nullable|numeric|min:0',
            'processing_fee_jmd' => 'nullable|numeric|min:0',
            'price_jmd' => 'required|numeric|min:0',
            'express_price_jmd' => 'nullable|numeric|min:0',
            'freight_type' => ['required', Rule::exists('shipping_modes', 'code')->where('tenant_id', $tenantId)],
            'is_active' => 'nullable|boolean',
        ]) + ['is_active' => false];

        $mode = ShippingMode::where('tenant_id', $tenantId)->where('code', $data['freight_type'])->firstOrFail();
        $data['express_price_jmd'] = $data['express_price_jmd'] ?? 0;
        $data['max_weight'] = $data['max_weight'] ?? null;
        $data['minimum_billable_measure'] = $data['minimum_billable_measure'] ?? $data['min_weight'];
        $data['processing_fee_jmd'] = $data['processing_fee_jmd'] ?? 0;
        $data['volume_unit'] = $mode->isSea() ? ($data['volume_unit'] ?? 'cuft') : 'cuft';
        $region = strtolower(trim((string) ($data['region'] ?? '')));
        $data['region'] = $region === '' ? null : (in_array($region, ['us', 'usa'], true) ? 'USA' : (in_array($region, ['cn', 'china'], true) ? 'China' : trim((string) $data['region'])));
        if (! empty($data['outlet_id'])) {
            abort_unless(Outlet::where('tenant_id', $tenantId)->whereKey($data['outlet_id'])->where('rate_group_mode', 'custom')->exists(), 422);
        }
        if (! empty($data['pricing_tier_id'])) {
            abort_unless(PricingTier::where('tenant_id', $tenantId)->whereKey($data['pricing_tier_id'])->exists(), 422);
        }

        return $data;
    }

    private function tiers()
    {
        return PricingTier::where('tenant_id', auth()->user()->tenant_id)->where('is_active', true)->orderBy('name')->get();
    }

    private function outlets()
    {
        return Outlet::where('tenant_id', auth()->user()->tenant_id)
            ->where('rate_group_mode', 'custom')
            ->orderBy('name')
            ->get();
    }

    private function shippingModes()
    {
        $tenantId = auth()->user()->tenant_id;
        ShippingMode::ensureDefaultsForTenant($tenantId);

        return ShippingMode::where('tenant_id', $tenantId)->orderBy('category')->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name')->get();
    }

    private function iconForCategory(string $category): string
    {
        return match ($category) {
            'sea' => 'fas fa-ship',
            'local' => 'fas fa-truck',
            default => 'fas fa-plane',
        };
    }
}





