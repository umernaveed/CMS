<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  ...$types
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$types)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $allowedTypes = collect($types)
            ->flatMap(fn ($type) => explode(',', $type))
            ->map(fn ($type) => trim($type))
            ->filter()
            ->all();

        if (!in_array($user->user_type, $allowedTypes)) {
            if ($request->expectsJson() || ! $request->isMethod('GET')) {
                abort(403, 'Unauthorized');
            }

            return redirect('/')->with('warning', $this->messageFor($user->user_type, $allowedTypes));
        }

        return $next($request);
    }

    private function messageFor(string $userType, array $allowedTypes): string
    {
        if (in_array('customer', $allowedTypes, true) && in_array($userType, ['tenant_admin', 'staff'], true)) {
            return 'Customer pages require a customer login. You are still signed in to the courier admin portal.';
        }

        if ((in_array('tenant_admin', $allowedTypes, true) || in_array('staff', $allowedTypes, true)) && $userType === 'customer') {
            return 'Courier admin pages require a courier staff login. You are still signed in as a customer.';
        }

        if ($userType === 'super_admin' && (in_array('tenant_admin', $allowedTypes, true) || in_array('staff', $allowedTypes, true))) {
            return 'Courier workspace pages require a courier admin or staff login.';
        }

        return 'Your current account does not have access to that area.';
    }
}