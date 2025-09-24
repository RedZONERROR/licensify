<?php

namespace Tests\Unit\Auth;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected RegisterController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new RegisterController();
    }

    public function test_create_returns_register_view(): void
    {
        $response = $this->controller->create();
        
        $this->assertEquals('auth.register', $response->name());
    }

    public function test_store_creates_user_and_logs_in(): void
    {
        Event::fake();

        $requestData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'privacy_policy' => true,
        ];

        $request = RegisterRequest::create('/register', 'POST', $requestData);
        $request->setLaravelSession($this->app['session.store']);

        // Mock the validated method
        $mockRequest = $this->createMock(RegisterRequest::class);
        $mockRequest->expects($this->once())
                   ->method('session')
                   ->willReturn($this->app['session.store']);

        // Mock request properties
        $mockRequest->name = 'John Doe';
        $mockRequest->email = 'john@example.com';
        $mockRequest->password = 'password123';

        $response = $this->controller->store($mockRequest);

        $this->assertEquals(302, $response->getStatusCode());
        
        // Verify user was created
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => User::ROLE_USER,
        ]);

        // Verify password was hashed
        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));

        // Verify Registered event was fired
        Event::assertDispatched(Registered::class);
    }
}