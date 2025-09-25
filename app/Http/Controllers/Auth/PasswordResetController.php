<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    protected PasswordResetService $passwordResetService;

    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * Display the password reset request form.
     */
    public function create()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle a password reset request.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Rate limiting key
        $key = 'password-reset:' . $request->ip();
        
        // Check rate limiting (5 attempts per hour)
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => ['Too many password reset attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.'],
            ]);
        }

        // Increment rate limiter
        RateLimiter::hit($key, 3600); // 1 hour

        try {
            $result = $this->passwordResetService->sendResetLink($request->email, $request->ip());
            
            if ($result['success']) {
                return back()->with('status', $result['message']);
            } else {
                throw ValidationException::withMessages([
                    'email' => [$result['message']],
                ]);
            }
        } catch (\Exception $e) {
            // Log the error but don't reveal it to the user
            logger()->error('Password reset error: ' . $e->getMessage(), [
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);
            
            throw ValidationException::withMessages([
                'email' => ['An error occurred while processing your request. Please try again later.'],
            ]);
        }
    }

    /**
     * Display the password reset form.
     */
    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Handle the password reset.
     */
    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'totp_code' => ['nullable', 'string', 'size:6'],
        ]);

        // Rate limiting for reset attempts
        $key = 'password-reset-attempt:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => ['Too many reset attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.'],
            ]);
        }

        RateLimiter::hit($key, 3600);

        try {
            $result = $this->passwordResetService->resetPassword(
                $request->email,
                $request->token,
                $request->password,
                $request->totp_code,
                $request->ip()
            );

            if ($result['success']) {
                // Clear rate limiter on success
                RateLimiter::clear($key);
                
                return redirect()->route('login')->with('status', $result['message']);
            } else {
                throw ValidationException::withMessages([
                    'email' => [$result['message']],
                ]);
            }
        } catch (ValidationException $e) {
            // Re-throw validation exceptions as-is
            throw $e;
        } catch (\Exception $e) {
            logger()->error('Password reset update error: ' . $e->getMessage(), [
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);
            
            throw ValidationException::withMessages([
                'email' => ['An error occurred while resetting your password. Please try again.'],
            ]);
        }
    }
}
