<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_USER, // Default role
            'privacy_policy_accepted_at' => now(), // Track privacy policy acceptance
        ]);

        event(new Registered($user));

        Auth::login($user);

        // Regenerate session ID for security
        $request->session()->regenerate();

        // Log successful registration
        activity()
            ->causedBy($user)
            ->log('User registered');

        return redirect()->route('dashboard');
    }
}