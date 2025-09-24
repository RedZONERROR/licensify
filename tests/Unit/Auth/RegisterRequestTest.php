<?php

namespace Tests\Unit\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_returns_true(): void
    {
        $request = new RegisterRequest();
        
        $this->assertTrue($request->authorize());
    }

    public function test_rules_returns_correct_validation_rules(): void
    {
        $request = new RegisterRequest();
        $rules = $request->rules();
        
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayHasKey('privacy_policy', $rules);
        
        $this->assertContains('required', $rules['name']);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('email', $rules['email']);
        $this->assertContains('unique:'.User::class, $rules['email']);
        $this->assertContains('required', $rules['password']);
        $this->assertContains('confirmed', $rules['password']);
        $this->assertContains('required', $rules['privacy_policy']);
        $this->assertContains('accepted', $rules['privacy_policy']);
    }

    public function test_messages_returns_custom_privacy_policy_messages(): void
    {
        $request = new RegisterRequest();
        $messages = $request->messages();
        
        $this->assertArrayHasKey('privacy_policy.required', $messages);
        $this->assertArrayHasKey('privacy_policy.accepted', $messages);
        $this->assertEquals('You must accept the privacy policy to register.', $messages['privacy_policy.required']);
        $this->assertEquals('You must accept the privacy policy to register.', $messages['privacy_policy.accepted']);
    }

    public function test_prepare_for_validation_converts_email_to_lowercase(): void
    {
        $request = RegisterRequest::create('/register', 'POST', [
            'email' => 'TEST@EXAMPLE.COM',
        ]);

        $request->prepareForValidation();
        
        $this->assertEquals('test@example.com', $request->email);
    }
}