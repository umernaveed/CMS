<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $user = $request->user();

        abort_unless($user, 403);

        $permissions = collect($permissions)
            ->flatMap(fn ($permission) => explode(',', (string) $permission))
            ->map(fn ($permission) => trim($permission))
            ->filter()
            ->values()
            ->all();

        abort_unless($user->hasAnyPermission($permissions), 403);

        return $next($request);
    }
}

