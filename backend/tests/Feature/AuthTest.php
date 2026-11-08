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
            ->assertJsonValidationErrors(['email' => 'Correo o contraseña incorrectos.']);
    }

    public function test_login_returns_same_message_for_unknown_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nadie@pos.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Correo o contraseña incorrectos.']);
    }

    public function test_login_validation_messages_are_in_spanish(): void
    {
        $this->postJson('/api/login', ['email' => 'no-es-un-correo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'El campo correo electrónico debe ser un correo electrónico válido (ej. nombre@dominio.com).',
                'password' => 'El campo contraseña es obligatorio.',
            ]);
    }

    public function test_login_is_throttled_after_too_many_failed_attempts(): void
    {
        User::factory()->create(['email' => 'cajero@pos.com']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => 'cajero@pos.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'email' => 'cajero@pos.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_missing_record_returns_spanish_not_found_message(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/route-that-does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('message', 'El registro solicitado no existe o fue eliminado.');
    }

    public function test_unauthenticated_request_returns_spanish_message(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
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
