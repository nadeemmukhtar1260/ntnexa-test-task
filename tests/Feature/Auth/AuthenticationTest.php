<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ali Khan',
            'email' => 'ali@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'ali@example.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['success', 'message', 'data' => ['user' => ['id', 'name', 'email'], 'token']])
            ->assertJsonMissingPath('data.user.password');

        $user = User::firstWhere('email', 'ali@example.com');
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertNotSame('secret-password', $user->password);
    }

    #[Test]
    public function registration_is_validated(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    #[Test]
    public function a_user_can_login_and_receives_a_token(): void
    {
        User::factory()->create(['email' => 'ali@example.com', 'password' => 'secret-password']);

        $response = $this->postJson('/api/login', [
            'email' => 'ali@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'ali@example.com')
            ->assertJsonMissingPath('data.user.password');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame(1, PersonalAccessToken::count());
    }

    #[Test]
    public function login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'ali@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/login', [
            'email' => 'ali@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Invalid credentials.']);

        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[Test]
    public function login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function the_current_user_can_be_retrieved_with_a_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');
    }

    #[Test]
    public function logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertSame(0, $user->tokens()->count());
    }

    #[Test]
    public function protected_routes_reject_requests_without_a_token(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated.']);

        $this->getJson('/api/leads')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    #[Test]
    public function protected_routes_reject_an_invalid_token(): void
    {
        $this->withToken('invalid-token')->getJson('/api/leads')->assertUnauthorized();
    }

    #[Test]
    public function unauthenticated_requests_get_json_even_without_accept_header(): void
    {
        // Plain get() does not send "Accept: application/json".
        $this->get('/api/leads')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function an_authenticated_user_via_sanctum_helper_can_access_protected_routes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/user')->assertOk();
    }
}
