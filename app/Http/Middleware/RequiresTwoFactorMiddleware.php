<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequiresTwoFactorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Check if user requires 2FA for their role
        if ($user->requires2FA()) {
            // Check if 2FA is enabled and confirmed
            if (!$user->hasTwoFactorEnabled()) {
                return redirect()->route('two-factor.setup')
                    ->with('error', 'Two-factor authentication is required for your role.');
            }

            // Check if 2FA has been verified in this session for sensitive operations
            if (!session('2fa_verified_at') || 
                now()->diffInMinutes(session('2fa_verified_at')) > 30) {
                return redirect()->route('two-factor.verify')
                    ->with('error', 'Please verify your two-factor authentication to continue.');
            }
        }

        return $next($request);
    }
}