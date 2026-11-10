<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    public function test_user_without_permissions_can_change_own_password_and_other_sessions_are_revoked(): void
    {
        $user = $this->userWithPermissions([]);
        $user->update(['password' => 'old-password']);
        $currentToken = $user->createToken('api-token')->plainTextToken;
        $user->createToken('api-token');

        $this->withToken($currentToken)
            ->putJson('/api/me/password', [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->userWithPermissions([]);
        $user->update(['password' => 'old-password']);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/me/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed_and_different(): void
    {
        $user = $this->userWithPermissions([]);
        $user->update(['password' => 'old-password']);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/me/password', [
                'current_password' => 'old-password',
                'password' => 'old-password',
                'password_confirmation' => 'something-else',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->putJson('/api/me/password', [])->assertUnauthorized();
    }
}
