<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $this->checkSessionSecurity($request);
        }

        return $next($request);
    }

    /**
     * Check session security measures.
     */
    protected function checkSessionSecurity(Request $request): void
    {
        $user = Auth::user();
        
        // Check for session hijacking by comparing IP addresses
        $currentIp = $request->ip();
        $sessionIp = Session::get('user_ip');
        
        if ($sessionIp && $sessionIp !== $currentIp) {
            // Log suspicious activity (activity logging will be implemented later)
            
            // Force logout for security
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();
            
            abort(403, 'Session security violation detected.');
        }
        
        // Store current IP if not set
        if (!$sessionIp) {
            Session::put('user_ip', $currentIp);
        }
        
        // Check session timeout (idle timeout)
        $lastActivity = Session::get('last_activity', time());
        $idleTimeout = config('session.idle_timeout', 3600); // 1 hour default
        
        if (time() - $lastActivity > $idleTimeout) {
            // Log session expiry (activity logging will be implemented later)
            
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();
            
            return redirect()->route('login')->with('message', 'Your session has expired due to inactivity.');
        }
        
        // Update last activity
        Session::put('last_activity', time());
        
        // Check absolute session timeout
        $sessionStart = Session::get('session_start', time());
        $absoluteTimeout = config('session.absolute_timeout', 28800); // 8 hours default
        
        if (time() - $sessionStart > $absoluteTimeout) {
            // Log session expiry (activity logging will be implemented later)
            
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();
            
            return redirect()->route('login')->with('message', 'Your session has expired. Please log in again.');
        }
    }
}