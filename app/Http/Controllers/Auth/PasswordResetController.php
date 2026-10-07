<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CustomerNotificationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request, CustomerNotificationService $notifications)
    {
        $request->validate(['email' => 'required|email']);

        Log::info('Password reset requested', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $user = $this->userForCurrentPortal($request->email);
        
        if (! $user) {
            Log::warning('Password reset attempted for non-existent or inactive user', [
                'email' => $request->email,
                'ip' => $request->ip()
            ]);
            
            return back()->with('status', __($this->sentStatus()));
        }

        Log::info('User found for password reset', [
            'user_id' => $user->id,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'tenant_id' => $user->tenant_id ?? 'N/A',
            'is_active' => $user->is_active
        ]);

        try {
            $token = Password::broker()->createToken($user);
            
            Log::debug('Password reset token created', [
                'user_id' => $user->id,
                'token' => substr($token, 0, 10) . '...', // Log only partial token for security
                'token_length' => strlen($token)
            ]);

            $sent = $notifications->passwordReset($user, $token);
            if (! $sent) {
                Log::error('Password reset email was not sent; notification service returned false', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                ]);

                return back()->withErrors([
                    'email' => 'Password reset email could not be sent. Please check mail logs.',
                ]);
            }
            
            Log::info('Password reset email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant' => app()->bound('currentTenant') ? app('currentTenant')->id ?? 'null' : 'no_tenant'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'user_id' => $user->id ?? null,
                'email' => $request->email,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e; // Re-throw if you want the application to handle it
        }

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }

    public function reset(string $token, Request $request)
    {
        $email = (string) $request->query('email');
        $tenantId = $request->integer('tenant_id') ?: null;
        Log::info('Password reset page viewed', [
            'email' => $email,
            'tenant_id' => $tenantId,
            'token_preview' => substr($token, 0, 10) . '...',
            'ip' => $request->ip()
        ]);

        $user = $email !== '' ? $this->userForCurrentPortal($email, $tenantId) : null;
        $invalidToken = ! $user || ! Password::broker()->tokenExists($user, $token);

        if ($invalidToken) {
            Log::warning('Password reset page blocked - invalid or expired token', [
                'email' => $email,
                'user_id' => $user?->id,
                'token_preview' => substr($token, 0, 10) . '...',
                'ip' => $request->ip(),
            ]);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'tenantId' => $user?->tenant_id ?: $tenantId,
            'invalidToken' => $invalidToken,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'tenant_id' => 'nullable|integer',
            'password' => 'required|string|min:8|confirmed',
        ]);

        Log::info('Password reset update attempted', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $user = $this->userForCurrentPortal($request->email, $request->integer('tenant_id') ?: null);
        
        if (! $user) {
            Log::warning('Password reset update failed - user not found', [
                'email' => $request->email,
                'ip' => $request->ip()
            ]);
            
            return back()->withErrors(['email' => __(Password::INVALID_TOKEN)])->withInput($request->only('email'));
        }
        
        if (! Password::broker()->tokenExists($user, $request->token)) {
            Log::warning('Password reset update failed - invalid token', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token_preview' => substr($request->token, 0, 10) . '...',
                'ip' => $request->ip()
            ]);
            
            return back()->withErrors(['email' => __(Password::INVALID_TOKEN)])->withInput($request->only('email'));
        }

        Log::info('Password reset token validated successfully', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        try {
            $oldPasswordHash = $user->password;
            
            $user->forceFill([
                'password' => Hash::make($request->password),
                'remember_token' => Str::random(60),
            ])->save();

            Log::info('Password reset successful - password updated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'user_type' => $user->user_type,
                'password_changed_at' => now()->toDateTimeString(),
                'ip' => $request->ip()
            ]);

            Password::broker()->deleteToken($user);
            
            Log::debug('Password reset token deleted after successful reset', [
                'user_id' => $user->id
            ]);

            event(new PasswordReset($user));
            
            Log::info('PasswordReset event dispatched', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Password reset update failed with exception', [
                'user_id' => $user->id ?? null,
                'email' => $request->email,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->withErrors(['email' => 'An error occurred while resetting your password. Please try again.']);
        }

        return redirect()->route('login')->with('status', __(Password::PASSWORD_RESET));
    }

    private function userForCurrentPortal(string $email, ?int $tenantId = null): ?User
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $effectiveTenantId = $tenant?->id ?: $tenantId;
        
        Log::debug('Looking for user in current portal', [
            'email' => $email,
            'has_tenant' => !is_null($tenant),
            'tenant_id' => $effectiveTenantId ?? 'null'
        ]);

        $user = User::where('email', $email)
            ->where('is_active', true)
            ->when($effectiveTenantId, fn ($query) => $query->where('tenant_id', $effectiveTenantId))
            ->when(! $effectiveTenantId, fn ($query) => $query->whereIn('user_type', ['super_admin', 'tenant_admin', 'staff']))
            ->first();

        if ($user) {
            Log::debug('User found for portal', [
                'user_id' => $user->id,
                'email' => $user->email,
                'user_type' => $user->user_type,
                'tenant_id' => $user->tenant_id
            ]);
        } else {
            Log::debug('No user found for portal', [
                'email' => $email,
                'tenant_context' => $tenant->id ?? 'no_tenant'
            ]);
        }

        return $user;
    }

    private function sentStatus(): string
    {
        return Password::RESET_LINK_SENT;
    }
}
