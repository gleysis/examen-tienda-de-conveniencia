<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Product;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    public function test_schema_returns_catalog_groups_with_defaults(): void
    {
        $admin = $this->userWithPermissions(['settings.view']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/settings/schema');

        $response->assertOk()->assertJsonStructure([
            'groups' => [
                '*' => ['key', 'label', 'fields' => [['key', 'label', 'type', 'value']]],
            ],
        ]);

        $groups = collect($response->json('groups'));
        $store = $groups->firstWhere('key', 'store');

        $this->assertNotNull($store);
        $this->assertEquals('Mi Tienda', collect($store['fields'])->firstWhere('key', 'store_name')['value']);
    }

    public function test_schema_requires_permission(): void
    {
        $user = $this->userWithPermissions([]);
        Sanctum::actingAs($user);

        $this->getJson('/api/settings/schema')->assertForbidden();
    }

    public function test_schema_only_returns_groups_the_user_can_edit_when_lacking_view_permission(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.inventory.edit']));

        $this->getJson('/api/settings/schema')
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.key', 'inventory')
            ->assertJsonPath('groups.0.can_edit', true)
            ->assertJsonMissingPath('groups.0.edit_permission');
    }

    public function test_schema_flags_editable_groups_per_permission(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.view', 'settings.pos.edit']));

        $groups = collect($this->getJson('/api/settings/schema')->assertOk()->json('groups'))
            ->pluck('can_edit', 'key');

        $this->assertSame(
            ['store' => false, 'sales' => false, 'pos' => true, 'inventory' => false, 'system' => false],
            $groups->all()
        );
    }

    public function test_group_permission_only_allows_editing_that_group(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.inventory.edit']));

        $this->putJson('/api/settings/bulk', ['values' => ['low_stock_default' => 8]])->assertOk();
        $this->assertSame(8, SystemSetting::get('low_stock_default'));

        $this->putJson('/api/settings/bulk', ['values' => ['low_stock_default' => 9, 'tax_rate' => 0]])
            ->assertForbidden();
        $this->postJson('/api/settings/reset', ['group' => 'sales'])->assertForbidden();
        $this->postJson('/api/settings/reset', ['group' => 'inventory'])->assertOk();

        $this->assertNull(SystemSetting::where('key', 'tax_rate')->first());
        $this->assertSame(5, SystemSetting::get('low_stock_default'));
    }

    public function test_bulk_update_requires_an_edit_permission(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.view']));

        $this->putJson('/api/settings/bulk', ['values' => ['store_name' => 'X']])->assertForbidden();
        $this->putJson('/api/settings/bulk', ['values' => ['unknown_key' => 'X']])->assertForbidden();
    }

    public function test_bulk_update_saves_catalog_values(): void
    {
        $admin = $this->userWithPermissions(['settings.edit']);
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/settings/bulk', [
            'values' => [
                'store_name' => 'Supermercado Central',
                'tax_rate' => 7,
                'allow_negative_stock' => true,
            ],
        ]);

        $response->assertOk();

        $this->assertEquals('Supermercado Central', SystemSetting::get('store_name'));
        $this->assertSame(7.0, SystemSetting::get('tax_rate'));
        $this->assertTrue(SystemSetting::get('allow_negative_stock'));
    }

    public function test_bulk_update_validates_values(): void
    {
        $admin = $this->userWithPermissions(['settings.edit']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/settings/bulk', [
            'values' => ['tax_rate' => 150],
        ])->assertUnprocessable();
    }

    public function test_bulk_update_ignores_unknown_keys(): void
    {
        $admin = $this->userWithPermissions(['settings.edit']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/settings/bulk', [
            'values' => ['unknown_key' => 'value'],
        ])->assertOk();

        $this->assertNull(SystemSetting::where('key', 'unknown_key')->first());
    }

    public function test_public_settings_are_casted_and_include_defaults(): void
    {
        $user = $this->userWithPermissions([]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/settings/public');

        $response->assertOk();

        $settings = $response->json('settings');
        $this->assertIsNumeric($settings['tax_rate']);
        $this->assertIsBool($settings['auto_print_receipt']);
        $this->assertIsBool($settings['allow_negative_stock']);
        $this->assertArrayNotHasKey('invoice_prefix', $settings);
        $this->assertArrayNotHasKey('low_stock_default', $settings);
    }

    public function test_public_settings_use_stored_value_even_if_row_flag_is_private(): void
    {
        SystemSetting::create([
            'key' => 'allow_negative_stock',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'sales',
            'is_public' => false,
        ]);
        Sanctum::actingAs($this->userWithPermissions([]));

        $this->getJson('/api/settings/public')
            ->assertOk()
            ->assertJsonPath('settings.allow_negative_stock', true);
    }

    public function test_catalog_keys_cannot_be_created_updated_or_deleted_via_crud(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.edit']));
        $setting = SystemSetting::create([
            'key' => 'tax_rate',
            'value' => '15',
            'type' => 'float',
            'group' => 'sales',
        ]);

        $this->postJson('/api/settings', [
            'key' => 'store_name',
            'value' => 'X',
            'type' => 'string',
            'group' => 'store',
        ])->assertUnprocessable()->assertJsonValidationErrors('key');

        $this->putJson("/api/settings/{$setting->id}", ['value' => 'abc'])->assertUnprocessable();
        $this->deleteJson("/api/settings/{$setting->id}")->assertUnprocessable();

        $this->assertSame('15', $setting->fresh()->value);
    }

    public function test_custom_text_setting_can_be_created(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.edit']));

        $this->postJson('/api/settings', [
            'key' => 'custom_notice',
            'value' => 'Aviso largo',
            'type' => 'text',
            'group' => 'general',
        ])->assertOk();

        $this->assertDatabaseHas('system_settings', ['key' => 'custom_notice', 'type' => 'text']);
    }

    public function test_set_endpoint_validates_catalog_keys(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.edit']));

        $this->postJson('/api/settings/set', ['key' => 'tax_rate', 'value' => 500])
            ->assertUnprocessable();

        $response = $this->postJson('/api/settings/set', ['key' => 'tax_rate', 'value' => 12])->assertOk();
        $this->assertEquals(12, $response->json('value'));

        $setting = SystemSetting::where('key', 'tax_rate')->first();
        $this->assertSame('float', $setting->type);
        $this->assertTrue($setting->is_public);
    }

    public function test_public_settings_are_available_without_authentication(): void
    {
        $this->getJson('/api/settings/public')
            ->assertOk()
            ->assertJsonPath('settings.theme', 'light')
            ->assertJsonPath('settings.language', 'es');
    }

    public function test_bulk_update_records_history_only_for_changed_values(): void
    {
        $admin = $this->userWithPermissions(['settings.edit', 'settings.view']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/settings/bulk', [
            'values' => ['store_name' => 'Nueva Tienda', 'tax_rate' => 15],
        ])->assertOk();

        $this->assertDatabaseCount('system_setting_changes', 1);
        $this->assertDatabaseHas('system_setting_changes', [
            'key' => 'store_name',
            'old_value' => 'Mi Tienda',
            'new_value' => 'Nueva Tienda',
            'user_id' => $admin->id,
        ]);

        $this->getJson('/api/settings/history')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'store_name')
            ->assertJsonPath('data.0.label', 'Nombre de la tienda')
            ->assertJsonPath('data.0.user.name', $admin->name);
    }

    public function test_reset_restores_group_defaults(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.edit']));

        $this->putJson('/api/settings/bulk', [
            'values' => ['tax_rate' => 7, 'invoice_prefix' => 'FAC'],
        ])->assertOk();

        $this->postJson('/api/settings/reset', ['group' => 'sales'])->assertOk();

        $this->assertSame(15.0, SystemSetting::get('tax_rate'));
        $this->assertSame('INV', SystemSetting::get('invoice_prefix'));
        $this->postJson('/api/settings/reset', ['group' => 'nope'])->assertUnprocessable();
    }

    public function test_bulk_update_validates_select_options(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.edit']));

        $this->putJson('/api/settings/bulk', ['values' => ['theme' => 'neon']])
            ->assertUnprocessable();

        $this->putJson('/api/settings/bulk', ['values' => ['theme' => 'dark', 'language' => 'en']])
            ->assertOk();

        $this->assertSame('dark', SystemSetting::get('theme'));
    }

    public function test_logo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->userWithPermissions(['settings.edit']));
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->post('/api/settings/logo', [
            'logo' => UploadedFile::fake()->createWithContent('logo.png', $png),
        ], ['Accept' => 'application/json'])->assertOk();

        $url = SystemSetting::get('store_logo');
        $path = Str::after($url, '/storage/');
        $this->assertStringStartsWith('branding/', $path);
        Storage::disk('public')->assertExists($path);

        $this->deleteJson('/api/settings/logo')->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertSame('', SystemSetting::get('store_logo'));
    }

    public function test_bulk_update_cannot_set_logo_url(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.edit']));

        $this->putJson('/api/settings/bulk', ['values' => ['store_logo' => 'https://evil.test/x.png']])
            ->assertOk();

        $this->assertNull(SystemSetting::where('key', 'store_logo')->first());
    }

    private function discountCode(string $code, float $percentage): Discount
    {
        return Discount::create([
            'code' => $code,
            'name' => $code,
            'type' => 'percentage',
            'value' => $percentage,
        ]);
    }

    public function test_sale_rejects_discounts_when_disabled(): void
    {
        $cashier = $this->userWithPermissions(['sales.create', 'settings.edit']);
        $product = Product::factory()->create(['stock' => 10, 'price' => 100]);
        $this->discountCode('PROMO10', 10);
        Sanctum::actingAs($cashier);

        $this->putJson('/api/settings/bulk', ['values' => ['allow_discounts' => false]])->assertOk();

        $this->postJson('/api/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'discount_code' => 'PROMO10',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Los descuentos en ventas están deshabilitados.');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_sale_rejects_discount_above_maximum_percent(): void
    {
        $cashier = $this->userWithPermissions(['sales.create', 'settings.edit']);
        $product = Product::factory()->create(['stock' => 10, 'price' => 100]);
        $this->discountCode('PROMO15', 15);
        $this->discountCode('PROMO10', 10);
        Sanctum::actingAs($cashier);

        $this->putJson('/api/settings/bulk', ['values' => ['max_discount_percent' => 10]])->assertOk();

        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ];

        $this->postJson('/api/sales', [...$payload, 'discount_code' => 'PROMO15'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El descuento supera el máximo permitido (10% del subtotal).');
        $this->postJson('/api/sales', [...$payload, 'discount_code' => 'PROMO10'])
            ->assertOk()
            ->assertJsonPath('sale.discount', 10);
    }

    public function test_sale_reports_products_that_ran_out_of_stock(): void
    {
        $cashier = $this->userWithPermissions(['sales.create', 'settings.edit']);
        $product = Product::factory()->create(['stock' => 2, 'name' => 'Leche']);
        Sanctum::actingAs($cashier);

        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 10]],
            'payment_method' => 'cash',
        ];

        $this->postJson('/api/sales', $payload)
            ->assertOk()
            ->assertJsonPath('out_of_stock_products', ['Leche']);

        $other = Product::factory()->create(['stock' => 1]);
        $this->putJson('/api/settings/bulk', ['values' => ['notify_out_of_stock' => false]])->assertOk();

        $this->postJson('/api/sales', [
            ...$payload,
            'items' => [['product_id' => $other->id, 'quantity' => 1, 'unit_price' => 10]],
        ])->assertOk()->assertJsonPath('out_of_stock_products', []);
    }

    public function test_dashboard_alerts_respect_inventory_settings(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['reports.view', 'settings.edit']));
        Product::factory()->create(['stock' => 2, 'min_stock' => 5, 'is_active' => true]);
        Product::factory()->create(['stock' => 0, 'is_active' => true]);

        $this->getJson('/api/dashboard/alerts')
            ->assertOk()
            ->assertJsonCount(1, 'low_stock')
            ->assertJsonCount(1, 'out_of_stock');

        $this->putJson('/api/settings/bulk', [
            'values' => ['low_stock_alerts' => false, 'notify_out_of_stock' => false],
        ])->assertOk();

        $this->getJson('/api/dashboard/alerts')
            ->assertOk()
            ->assertJsonCount(0, 'low_stock')
            ->assertJsonCount(0, 'out_of_stock');
    }

    public function test_low_stock_default_applies_to_products_without_own_minimum(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['reports.view', 'settings.edit']));
        Product::factory()->create(['stock' => 4, 'min_stock' => 0, 'is_active' => true]);
        Product::factory()->create(['stock' => 8, 'min_stock' => 0, 'is_active' => true]);

        $this->getJson('/api/dashboard/alerts')->assertOk()->assertJsonCount(1, 'low_stock');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('cards.low_stock_count', 1);

        $this->putJson('/api/settings/bulk', ['values' => ['low_stock_default' => 10]])->assertOk();

        $this->getJson('/api/dashboard/alerts')->assertOk()->assertJsonCount(2, 'low_stock');
    }

    public function test_history_per_page_is_capped(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.view']));

        $this->getJson('/api/settings/history?per_page=100000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_sale_uses_invoice_prefix_from_settings(): void
    {
        $cashier = $this->userWithPermissions(['sales.create', 'settings.edit']);
        $product = Product::factory()->create(['stock' => 10]);
        Sanctum::actingAs($cashier);

        $this->putJson('/api/settings/bulk', [
            'values' => ['invoice_prefix' => 'FAC'],
        ])->assertOk();

        $response = $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 50.00],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();
        $this->assertStringStartsWith('FAC-', $response->json('sale.invoice_number'));
    }

    public function test_sale_allows_negative_stock_when_enabled(): void
    {
        $cashier = $this->userWithPermissions(['sales.create', 'settings.edit']);
        $product = Product::factory()->create(['stock' => 1]);
        Sanctum::actingAs($cashier);

        $this->putJson('/api/settings/bulk', [
            'values' => ['allow_negative_stock' => true],
        ])->assertOk();

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 10.00],
            ],
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertEquals(-4, $product->fresh()->stock);
    }
}
