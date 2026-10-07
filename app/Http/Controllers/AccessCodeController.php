<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CourierSupportAccessCode;
use App\Models\MasterTechAccessCode;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Code128Barcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccessCodeController extends Controller
{
    public function show()
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        abort_unless($tenant, 404);

        return view('auth.tech-support', compact('tenant'));
    }

    public function enter(Request $request)
    {
        $data = $request->validate([
            'access_code' => 'required|string|max:100',
        ]);

        $code = strtoupper(trim($data['access_code']));
        $tenantId = app()->bound('currentTenant') ? app('currentTenant')?->id : null;

        abort_unless($tenantId, 404);

        if ($this->loginWithCourierSupportCode($code, (int) $tenantId)) {
            $request->session()->regenerate();

            return redirect()->route('courier.dashboard');
        }

        if ($this->loginWithMasterTechCode($code, (int) $tenantId)) {
            $request->session()->regenerate();

            return redirect()->route('courier.dashboard');
        }

        throw ValidationException::withMessages(['access_code' => 'The access code is invalid, expired, used, or revoked.']);
    }

    public function storeCourierCode(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->user_type, ['tenant_admin'], true), 403);

        $data = $request->validate([
            'label' => 'nullable|string|max:120',
            'expires_in_minutes' => 'required|integer|min:5|max:10080',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $allowedPermissions = Permission::whereIn('name', $data['permissions'])
            ->whereNotIn('name', $this->destructivePermissions())
            ->pluck('name')
            ->values()
            ->all();

        if (empty($allowedPermissions)) {
            throw ValidationException::withMessages(['permissions' => 'Select at least one non-destructive support permission.']);
        }

        $plainCode = 'SUP-'.Str::upper(Str::random(8));
        CourierSupportAccessCode::create([
            'tenant_id' => $user->tenant_id,
            'created_by' => $user->id,
            'code_hash' => Hash::make($plainCode),
            'label' => $data['label'] ?? null,
            'permissions' => $allowedPermissions,
            'expires_at' => now()->addMinutes((int) $data['expires_in_minutes']),
        ]);

        ActivityLog::record('created', 'support_access', 'Generated courier support access code', [
            'permissions' => $allowedPermissions,
            'expires_in_minutes' => (int) $data['expires_in_minutes'],
        ]);

        return back()->with('support_access_code', $plainCode)->with('success', 'One-time support code generated.');
    }

    public function revokeCourierCode(CourierSupportAccessCode $code)
    {
        abort_if((int) $code->tenant_id !== (int) auth()->user()->tenant_id, 404);

        $code->update([
            'revoked_at' => now(),
            'revoked_by' => auth()->id(),
        ]);

        ActivityLog::record('revoked', 'support_access', 'Revoked courier support access code');

        return back()->with('success', 'Support code revoked. Any active support session is no longer authorized.');
    }

    public function regeneratePersonalSecurityCode(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->user_type, ['tenant_admin', 'super_admin'], true), 403);

        $plainCode = Str::upper(Str::random(8));
        $user->forceFill([
            'security_code_hash' => Hash::make($plainCode),
            'security_code_regenerated_at' => now(),
            'security_code_reusable' => false,
        ])->save();

        ActivityLog::record('regenerated', 'security', 'Regenerated personal destructive-action code');

        return back()
            ->with('fresh_security_code', $plainCode)
            ->with('success_security_code', 'Your previous destructive-action code has been replaced.');
    }

    public function setPersonalSecurityCode(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->user_type, ['tenant_admin', 'super_admin'], true), 403);

        $data = $request->validate([
            'security_code' => ['required', 'string', 'min:6', 'max:64', 'regex:/^[A-Za-z0-9-]+$/', 'confirmed'],
        ], [
            'security_code.regex' => 'The destructive code may contain only letters, numbers, and hyphens.',
        ]);

        $plainCode = Str::upper(trim($data['security_code']));
        $user->forceFill([
            'security_code_hash' => Hash::make($plainCode),
            'security_code_regenerated_at' => now(),
            'security_code_reusable' => true,
        ])->save();

        ActivityLog::record('updated', 'security', 'Set reusable personal destructive-action code');

        return back()->with('success', 'Your reusable destructive-action code has been set.');
    }

    public function storeMasterCode(Request $request)
    {
        abort_unless($request->user()->user_type === 'super_admin', 403);

        $data = $request->validate([
            'label' => 'nullable|string|max:120',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $plainCode = 'MTC-'.Str::upper(Str::random(12));
        MasterTechAccessCode::create($data + [
            'created_by' => auth()->id(),
            'code_hash' => Hash::make($plainCode),
        ]);

        return back()
            ->with('master_tech_code', $plainCode)
            ->with('master_tech_barcode', Code128Barcode::svg($plainCode, 84))
            ->with('success', 'Master Tech access code generated.');
    }

    public function revokeMasterCode(MasterTechAccessCode $code)
    {
        abort_unless(auth()->user()->user_type === 'super_admin', 403);

        $code->update([
            'revoked_at' => now(),
            'revoked_by' => auth()->id(),
        ]);

        return back()->with('success', 'Master Tech code revoked.');
    }

    private function loginWithCourierSupportCode(string $plainCode, int $tenantId): bool
    {
        $accessCode = CourierSupportAccessCode::where('tenant_id', $tenantId)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()
            ->first(fn ($candidate) => Hash::check($plainCode, $candidate->code_hash));

        if (! $accessCode || ! $accessCode->isUsable()) {
            return false;
        }

        $user = User::where('tenant_id', $tenantId)
            ->where('user_type', 'tenant_admin')
            ->where('is_active', true)
            ->oldest()
            ->first();

        if (! $user) {
            return false;
        }

        Auth::login($user);
        session([
            'support_access_code_id' => $accessCode->id,
            'support_access_permissions' => $accessCode->permissions ?: [],
            'support_access_tenant_id' => $tenantId,
        ]);
        $accessCode->update(['used_at' => now(), 'active_user_id' => $user->id]);

        return true;
    }

    private function loginWithMasterTechCode(string $plainCode, int $tenantId): bool
    {
        $accessCode = MasterTechAccessCode::whereNull('revoked_at')
            ->get()
            ->first(fn ($candidate) => Hash::check($plainCode, $candidate->code_hash) && $candidate->isUsable());

        if (! $accessCode) {
            return false;
        }

        $user = User::where('tenant_id', $tenantId)
            ->where('user_type', 'tenant_admin')
            ->where('is_active', true)
            ->oldest()
            ->first();

        if (! $user) {
            return false;
        }

        Auth::login($user);
        session([
            'master_tech_access' => true,
            'master_tech_code_id' => $accessCode->id,
            'support_access_tenant_id' => $tenantId,
        ]);
        $accessCode->update(['last_used_at' => now()]);

        return true;
    }

    private function destructivePermissions(): array
    {
        return [
            'manage_system',
            'manage_roles',
            'manage_staff',
            'superadmin_maintenance',
            'superadmin_staff_roles',
        ];
    }
}
