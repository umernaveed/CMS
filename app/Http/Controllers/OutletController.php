<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\SubscriptionLimitService;

class OutletController extends Controller
{
    public function index()
    {
        $outlets = Outlet::where('tenant_id', auth()->user()->tenant_id)
            ->withCount(['customers', 'packages'])
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->paginate($this->perPage(request(), 15));

        return view('courier.outlets.index', compact('outlets'));
    }

    public function create()
    {
        return view('courier.outlets.create', ['outlet' => null]);
    }

    public function show(Outlet $outlet)
    {
        $this->authorizeTenant($outlet);
        $outlet->loadCount(['customers', 'packages']);
        $staff = User::where('tenant_id', auth()->user()->tenant_id)
            ->where('outlet_id', $outlet->id)
            ->whereIn('user_type', ['tenant_admin', 'staff'])
            ->orderBy('full_name')
            ->get();

        return view('courier.outlets.show', compact('outlet', 'staff'));
    }

    public function store(Request $request, SubscriptionLimitService $limits)
    {
        $tenantId = auth()->user()->tenant_id;
        $data = $request->validate([
            'outlet_code' => 'required|string|max:50|unique:outlets,outlet_code,NULL,id,tenant_id,' . $tenantId,
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'parish' => 'nullable|string|max:100',
            'manager_name' => 'nullable|string|max:255',
            'rate_group_mode' => 'required|in:main,custom',
            'is_main' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'staff_name' => 'nullable|string|max:255',
            'staff_email' => 'nullable|email|unique:users,email',
            'staff_password' => 'nullable|string|min:8',
        ]);

        $isMain = ! empty($data['is_main']);

        if ($isMain) {
            Outlet::where('tenant_id', $tenantId)->update(['is_main' => false]);
        }

        $limits->ensureCanCreate($tenantId, 'outlets');
        if (! empty($data['staff_email']) && ! empty($data['staff_password'])) {
            $limits->ensureCanCreate($tenantId, 'users');
        }

        $outlet = Outlet::create(array_merge($data, [
            'tenant_id' => $tenantId,
            'rate_group_mode' => $isMain ? 'main' : $data['rate_group_mode'],
            'is_main' => $isMain,
            'is_active' => $data['is_active'] ?? true,
        ]));

        if (! empty($data['staff_email']) && ! empty($data['staff_password'])) {
            User::create([
                'tenant_id' => $tenantId,
                'outlet_id' => $outlet->id,
                'user_type' => 'staff',
                'full_name' => $data['staff_name'] ?: $outlet->name . ' Staff',
                'email' => $data['staff_email'],
                'phone' => $outlet->phone,
                'password' => Hash::make($data['staff_password']),
                'is_active' => true,
            ]);
        }

        return redirect()->route('courier.outlets.index')->with('success', 'Outlet created successfully.');
    }

    public function edit(Outlet $outlet)
    {
        $this->authorizeTenant($outlet);
        return view('courier.outlets.edit', compact('outlet'));
    }

    public function update(Request $request, Outlet $outlet)
    {
        $this->authorizeTenant($outlet);
        $tenantId = auth()->user()->tenant_id;
        $data = $request->validate([
            'outlet_code' => 'required|string|max:50|unique:outlets,outlet_code,' . $outlet->id . ',id,tenant_id,' . $tenantId,
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'parish' => 'nullable|string|max:100',
            'manager_name' => 'nullable|string|max:255',
            'rate_group_mode' => 'required|in:main,custom',
            'is_main' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $isMain = ! empty($data['is_main']);

        if ($isMain) {
            Outlet::where('tenant_id', $tenantId)->where('id', '!=', $outlet->id)->update(['is_main' => false]);
        }

        $outlet->update(array_merge($data, [
            'rate_group_mode' => $isMain ? 'main' : $data['rate_group_mode'],
            'is_main' => $isMain,
            'is_active' => $data['is_active'] ?? false,
        ]));

        return redirect()->route('courier.outlets.index')->with('success', 'Outlet updated successfully.');
    }

    public function destroy(Outlet $outlet)
    {
        $this->authorizeTenant($outlet);
        abort_if($outlet->is_main, 422, 'Main outlet cannot be deleted.');
        $outlet->delete();

        return redirect()->route('courier.outlets.index')->with('success', 'Outlet deleted successfully.');
    }

    private function authorizeTenant(Outlet $outlet): void
    {
        abort_if($outlet->tenant_id !== auth()->user()->tenant_id, 404);
    }
}
