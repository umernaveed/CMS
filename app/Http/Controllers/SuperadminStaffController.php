<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class SuperadminStaffController extends Controller
{
    public function employees(Request $request)
    {
        $employees = User::query()
            ->with('roles')
            ->where('user_type', 'super_admin')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(fn ($nested) => $nested->where('full_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate($this->perPage(request(), 15))
            ->withQueryString();
        $roles = $this->superadminRoles()->get();

        return view('superadmin.staff.employees', compact('employees', 'roles'));
    }

    public function storeEmployee(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:8',
            'is_active' => 'nullable|boolean',
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $roleIds = $this->safeSuperadminRoleIds($data['role_ids'] ?? []);
        if (empty($roleIds)) {
            return back()->withErrors(['role_ids' => 'Select at least one System Admin portal role.'])->withInput();
        }

        $employee = User::create([
            'user_type' => 'super_admin',
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);
        $employee->roles()->sync($roleIds);

        return back()->with('success', 'System Admin employee created.');
    }

    public function updateEmployee(Request $request, User $employee)
    {
        abort_unless($employee->user_type === 'super_admin', 404);

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($employee->id)],
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:8',
            'is_active' => 'nullable|boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $roleIds = $this->safeSuperadminRoleIds($data['role_ids'] ?? []);
        if ($employee->id !== auth()->id() && empty($roleIds)) {
            return back()->withErrors(['role_ids' => 'Assign at least one role to restricted System Admin employees.'])->withInput();
        }

        $employee->fill([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $employee->password = Hash::make($data['password']);
        }

        $employee->save();
        if ($employee->id !== auth()->id() || ! empty($roleIds)) {
            $employee->roles()->sync($roleIds);
        }

        return back()->with('success', 'System Admin employee updated.');
    }

    public function destroyEmployee(User $employee)
    {
        abort_unless($employee->user_type === 'super_admin', 404);
        abort_if($employee->id === auth()->id(), 422, 'You cannot delete your own account.');

        $employee->delete();

        return back()->with('success', 'System Admin employee deleted.');
    }

    public function rolesBranches(Request $request)
    {
        $tenants = Tenant::orderBy('company_name')->get();
        $permissions = Permission::where('name', 'like', 'superadmin_%')
            ->orderBy('module')
            ->orderBy('display_name')
            ->get()
            ->groupBy('module');
        $roles = Role::with(['tenant', 'permissions'])
            ->withCount('users')
            ->whereNull('tenant_id')
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->tenant_id))
            ->when($request->filled('role_q'), function ($query) use ($request) {
                $term = '%'.$request->role_q.'%';
                $query->where(fn ($nested) => $nested->where('display_name', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderByRaw('tenant_id is null desc')
            ->orderBy('display_name')
            ->paginate(10, ['*'], 'roles_page')
            ->withQueryString();
        $branches = Outlet::with('tenant')
            ->withCount(['users', 'customers', 'packages'])
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->tenant_id))
            ->when($request->filled('branch_q'), function ($query) use ($request) {
                $term = '%'.$request->branch_q.'%';
                $query->where(fn ($nested) => $nested->where('name', 'like', $term)->orWhere('outlet_code', 'like', $term)->orWhere('city', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(10, ['*'], 'branches_page')
            ->withQueryString();

        return view('superadmin.staff.roles-branches', compact('tenants', 'permissions', 'roles', 'branches'));
    }

    public function storeRole(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => 'nullable|exists:tenants,id',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $tenantId = $data['tenant_id'] ?? null;
        $role = Role::create([
            'tenant_id' => $tenantId,
            'name' => Str::slug($data['display_name'], '_').($tenantId ? '_'.$tenantId : '_system'),
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);
        $role->permissions()->sync($this->safeSuperadminPermissionIds($data['permission_ids'] ?? []));

        return back()->with('success', 'Role created.');
    }

    public function updateRole(Request $request, Role $role)
    {
        abort_unless($role->tenant_id === null, 404);

        $data = $request->validate([
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $role->update([
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
        ]);
        $role->permissions()->sync($this->safeSuperadminPermissionIds($data['permission_ids'] ?? []));

        return back()->with('success', 'Role updated.');
    }

    public function destroyRole(Role $role)
    {
        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'This role is assigned to users and cannot be deleted.',
            ]);
        }

        $role->delete();

        return back()->with('success', 'Role deleted.');
    }

    public function storeBranch(Request $request)
    {
        $data = $this->branchData($request);

        if (! empty($data['is_main'])) {
            Outlet::where('tenant_id', $data['tenant_id'])->update(['is_main' => false]);
        }

        Outlet::create($data + [
            'rate_group_mode' => ($data['is_main'] ?? false) ? 'main' : ($data['rate_group_mode'] ?? 'custom'),
            'is_active' => $request->boolean('is_active', true),
            'is_main' => $request->boolean('is_main'),
        ]);

        return back()->with('success', 'Branch created.');
    }

    public function updateBranch(Request $request, Outlet $branch)
    {
        $data = $this->branchData($request, $branch);

        if (! empty($data['is_main'])) {
            Outlet::where('tenant_id', $data['tenant_id'])->whereKeyNot($branch->id)->update(['is_main' => false]);
        }

        $branch->update($data + [
            'rate_group_mode' => ($data['is_main'] ?? false) ? 'main' : ($data['rate_group_mode'] ?? 'custom'),
            'is_active' => $request->boolean('is_active'),
            'is_main' => $request->boolean('is_main'),
        ]);

        return back()->with('success', 'Branch updated.');
    }

    public function destroyBranch(Outlet $branch)
    {
        abort_if($branch->is_main, 422, 'Main branch cannot be deleted.');

        $branch->delete();

        return back()->with('success', 'Branch deleted.');
    }

    private function branchData(Request $request, ?Outlet $branch = null): array
    {
        return $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'outlet_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('outlets', 'outlet_code')->where('tenant_id', $request->tenant_id)->ignore(optional($branch)->id),
            ],
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'parish' => 'nullable|string|max:100',
            'manager_name' => 'nullable|string|max:255',
            'rate_group_mode' => 'nullable|in:main,custom',
            'is_main' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function superadminRoles()
    {
        return Role::whereNull('tenant_id')
            ->withCount('permissions')
            ->where('name', '<>', 'super_admin')
            ->whereHas('permissions', fn ($permissionQuery) => $permissionQuery->where('name', 'like', 'superadmin_%'))
            ->orderBy('display_name');
    }

    private function safeSuperadminRoleIds(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        return $this->superadminRoles()
            ->whereIn('id', $roleIds)
            ->pluck('id')
            ->all();
    }

    private function safeSuperadminPermissionIds(array $permissionIds): array
    {
        if (empty($permissionIds)) {
            return [];
        }

        return Permission::whereIn('id', $permissionIds)
            ->where('name', 'like', 'superadmin_%')
            ->pluck('id')
            ->all();
    }
}

