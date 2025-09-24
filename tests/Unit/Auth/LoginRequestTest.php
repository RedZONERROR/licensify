<?php

namespace Tests\Unit\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_returns_true(): void
    {
        $request = new LoginRequest();
        
        $this->assertTrue($request->authorize());
    }

    public function test_rules_returns_correct_validation_rules(): void
    {
        $request = new LoginRequest();
        $rules = $request->rules();
        
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('email', $rules['email']);
        $this->assertContains('required', $rules['password']);
    }

    public function test_authenticate_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        RateLimiter::clear($request->throttleKey());

        $request->authenticate();

        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_authenticate_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        RateLimiter::clear($request->throttleKey());

        $this->expectException(ValidationException::class);
        
        $request->authenticate();
    }

    public function test_authenticate_throws_exception_when_rate_limited(): void
    {
        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Simulate rate limiting
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($request->throttleKey());
        }

        $this->expectException(ValidationException::class);
        
        $request->authenticate();
    }

    public function test_throttle_key_includes_email_and_ip(): void
    {
        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'test@example.com',
        ]);

        $throttleKey = $request->throttleKey();
        
        $this->assertStringContainsString('test@example.com', $throttleKey);
        $this->assertStringContainsString($request->ip(), $throttleKey);
    }
}