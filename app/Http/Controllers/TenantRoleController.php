<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantRoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->where(function ($query) {
                $query->whereNull('tenant_id')->orWhere('tenant_id', auth()->user()->tenant_id);
            })
            ->where(function ($query) {
                $query->whereIn('name', ['tenant_admin', 'staff'])->orWhere('tenant_id', auth()->user()->tenant_id);
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('display_name', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderByDesc('is_system')
            ->orderBy('display_name')
            ->paginate($this->perPage(request(), 10))
            ->withQueryString();

        return view('courier.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = $this->courierPermissions()->get()->groupBy('module');

        return view('courier.roles.create', ['role' => null, 'permissions' => $permissions]);
    }

    public function show(Role $role)
    {
        $this->authorizeRole($role);
        $role->load(['permissions' => fn ($query) => $this->applyCourierPermissionScope($query)->orderBy('module')->orderBy('display_name')])->loadCount('users');
        $permissions = $role->permissions->groupBy('module');

        return view('courier.roles.show', compact('role', 'permissions'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $role = Role::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => Str::slug($data['display_name'], '_') . '_' . auth()->user()->tenant_id,
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $role->permissions()->sync($this->safeCourierPermissionIds($data['permission_ids'] ?? []));
        ActivityLog::record('created', 'roles', 'Created role '.$role->display_name, ['role_id' => $role->id]);

        return redirect()->route('courier.roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        $this->authorizeRole($role);
        abort_if($role->is_system, 422, 'System roles cannot be edited. Create a custom tenant role instead.');
        $permissions = $this->courierPermissions()->get()->groupBy('module');
        $role->load(['permissions' => fn ($query) => $this->applyCourierPermissionScope($query)]);

        return view('courier.roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizeRole($role);
        abort_if($role->is_system, 422, 'System roles cannot be edited. Create a custom tenant role instead.');
        $data = $this->validatedData($request);

        if (! $role->is_system) {
            $role->update([
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        }

        $role->permissions()->sync($this->safeCourierPermissionIds($data['permission_ids'] ?? []));
        ActivityLog::record('updated', 'roles', 'Updated role '.$role->display_name, ['role_id' => $role->id]);

        return redirect()->route('courier.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        $this->authorizeRole($role);
        abort_if($role->is_system, 422, 'System roles cannot be deleted.');
        $name = $role->display_name;
        $role->delete();
        ActivityLog::record('deleted', 'roles', 'Deleted role '.$name);

        return redirect()->route('courier.roles.index')->with('success', 'Role deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);
    }

    private function courierPermissions()
    {
        return $this->applyCourierPermissionScope(Permission::query())
            ->orderBy('module')
            ->orderBy('display_name');
    }

    private function safeCourierPermissionIds(array $permissionIds): array
    {
        if (empty($permissionIds)) {
            return [];
        }

        return $this->applyCourierPermissionScope(Permission::whereIn('id', $permissionIds))
            ->pluck('id')
            ->all();
    }

    private function applyCourierPermissionScope($query)
    {
        return $query
            ->where('name', 'not like', 'superadmin_%')
            ->where('name', 'not like', 'manage_carriers%')
            ->where('name', 'not like', 'manage_couriers%')
            ->whereNotIn('name', [
                'manage_tenants',
                'create_tenants',
                'edit_tenants',
                'delete_tenants',
                'open_tenant_portal',
                'manage_subscription_plans',
            ])
            ->where(function ($query) {
                $query->whereNull('module')
                    ->orWhere(function ($query) {
                        $query->where('module', 'not like', 'System Admin%')
                            ->where('module', 'not like', 'Superadmin%')
                            ->whereNotIn('module', ['tenants', 'Tenants']);
                    });
            });
    }

    private function authorizeRole(Role $role): void
    {
        $allowedSystem = $role->tenant_id === null && in_array($role->name, ['tenant_admin', 'staff']);
        $owned = $role->tenant_id === auth()->user()->tenant_id;

        abort_unless($allowedSystem || $owned, 404);
    }
}



