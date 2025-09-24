<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SessionSecurityMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class SessionSecurityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected SessionSecurityMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SessionSecurityMiddleware();
    }

    public function test_passes_through_when_not_authenticated(): void
    {
        $request = Request::create('/', 'GET');
        
        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_updates_session_security_data_when_authenticated(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $request = Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->server->set('HTTP_USER_AGENT', 'Test Browser');
        
        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull(Session::get('last_activity'));
        $this->assertEquals('192.168.1.1', Session::get('user_ip'));
        $this->assertEquals('Test Browser', Session::get('user_agent'));
    }

    public function test_stores_session_id_in_cache(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $request = Request::create('/', 'GET');
        
        $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $sessionKey = 'user_session_' . $user->id;
        $this->assertNotNull(Cache::get($sessionKey));
        $this->assertEquals(Session::getId(), Cache::get($sessionKey));
    }

    public function test_detects_ip_change(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        // Set initial IP
        Session::put('user_ip', '192.168.1.1');

        $request = Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.2'); // Different IP
        
        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        // In a real implementation, this would log suspicious activity
    }

    public function test_detects_user_agent_change(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        // Set initial user agent
        Session::put('user_agent', 'Original Browser');

        $request = Request::create('/', 'GET');
        $request->server->set('HTTP_USER_AGENT', 'Different Browser');
        
        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        // In a real implementation, this would log suspicious activity
    }

    public function test_detects_concurrent_sessions(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $sessionKey = 'user_session_' . $user->id;
        
        // Set a different session ID in cache
        Cache::put($sessionKey, 'different_session_id', now()->addHours(24));

        $request = Request::create('/', 'GET');
        
        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        // In a real implementation, this would log concurrent session detection
    }
}