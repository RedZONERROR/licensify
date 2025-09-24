<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip if user is not authenticated
        if (!$user) {
            return $next($request);
        }

        // Skip if user doesn't require 2FA
        if (!$user->requires2FA()) {
            return $next($request);
        }

        // Skip if user doesn't have 2FA enabled
        if (!$user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        // Skip if 2FA is already verified in this session
        if ($this->is2FAVerified($request)) {
            return $next($request);
        }

        // Skip if this is a 2FA related route
        if ($this->is2FARoute($request)) {
            return $next($request);
        }

        // Redirect to 2FA challenge
        return redirect()->route('two-factor.challenge');
    }

    /**
     * Check if 2FA is verified in current session
     */
    protected function is2FAVerified(Request $request): bool
    {
        $verified = session('2fa_verified', false);
        $verifiedAt = session('2fa_verified_at');

        if (!$verified || !$verifiedAt) {
            return false;
        }

        // Check if verification is still valid (30 minutes)
        $expiresAt = $verifiedAt->addMinutes(30);
        
        if (now()->isAfter($expiresAt)) {
            session()->forget(['2fa_verified', '2fa_verified_at']);
            return false;
        }

        return true;
    }

    /**
     * Check if current route is 2FA related
     */
    protected function is2FARoute(Request $request): bool
    {
        $route = $request->route();
        
        if (!$route) {
            return false;
        }

        $routeName = $route->getName();
        
        return in_array($routeName, [
            'two-factor.challenge',
            'two-factor.verify',
            'logout',
        ]);
    }
}