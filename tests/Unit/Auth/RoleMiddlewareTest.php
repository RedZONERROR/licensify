<?php

namespace Tests\Unit\Auth;

use App\Enums\Role;
use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected RoleMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RoleMiddleware();
    }

    public function test_redirects_unauthenticated_users(): void
    {
        $request = Request::create('/admin');
        
        $response = $this->middleware->handle($request, function () {
            return response('OK');
        }, 'admin');

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    public function test_allows_user_with_correct_role(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        Auth::login($user);

        $request = Request::create('/admin');
        
        $response = $this->middleware->handle($request, function () {
            return response('OK');
        }, 'admin');

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_denies_user_with_incorrect_role(): void
    {
        $user = User::factory()->create(['role' => Role::USER]);
        Auth::login($user);

        $request = Request::create('/admin');
        
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        
        $this->middleware->handle($request, function () {
            return response('OK');
        }, 'admin');
    }

    public function test_allows_user_with_multiple_valid_roles(): void
    {
        $user = User::factory()->create(['role' => Role::DEVELOPER]);
        Auth::login($user);

        $request = Request::create('/admin');
        
        $response = $this->middleware->handle($request, function () {
            return response('OK');
        }, 'admin', 'developer');

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_admin_can_access_all_roles(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        Auth::login($user);

        $request = Request::create('/test');
        
        $response = $this->middleware->handle($request, function () {
            return response('OK');
        }, 'user', 'reseller', 'developer', 'admin');

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_reseller_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['role' => Role::RESELLER]);
        Auth::login($user);

        $request = Request::create('/admin');
        
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        
        $this->middleware->handle($request, function () {
            return response('OK');
        }, 'admin');
    }

    public function test_user_cannot_access_privileged_routes(): void
    {
        $user = User::factory()->create(['role' => Role::USER]);
        Auth::login($user);

        $request = Request::create('/reseller');
        
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        
        $this->middleware->handle($request, function () {
            return response('OK');
        }, 'reseller', 'developer', 'admin');
    }
}