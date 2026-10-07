<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\CustomerNotificationService;
use App\Services\SubscriptionLimitService;

class StaffUserController extends Controller
{
    public function index(Request $request)
    {
        $users = $this->tenantStaff()
            ->with(['outlet', 'roles'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(fn ($nested) => $nested->where('full_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('outlet_id'), fn ($query) => $query->where('outlet_id', $request->integer('outlet_id')))
            ->when($request->filled('user_type'), fn ($query) => $query->where('user_type', $request->user_type))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('role_id'), fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereKey($request->integer('role_id'))))
            ->latest()
            ->paginate($this->perPage(request(), 15))
            ->withQueryString();
        $outlets = $this->tenantOutlets()->orderBy('name')->get();
        $roles = $this->availableRoles()->get();

        return view('courier.staff.index', compact('users', 'outlets', 'roles'));
    }

    public function create()
    {
        $outlets = $this->tenantOutlets()->orderBy('name')->get();
        $roles = $this->availableRoles()->get();

        return view('courier.staff.create', compact('outlets', 'roles'));
    }

    public function show(User $staff)
    {
        $this->authorizeStaff($staff);
        $staff->load(['outlet', 'roles.permissions']);

        return view('courier.staff.show', compact('staff'));
    }

    public function store(Request $request, SubscriptionLimitService $limits, CustomerNotificationService $notifications)
    {
        $tenantId = auth()->user()->tenant_id;
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'user_type' => 'required|in:tenant_admin,staff',
            'outlet_id' => 'nullable|exists:outlets,id',
            'password' => 'required|string|min:8',
            'is_active' => 'nullable|boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $data['outlet_id'] = auth()->user()->outlet_id;
        }
        $this->ensureStaffCanAssignUserType($data['user_type']);
        $this->ensureTenantOutlet($data['outlet_id'] ?? null);

        $limits->ensureCanCreate($tenantId, 'users');

        $user = User::create([
            'tenant_id' => $tenantId,
            'outlet_id' => $data['outlet_id'] ?? null,
            'user_type' => $data['user_type'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->roles()->sync($this->safeRoleIds($data['role_ids'] ?? [], $data['user_type']));
        $notifications->staffAccountCreated($user->fresh(['tenant', 'outlet']));
        ActivityLog::record('created', 'users', 'Created staff user '.$user->email, ['user_id' => $user->id]);

        return redirect()->route('courier.staff.index')->with('success', 'Staff user created successfully.');
    }

    public function edit(User $staff)
    {
        $this->authorizeStaff($staff);
        $outlets = $this->tenantOutlets()->orderBy('name')->get();
        $roles = $this->availableRoles()->get();
        $staff->load('roles');

        return view('courier.staff.edit', compact('staff', 'outlets', 'roles'));
    }

    public function update(Request $request, User $staff)
    {
        $this->authorizeStaff($staff);
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $staff->id,
            'phone' => 'nullable|string|max:50',
            'user_type' => 'required|in:tenant_admin,staff',
            'outlet_id' => 'nullable|exists:outlets,id',
            'password' => 'nullable|string|min:8',
            'is_active' => 'nullable|boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $data['outlet_id'] = auth()->user()->outlet_id;
        }
        $this->ensureStaffCanAssignUserType($data['user_type']);
        $this->ensureTenantOutlet($data['outlet_id'] ?? null);

        $updateData = [
            'outlet_id' => $data['outlet_id'] ?? null,
            'user_type' => $data['user_type'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? false,
        ];

        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $staff->update($updateData);

        $staff->roles()->sync($this->safeRoleIds($data['role_ids'] ?? [], $data['user_type']));
        ActivityLog::record('updated', 'users', 'Updated staff user '.$staff->email, ['user_id' => $staff->id]);

        return redirect()->route('courier.staff.index')->with('success', 'Staff user updated successfully.');
    }

    public function destroy(User $staff)
    {
        $this->authorizeStaff($staff);
        abort_if($staff->id === auth()->id(), 422, 'You cannot delete yourself.');
        $email = $staff->email;
        $staff->delete();
        ActivityLog::record('deleted', 'users', 'Deleted staff user '.$email);

        return redirect()->route('courier.staff.index')->with('success', 'Staff user deleted successfully.');
    }

    public function deactivate(User $staff)
    {
        $this->authorizeStaff($staff);
        abort_if($staff->id === auth()->id(), 422, 'You cannot deactivate yourself.');

        $staff->update(['is_active' => false]);
        ActivityLog::record('deactivated', 'users', 'Deactivated staff user '.$staff->email, ['user_id' => $staff->id]);

        return back()->with('success', 'Employee deactivated successfully.');
    }

    private function tenantStaff()
    {
        $query = User::where('tenant_id', auth()->user()->tenant_id)->whereIn('user_type', ['tenant_admin', 'staff']);

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $query->where('outlet_id', auth()->user()->outlet_id);
        }

        return $query;
    }

    private function tenantOutlets()
    {
        $query = Outlet::where('tenant_id', auth()->user()->tenant_id);

        if (auth()->user()->user_type === 'staff' && auth()->user()->outlet_id) {
            $query->where('id', auth()->user()->outlet_id);
        }

        return $query;
    }

    private function availableRoles()
    {
        return Role::where(function ($query) {
            $query->where(function ($subQuery) {
                $subQuery->whereNull('tenant_id')->where('name', 'tenant_admin');
            })->orWhere('tenant_id', auth()->user()->tenant_id);
        })->orderBy('display_name');
    }

    private function safeRoleIds(array $roleIds, string $userType): array
    {
        return Role::whereIn('id', $roleIds)
            ->where(function ($query) {
                $query->whereNull('tenant_id')->orWhere('tenant_id', auth()->user()->tenant_id);
            })
            ->where(function ($query) use ($userType) {
                $query->whereNotNull('tenant_id');

                if ($userType === 'tenant_admin') {
                    $query->orWhere(function ($systemQuery) {
                        $systemQuery->whereNull('tenant_id')->where('name', 'tenant_admin');
                    });
                }
            })
            ->pluck('id')
            ->all();
    }

    private function authorizeStaff(User $staff): void
    {
        abort_if($staff->tenant_id !== auth()->user()->tenant_id, 404);
        abort_if(! in_array($staff->user_type, ['tenant_admin', 'staff']), 404);
        abort_if(auth()->user()->user_type === 'staff' && auth()->user()->outlet_id && $staff->outlet_id !== auth()->user()->outlet_id, 404);
    }

    private function ensureTenantOutlet(?int $outletId): void
    {
        if (! $outletId) {
            return;
        }

        abort_unless($this->tenantOutlets()->whereKey($outletId)->exists(), 422, 'Selected outlet is not available.');
    }

    private function ensureStaffCanAssignUserType(string $userType): void
    {
        abort_if(auth()->user()->user_type === 'staff' && $userType !== 'staff', 403, 'Staff users cannot create or promote tenant administrators.');
    }
}
