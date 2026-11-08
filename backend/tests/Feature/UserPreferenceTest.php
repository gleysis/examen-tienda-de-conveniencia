<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class UserPreferenceTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    public function test_user_without_permissions_gets_system_defaults(): void
    {
        SystemSetting::set('theme', 'dark', 'string', 'system');
        Sanctum::actingAs($this->userWithPermissions([]));

        $this->getJson('/api/me/preferences')
            ->assertOk()
            ->assertJsonPath('preferences.theme', 'dark')
            ->assertJsonPath('preferences.language', 'es')
            ->assertJsonPath('group.can_edit', true)
            ->assertJsonCount(5, 'group.fields');
    }

    public function test_user_preferences_override_system_defaults_only_for_that_user(): void
    {
        $cashier = $this->userWithPermissions([]);
        $other = $this->userWithPermissions([]);
        Sanctum::actingAs($cashier);

        $this->putJson('/api/me/preferences', ['preferences' => ['theme' => 'dark', 'font_size' => 'large']])
            ->assertOk()
            ->assertJsonPath('preferences.theme', 'dark')
            ->assertJsonPath('preferences.font_size', 'large');

        $this->assertSame(['theme' => 'dark', 'font_size' => 'large'], $cashier->fresh()->preferences);
        $this->assertSame('light', SystemSetting::get('theme', 'light'));

        Sanctum::actingAs($other);
        $this->getJson('/api/me/preferences')->assertOk()->assertJsonPath('preferences.theme', 'light');
    }

    public function test_null_value_restores_system_default(): void
    {
        $user = $this->userWithPermissions([]);
        $user->update(['preferences' => ['theme' => 'dark', 'language' => 'en']]);
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', ['preferences' => ['theme' => null]])
            ->assertOk()
            ->assertJsonPath('preferences.theme', 'light')
            ->assertJsonPath('preferences.language', 'en');

        $this->assertSame(['language' => 'en'], $user->fresh()->preferences);
    }

    public function test_preferences_are_validated_and_unknown_keys_ignored(): void
    {
        $user = $this->userWithPermissions([]);
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', ['preferences' => ['theme' => 'neon']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preferences.theme');

        $this->putJson('/api/me/preferences', ['preferences' => ['tax_rate' => 0, 'language' => 'en']])
            ->assertOk();

        $this->assertSame(['language' => 'en'], $user->fresh()->preferences);
        $this->assertNull(SystemSetting::where('key', 'tax_rate')->first());
    }

    public function test_preferences_require_authentication(): void
    {
        $this->getJson('/api/me/preferences')->assertUnauthorized();
        $this->putJson('/api/me/preferences', ['preferences' => ['theme' => 'dark']])->assertUnauthorized();
    }
}
