<?php

namespace Tests\Unit\Auth;

use App\Http\Middleware\EnsureSessionSecurity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class SessionSecurityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected EnsureSessionSecurity $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureSessionSecurity();
    }

    public function test_allows_unauthenticated_users(): void
    {
        $request = Request::create('/');
        
        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_sets_initial_ip_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);
        
        $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertEquals('192.168.1.1', Session::get('user_ip'));
    }

    public function test_detects_ip_change_and_logs_out_user(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        Session::put('user_ip', '192.168.1.1');

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.2']);
        
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        
        $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertFalse(Auth::check());
    }

    public function test_updates_last_activity_timestamp(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        
        $initialTime = time() - 100;
        Session::put('last_activity', $initialTime);

        $request = Request::create('/');
        
        $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertGreaterThan($initialTime, Session::get('last_activity'));
    }

    public function test_enforces_idle_timeout(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        
        // Set last activity to 2 hours ago (exceeds 1 hour default timeout)
        Session::put('last_activity', time() - 7200);

        $request = Request::create('/');
        
        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertFalse(Auth::check());
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    public function test_enforces_absolute_timeout(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        
        // Set session start to 10 hours ago (exceeds 8 hour default timeout)
        Session::put('session_start', time() - 36000);
        Session::put('last_activity', time()); // Recent activity

        $request = Request::create('/');
        
        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertFalse(Auth::check());
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    public function test_allows_valid_session_within_timeouts(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        
        // Set recent activity and session start
        Session::put('last_activity', time() - 300); // 5 minutes ago
        Session::put('session_start', time() - 1800); // 30 minutes ago

        $request = Request::create('/');
        
        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertTrue(Auth::check());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_sets_session_start_if_not_present(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $request = Request::create('/');
        
        $this->middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertNotNull(Session::get('session_start'));
        $this->assertLessThanOrEqual(time(), Session::get('session_start'));
    }
}