<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SessionSecurityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $this->handleSessionSecurity($request);
        }

        return $next($request);
    }

    /**
     * Handle session security measures
     */
    private function handleSessionSecurity(Request $request): void
    {
        $user = Auth::user();
        $sessionKey = 'user_session_' . $user->id;
        
        // Check for session hijacking
        $this->detectSessionHijacking($request, $user);
        
        // Handle concurrent session detection
        $this->handleConcurrentSessions($request, $user, $sessionKey);
        
        // Update last activity
        Session::put('last_activity', now());
        Session::put('user_ip', $request->ip());
        Session::put('user_agent', $request->userAgent());
    }

    /**
     * Detect potential session hijacking
     */
    private function detectSessionHijacking(Request $request, $user): void
    {
        $storedIp = Session::get('user_ip');
        $storedUserAgent = Session::get('user_agent');
        
        if ($storedIp && $storedIp !== $request->ip()) {
            // Log suspicious activity
            activity()
                ->causedBy($user)
                ->withProperties([
                    'stored_ip' => $storedIp,
                    'current_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('Potential session hijacking detected - IP change');
        }
        
        if ($storedUserAgent && $storedUserAgent !== $request->userAgent()) {
            // Log suspicious activity
            activity()
                ->causedBy($user)
                ->withProperties([
                    'stored_user_agent' => $storedUserAgent,
                    'current_user_agent' => $request->userAgent(),
                ])
                ->log('Potential session hijacking detected - User agent change');
        }
    }

    /**
     * Handle concurrent session detection
     */
    private function handleConcurrentSessions(Request $request, $user, string $sessionKey): void
    {
        $currentSessionId = Session::getId();
        $storedSessionId = cache()->get($sessionKey);
        
        if ($storedSessionId && $storedSessionId !== $currentSessionId) {
            // Log concurrent session
            activity()
                ->causedBy($user)
                ->withProperties([
                    'stored_session' => $storedSessionId,
                    'current_session' => $currentSessionId,
                ])
                ->log('Concurrent session detected');
        }
        
        // Store current session ID
        cache()->put($sessionKey, $currentSessionId, now()->addHours(24));
    }
}