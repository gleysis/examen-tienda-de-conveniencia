<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_user_with_roles(): void
    {
        $user = User::factory()->create([
            'email' => 'cajero@pos.com',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'cajero@pos.com',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'roles'],
            ]);
    }

    public function test_login_returns_422_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'cajero@pos.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'cajero@pos.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_protected_route_returns_401_without_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_user_endpoint_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }
}
