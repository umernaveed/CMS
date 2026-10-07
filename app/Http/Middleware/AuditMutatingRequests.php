<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditMutatingRequests
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (auth()->check() && $this->shouldAudit($request)) {
            try {
                ActivityLog::create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'user_id' => auth()->id(),
                    'action' => $this->actionFor($request),
                    'module' => $this->moduleFor($request),
                    'description' => $this->descriptionFor($request),
                    'new_data' => [
                        'portal' => $this->portalFor($request),
                        'method' => $request->method(),
                        'route' => optional($request->route())->getName(),
                        'path' => $request->path(),
                        'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                        'input' => $this->safeInput($request),
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    'created_at' => now(),
                ]);
            } catch (Throwable $e) {
                Log::warning('Request audit log write failed', [
                    'route' => optional($request->route())->getName(),
                    'path' => $request->path(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }

    private function shouldAudit(Request $request): bool
    {
        if ($request->routeIs('notifications.dismiss')) {
            return false;
        }

        $routeName = (string) optional($request->route())->getName();

        if ($routeName === '') {
            return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        }

        return str_starts_with($routeName, 'courier.')
            || str_starts_with($routeName, 'superadmin.')
            || str_starts_with($routeName, 'customer.');
    }

    private function actionFor(Request $request): string
    {
        $routeName = (string) optional($request->route())->getName();
        $lastSegment = collect(explode('.', $routeName))->filter()->last();

        if ($lastSegment && ! in_array($lastSegment, ['index', 'show'], true)) {
            return str_replace('-', '_', $lastSegment);
        }

        return match ($request->method()) {
            'GET' => $lastSegment === 'show' ? 'viewed' : 'opened',
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => strtolower($request->method()),
        };
    }

    private function moduleFor(Request $request): string
    {
        $segments = collect(explode('.', (string) optional($request->route())->getName()))->filter()->values();

        if ($segments->count() >= 2) {
            return str_replace('-', '_', (string) $segments->get(1));
        }

        return str_replace('-', '_', $request->segment(2) ?: $request->segment(1) ?: 'system');
    }

    private function portalFor(Request $request): string
    {
        $routeName = (string) optional($request->route())->getName();

        return strtok($routeName, '.') ?: 'web';
    }

    private function descriptionFor(Request $request): string
    {
        $routeName = optional($request->route())->getName();
        $user = auth()->user();
        $name = $user->full_name ?: $user->email;

        return trim($name.' '.$this->actionFor($request).' '.$this->moduleFor($request).' via '.($routeName ?: $request->path()));
    }

    private function safeInput(Request $request): array
    {
        return collect($request->except([
            '_token',
            '_method',
            'password',
            'password_confirmation',
            'current_password',
            'admin_password',
            'secret',
            'api_key',
            'paypal_secret',
            'dime_api_key',
            'amber_pay_api_key',
        ]))
            ->map(function ($value) {
                if ($value instanceof \Illuminate\Http\UploadedFile) {
                    return [
                        'file' => $value->getClientOriginalName(),
                        'mime' => $value->getClientMimeType(),
                        'size' => $value->getSize(),
                    ];
                }

                if (is_array($value)) {
                    return collect($value)->map(fn ($item) => $item instanceof \Illuminate\Http\UploadedFile
                        ? ['file' => $item->getClientOriginalName(), 'mime' => $item->getClientMimeType(), 'size' => $item->getSize()]
                        : $item)->all();
                }

                return is_string($value) ? substr($value, 0, 500) : $value;
            })
            ->all();
    }
}
