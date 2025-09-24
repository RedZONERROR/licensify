<?php

namespace Tests\Feature;

use App\Http\Middleware\TwoFactorMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected Google2FA $google2fa;
    protected TwoFactorMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->google2fa = new Google2FA();
        $this->middleware = new TwoFactorMiddleware();
    }

    public function test_middleware_allows_unauthenticated_users()
    {
        $request = Request::create('/test');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_middleware_allows_users_who_dont_require_2fa()
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        
        $request = Request::create('/test');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_middleware_allows_users_without_2fa_enabled()
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        
        $request = Request::create('/test');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_middleware_allows_users_with_verified_2fa()
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);

        // Simulate verified 2FA session
        session(['2fa_verified' => true, '2fa_verified_at' => now()]);

        $request = Request::create('/test');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_middleware_redirects_users_with_unverified_2fa()
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);

        $request = Request::create('/test');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('two-factor/challenge', $response->headers->get('Location'));
    }

    public function test_middleware_allows_2fa_related_routes()
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);

        // Test 2FA challenge route
        $request = Request::create('/two-factor/challenge');
        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['GET'], '/two-factor/challenge', []);
            $route->name('two-factor.challenge');
            return $route;
        });
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_2fa_verification_expires_after_30_minutes()
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);

        // Simulate expired 2FA session (31 minutes ago)
        session([
            '2fa_verified' => true, 
            '2fa_verified_at' => now()->subMinutes(31)
        ]);

        $request = Request::create('/test');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('two-factor/challenge', $response->headers->get('Location'));
        
        // Session should be cleared
        $this->assertFalse(session('2fa_verified', false));
        $this->assertNull(session('2fa_verified_at'));
    }

    public function test_middleware_allows_logout_route()
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $secret = $this->google2fa->generateSecretKey();
        
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            '2fa_enabled' => true
        ]);

        $request = Request::create('/logout', 'POST');
        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['POST'], '/logout', []);
            $route->name('logout');
            return $route;
        });
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }
}