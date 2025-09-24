<?php

namespace Tests\Unit\Auth;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    protected LoginController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new LoginController();
    }

    public function test_create_returns_login_view(): void
    {
        $response = $this->controller->create();
        
        $this->assertEquals('auth.login', $response->name());
    }

    public function test_store_authenticates_user_and_redirects(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $request->setLaravelSession($this->app['session.store']);

        // Mock the authenticate method
        $mockRequest = $this->createMock(LoginRequest::class);
        $mockRequest->expects($this->once())
                   ->method('authenticate');
        $mockRequest->expects($this->once())
                   ->method('session')
                   ->willReturn($this->app['session.store']);

        Auth::shouldReceive('user')->andReturn($user);

        $response = $this->controller->store($mockRequest);

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_destroy_logs_out_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/logout', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $response = $this->controller->destroy($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/', $response->getTargetUrl());
    }
}