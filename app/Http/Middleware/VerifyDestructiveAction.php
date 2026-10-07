<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerifyDestructiveAction
{
    private const PHRASE = 'I understand this action is permanent';

    public function handle(Request $request, Closure $next)
    {
        if (! $this->isDestructive($request)) {
            return $next($request);
        }

        $user = $request->user();
        abort_unless($user, 403);
        abort_if($user->user_type === 'staff', 403, 'Only highest-level admins can delete, void, purge, or clear data.');

        if (! $user->security_code_hash) {
            $code = $this->regenerateSecurityCode($user);

            return back()
                ->withErrors(['security_code' => 'A personal security code was generated for you. Use it once to confirm destructive actions.'])
                ->with('fresh_security_code', $code);
        }

        $confirmationPhrase = Str::of((string) $request->input('confirm_phrase'))
            ->squish()
            ->lower()
            ->toString();

        if ($confirmationPhrase !== Str::lower(self::PHRASE)) {
            throw ValidationException::withMessages([
                'confirm_phrase' => 'Enter the full confirmation phrase shown in the dialog.',
            ]);
        }

        $securityCode = Str::upper(trim((string) $request->input('security_code')));
        if (! Hash::check($securityCode, $user->security_code_hash)) {
            throw ValidationException::withMessages([
                'security_code' => 'Security code is invalid or expired.',
            ]);
        }

        ActivityLog::record('verified_destructive_action', 'security', 'Verified destructive action '.$request->route()?->getName(), [
            'route' => $request->route()?->getName(),
            'path' => $request->path(),
            'reusable_code' => (bool) $user->security_code_reusable,
        ]);

        $response = $next($request);
        if (! $user->security_code_reusable) {
            $nextCode = $this->regenerateSecurityCode($user);
            session()->flash('fresh_security_code', $nextCode);
            session()->flash('success_security_code', 'A new single-use security code was generated for your next destructive action.');
        }

        return $response;
    }

    private function isDestructive(Request $request): bool
    {
        if ($request->isMethod('delete')) {
            return true;
        }

        $routeName = (string) $request->route()?->getName();

        return Str::contains($routeName, ['.void', '.clear', '.mass-delete', '.destroy']);
    }

    private function regenerateSecurityCode($user): string
    {
        $code = Str::upper(Str::random(8));
        $user->forceFill([
            'security_code_hash' => Hash::make($code),
            'security_code_regenerated_at' => now(),
            'security_code_reusable' => false,
        ])->save();

        return $code;
    }
}
