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

        $response = $this->getJson('/api/me/preferences')
            ->assertOk()
            ->assertJsonPath('preferences.theme', 'dark')
            ->assertJsonPath('preferences.language', 'es')
            ->assertJsonCount(2, 'groups');

        $system = collect($response->json('groups'))->firstWhere('key', 'personal.system');
        $this->assertNotNull($system);
        $this->assertTrue($system['can_edit']);
        $this->assertCount(5, $system['fields']);
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

    public function test_printer_group_is_personal_and_lists_admin_defined_printers(): void
    {
        SystemSetting::set('available_printers', "POS-80 Caja 1\nPOS-58 Cocina", 'string', 'printing');
        Sanctum::actingAs($this->userWithPermissions([]));

        $response = $this->getJson('/api/me/preferences')->assertOk();

        $groups = collect($response->json('groups'));
        $printer = $groups->firstWhere('key', 'personal.printing');

        $this->assertNotNull($printer);
        $this->assertSame('Impresora', $printer['label']);
        $this->assertTrue($printer['can_edit']);
        $this->assertCount(4, $printer['fields']);

        $name = collect($printer['fields'])->firstWhere('key', 'printer_name');
        $this->assertSame('select', $name['type']);
        $this->assertSame([
            '' => 'Impresora predeterminada del navegador',
            'POS-80 Caja 1' => 'POS-80 Caja 1',
            'POS-58 Cocina' => 'POS-58 Cocina',
        ], $name['options']);
    }

    public function test_printer_preferences_are_saved_per_user(): void
    {
        $cashier = $this->userWithPermissions([]);
        $other = $this->userWithPermissions([]);
        Sanctum::actingAs($cashier);

        $this->putJson('/api/me/preferences', [
            'preferences' => ['printer_name' => 'POS-80 Caja 1', 'printer_copies' => 2],
        ])
            ->assertOk()
            ->assertJsonPath('preferences.printer_name', 'POS-80 Caja 1')
            ->assertJsonPath('preferences.printer_copies', 2);

        $this->putJson('/api/me/preferences', ['preferences' => ['printer_copies' => 0]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preferences.printer_copies');

        Sanctum::actingAs($other);
        $this->getJson('/api/me/preferences')
            ->assertOk()
            ->assertJsonPath('preferences.printer_name', '')
            ->assertJsonPath('preferences.printer_copies', 1);
    }

    public function test_preferences_require_authentication(): void
    {
        $this->getJson('/api/me/preferences')->assertUnauthorized();
        $this->putJson('/api/me/preferences', ['preferences' => ['theme' => 'dark']])->assertUnauthorized();
    }
}
