<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPlanModule
{
    public function handle(Request $request, Closure $next, ...$modules)
    {
        $user = $request->user();

        abort_unless($user, 403);

        if ($user->user_type === 'super_admin') {
            return $next($request);
        }

        $modules = array_values(array_filter($modules));
        abort_unless(! empty($modules), 403);

        foreach ($modules as $module) {
            if ($user->subscriptionAllowsModule($module)) {
                return $next($request);
            }
        }

        abort(403, 'This module is not included in the courier subscription plan.');
    }
}
