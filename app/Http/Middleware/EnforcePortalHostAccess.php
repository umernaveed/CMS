<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnforcePortalHostAccess
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $isCentralHost = app()->bound('currentHostIsCentral') && app('currentHostIsCentral');
        $isLocalHost = app()->bound('currentHostIsLocal') && app('currentHostIsLocal');

        if (! $tenant && ! $isCentralHost) {
            if ($request->is('customer/login')) {
                return redirect()->to($request->getSchemeAndHttpHost().'/login');
            }

            abort(404);
        }

        if ($tenant && $request->routeIs('superadmin.*') && ! $request->routeIs('superadmin.return')) {
            abort(404);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs('superadmin.return') && $request->session()->has('impersonated_by')) {
            return $next($request);
        }

        if (! $tenant && $isCentralHost && $user->user_type !== 'super_admin' && $request->session()->has('impersonated_by')) {
            $superAdmin = User::where('user_type', 'super_admin')
                ->where('is_active', true)
                ->find($request->session()->get('impersonated_by'));

            if ($superAdmin) {
                Auth::login($superAdmin);
                $request->setUserResolver(fn () => $superAdmin);
                $request->session()->forget([
                    'impersonated_by',
                    'impersonated_by_email',
                    'impersonated_tenant_id',
                    'superadmin_open_portal_full_access',
                ]);
                $user = $superAdmin;
            }
        }

        if ($tenant) {
            if ($user->user_type === 'super_admin' || (int) $user->tenant_id !== (int) $tenant->id) {
                return $this->logoutAndRedirectToLogin(
                    $request,
                    'You were signed in to another workspace. Please sign in to '.$tenant->company_name.' to continue.'
                );
            }
        } elseif (! $isLocalHost && $user->user_type !== 'super_admin') {
            return $this->logoutAndRedirectToLogin(
                $request,
                'Courier accounts must use their assigned courier URL. Please sign in with the correct account.'
            );
        }

        return $next($request);
    }

    private function logoutAndRedirectToLogin(Request $request, string $message)
    {
        Auth::logout();
        $request->session()->forget([
            'impersonated_by',
            'impersonated_by_email',
            'impersonated_tenant_id',
            'superadmin_open_portal_full_access',
        ]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
