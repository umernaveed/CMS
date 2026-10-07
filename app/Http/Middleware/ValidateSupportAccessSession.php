<?php

namespace App\Http\Middleware;

use App\Models\CourierSupportAccessCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ValidateSupportAccessSession
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->session()->has('support_access_code_id')) {
            $code = CourierSupportAccessCode::find($request->session()->get('support_access_code_id'));
            if (! $code || $code->revoked_at || ($code->expires_at && $code->expires_at->isPast())) {
                Auth::logout();
                $request->session()->forget(['support_access_code_id', 'support_access_permissions', 'support_access_tenant_id']);

                return redirect()->route('login')->withErrors(['access_code' => 'Support access was cancelled or expired.']);
            }
        }

        return $next($request);
    }
}
