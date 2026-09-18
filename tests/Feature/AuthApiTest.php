<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_returns_a_token_and_validation_errors_use_the_api_envelope(): void
    {
        $invalid = $this->postJson('/api/register', [
            'name' => 'Ada',
            'email' => 'not-an-email',
            'password' => 'secret123',
            'password_confirmation' => 'different',
        ]);

        $invalid->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['email', 'password']]);

        $registered = $this->postJson('/api/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $registered->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registered successfully.')
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->assertJsonStructure(['success', 'message', 'data' => ['user' => ['id', 'name', 'email'], 'token']])
            ->assertJsonMissingPath('data.user.password');

        $token = $registered->json('data.token');

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'ada@example.com');
    }

    public function test_login_logout_and_me_follow_the_token_authentication_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'secret123',
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'incorrect',
        ])->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Invalid credentials.']);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged in successfully.')
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $token = $login->json('data.token');

        $this->withToken($token)->postJson('/api/logout')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Logged out successfully.',
                'data' => null,
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/me')
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.']);
    }

    public function test_protected_routes_reject_requests_without_a_token(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.']);

        $this->postJson('/api/logout')
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.']);
    }
}
