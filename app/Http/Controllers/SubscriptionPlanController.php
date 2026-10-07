<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->paginate($this->perPage(request(), 15));
        return view('superadmin.plans.index', compact('plans'));
    }

    public function create()
    {
        $modules = SubscriptionPlan::MODULES;

        return view('superadmin.plans.create', compact('modules'));
    }

    public function show(SubscriptionPlan $plan)
    {
        $plan->loadCount('tenantSubscriptions');

        return view('superadmin.plans.show', compact('plan'));
    }

    public function store(Request $request)
    {
        $data = $this->planData($request);
        unset($data['modules']);
        $data['features'] = $this->featuresJson($request);
        SubscriptionPlan::create($data);

        return redirect()->route('superadmin.plans.index')->with('success', 'Plan created successfully.');
    }

    public function edit(SubscriptionPlan $plan)
    {
        $modules = SubscriptionPlan::MODULES;

        return view('superadmin.plans.edit', compact('plan', 'modules'));
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $data = $this->planData($request);
        unset($data['modules']);
        $data['features'] = $this->featuresJson($request);
        $plan->update($data);

        return redirect()->route('superadmin.plans.index')->with('success', 'Plan updated successfully.');
    }

    public function destroy(SubscriptionPlan $plan)
    {
        $plan->delete();
        return redirect()->route('superadmin.plans.index')->with('success', 'Plan deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'billing_model' => 'required|in:subscription,per_package,hybrid',
            'per_package_base_fee_jmd' => 'nullable|numeric|min:0',
            'package_service_fee_jmd' => 'nullable|numeric|min:0',
            'max_users' => 'nullable|integer|min:0',
            'max_customers' => 'nullable|integer|min:0',
            'max_packages_month' => 'nullable|integer|min:0',
            'max_outlets' => 'nullable|integer|min:0',
            'max_storage_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'modules' => 'nullable|array',
            'modules.*' => 'string',
        ]);
    }

    private function planData(Request $request): array
    {
        $data = $this->validated($request);

        foreach (['monthly_price', 'yearly_price', 'per_package_base_fee_jmd', 'package_service_fee_jmd'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $data[$field] === null ? ($field === 'yearly_price' ? null : 0) : $data[$field];
            }
        }

        foreach (['max_users', 'max_customers', 'max_packages_month', 'max_outlets'] as $field) {
            $data[$field] = $data[$field] ?? null;
        }

        $data['billing_model'] = $data['billing_model'] ?? 'subscription';
        $data['per_package_base_fee_jmd'] = $data['per_package_base_fee_jmd'] ?? 0;
        $data['package_service_fee_jmd'] = $data['package_service_fee_jmd'] ?? 0;
        $data['max_storage_days'] = $data['max_storage_days'] ?? 30;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }

    private function featuresJson(Request $request): array
    {
        $features = collect(explode("\n", (string) $request->input('feature_lines')))
            ->map(fn ($feature) => trim($feature))
            ->filter()
            ->values()
            ->all();

        $modules = collect((array) $request->input('modules', []))
            ->filter(fn ($module) => array_key_exists($module, SubscriptionPlan::MODULES))
            ->values()
            ->all();

        return ['features' => $features, 'modules' => $modules];
    }
}
