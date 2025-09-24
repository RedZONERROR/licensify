<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Illuminate\Validation\ValidationException;

class OAuthController extends Controller
{
    /**
     * Redirect to Gmail OAuth provider
     */
    public function redirectToProvider(string $provider)
    {
        if ($provider !== 'google') {
            abort(404, 'Provider not supported');
        }

        try {
            return Socialite::driver($provider)
                ->scopes(['email', 'profile'])
                ->redirect();
        } catch (\Exception $e) {
            Log::error('OAuth redirect error', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('login')
                ->withErrors(['oauth' => 'Unable to connect to Gmail. Please try again.']);
        }
    }

    /**
     * Handle OAuth callback from provider
     */
    public function handleProviderCallback(string $provider, Request $request)
    {
        if ($provider !== 'google') {
            abort(404, 'Provider not supported');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException $e) {
            Log::warning('OAuth invalid state', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return redirect()->route('login')
                ->withErrors(['oauth' => 'Authentication session expired. Please try again.']);
        } catch (\Exception $e) {
            Log::error('OAuth callback error', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return redirect()->route('login')
                ->withErrors(['oauth' => 'Authentication failed. Please try again.']);
        }

        // Check if user exists with this email
        $existingUser = User::where('email', $socialUser->getEmail())->first();

        if ($existingUser) {
            return $this->handleExistingUser($existingUser, $socialUser, $provider);
        }

        return $this->handleNewUser($socialUser, $provider);
    }

    /**
     * Handle existing user OAuth login
     */
    protected function handleExistingUser(User $user, $socialUser, string $provider)
    {
        // Update OAuth provider info
        $oauthProviders = $user->oauth_providers ?? [];
        $oauthProviders[$provider] = [
            'id' => $socialUser->getId(),
            'email' => $socialUser->getEmail(),
            'name' => $socialUser->getName(),
            'avatar' => $socialUser->getAvatar(),
            'linked_at' => now()->toISOString()
        ];

        $user->update([
            'oauth_providers' => $oauthProviders,
            'avatar' => $user->avatar ?? $socialUser->getAvatar(),
        ]);

        // Log the user in
        Auth::login($user, true);

        Log::info('OAuth login successful', [
            'user_id' => $user->id,
            'provider' => $provider,
            'ip' => request()->ip()
        ]);

        // Check if user needs to set up additional password
        if (!$user->password) {
            session()->flash('oauth_setup_password', true);
            return redirect()->route('profile.edit')
                ->with('success', 'Welcome back! You can optionally set up a password for additional security.');
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Successfully logged in with Gmail!');
    }

    /**
     * Handle new user OAuth registration
     */
    protected function handleNewUser($socialUser, string $provider)
    {
        try {
            $user = User::create([
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'email_verified_at' => now(), // Gmail emails are pre-verified
                'avatar' => $socialUser->getAvatar(),
                'role' => User::ROLE_USER,
                'oauth_providers' => [
                    $provider => [
                        'id' => $socialUser->getId(),
                        'email' => $socialUser->getEmail(),
                        'name' => $socialUser->getName(),
                        'avatar' => $socialUser->getAvatar(),
                        'linked_at' => now()->toISOString()
                    ]
                ]
            ]);

            Auth::login($user, true);

            Log::info('OAuth registration successful', [
                'user_id' => $user->id,
                'provider' => $provider,
                'ip' => request()->ip()
            ]);

            // Redirect to privacy policy acceptance and optional password setup
            session()->flash('oauth_new_user', true);
            return redirect()->route('profile.edit')
                ->with('success', 'Welcome! Please review your profile and optionally set up a password.');

        } catch (\Exception $e) {
            Log::error('OAuth user creation failed', [
                'provider' => $provider,
                'email' => $socialUser->getEmail(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('login')
                ->withErrors(['oauth' => 'Account creation failed. Please try again or contact support.']);
        }
    }

    /**
     * Link OAuth provider to existing authenticated user
     */
    public function linkProvider(string $provider, Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if ($provider !== 'google') {
            abort(404, 'Provider not supported');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            Log::error('OAuth linking error', [
                'user_id' => Auth::id(),
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('profile.edit')
                ->withErrors(['oauth' => 'Failed to link Gmail account. Please try again.']);
        }

        $user = Auth::user();

        // Check if this Gmail account is already linked to another user
        $existingUser = User::where('email', '!=', $user->email)
            ->whereJsonContains('oauth_providers->' . $provider . '->email', $socialUser->getEmail())
            ->first();

        if ($existingUser) {
            return redirect()->route('profile.edit')
                ->withErrors(['oauth' => 'This Gmail account is already linked to another user.']);
        }

        // Link the provider
        $oauthProviders = $user->oauth_providers ?? [];
        $oauthProviders[$provider] = [
            'id' => $socialUser->getId(),
            'email' => $socialUser->getEmail(),
            'name' => $socialUser->getName(),
            'avatar' => $socialUser->getAvatar(),
            'linked_at' => now()->toISOString()
        ];

        $user->update([
            'oauth_providers' => $oauthProviders,
            'avatar' => $user->avatar ?? $socialUser->getAvatar(),
        ]);

        Log::info('OAuth provider linked', [
            'user_id' => $user->id,
            'provider' => $provider,
            'linked_email' => $socialUser->getEmail()
        ]);

        return redirect()->route('profile.edit')
            ->with('success', 'Gmail account successfully linked to your profile!');
    }

    /**
     * Unlink OAuth provider from user account
     */
    public function unlinkProvider(string $provider, Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if ($provider !== 'google') {
            abort(404, 'Provider not supported');
        }

        $user = Auth::user();
        $oauthProviders = $user->oauth_providers ?? [];

        if (!isset($oauthProviders[$provider])) {
            return redirect()->route('profile.edit')
                ->withErrors(['oauth' => 'Gmail account is not linked to your profile.']);
        }

        // Ensure user has a password if unlinking their only OAuth provider
        if (!$user->password && count($oauthProviders) === 1) {
            return redirect()->route('profile.edit')
                ->withErrors(['oauth' => 'You must set a password before unlinking your Gmail account.']);
        }

        unset($oauthProviders[$provider]);

        $user->update([
            'oauth_providers' => empty($oauthProviders) ? null : $oauthProviders
        ]);

        Log::info('OAuth provider unlinked', [
            'user_id' => $user->id,
            'provider' => $provider
        ]);

        return redirect()->route('profile.edit')
            ->with('success', 'Gmail account successfully unlinked from your profile.');
    }
}