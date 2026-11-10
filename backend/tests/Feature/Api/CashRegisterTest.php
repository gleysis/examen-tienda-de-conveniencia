<?php

namespace Tests\Feature\Api;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    public function test_creates_cash_register(): void
    {
        $manager = $this->userWithPermissions(['cash-registers.create']);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/cash-registers', [
            'name' => 'Caja 1',
            'location' => 'Planta baja',
            'opening_balance' => 100.00,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('cash_registers', [
            'name' => 'Caja 1',
            'opening_balance' => 100.00,
            'current_balance' => 100.00,
            'status' => 'open',
            'user_id' => $manager->id,
        ]);
    }

    public function test_cash_sale_updates_open_register_automatically(): void
    {
        $cashier = $this->userWithPermissions(['sales.create']);
        $register = CashRegister::create([
            'name' => 'Caja 1',
            'user_id' => $cashier->id,
            'status' => 'open',
            'opening_balance' => 100.00,
            'current_balance' => 100.00,
            'expected_closing' => 100.00,
        ]);
        SystemSettings::updateMany(['tax_rate' => 0]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 25.00]);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'payment_method' => 'cash',
        ])->assertOk();

        $register->refresh();
        $this->assertSame(150.0, $register->current_balance);
        $this->assertSame(50.0, $register->cash_sales);
        $this->assertSame(50.0, $register->total_sales);
        $this->assertSame(150.0, $register->expected_closing);
        $this->assertDatabaseHas('sales', ['cash_register_id' => $register->id]);
    }

    public function test_card_sale_does_not_add_to_drawer_balance(): void
    {
        $cashier = $this->userWithPermissions(['sales.create']);
        $register = CashRegister::create([
            'name' => 'Caja 1',
            'user_id' => $cashier->id,
            'status' => 'open',
            'opening_balance' => 100.00,
            'current_balance' => 100.00,
            'expected_closing' => 100.00,
        ]);
        SystemSettings::updateMany(['tax_rate' => 0]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 80.00]);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'card',
        ])->assertOk();

        $register->refresh();
        $this->assertSame(100.0, $register->current_balance);
        $this->assertSame(80.0, $register->card_sales);
        $this->assertSame(100.0, $register->expected_closing);
    }

    public function test_closing_register_computes_cash_difference(): void
    {
        $manager = $this->userWithPermissions(['cash-registers.close']);
        $register = CashRegister::create([
            'name' => 'Caja 1',
            'user_id' => $manager->id,
            'status' => 'open',
            'opening_balance' => 100.00,
            'current_balance' => 150.00,
            'cash_sales' => 50.00,
            'total_sales' => 50.00,
            'expected_closing' => 150.00,
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/cash-registers/{$register->id}/close", [
            'actual_closing' => 145.00,
        ]);

        $response->assertOk();
        $register->refresh();
        $this->assertSame('closed', $register->status);
        $this->assertSame(145.0, $register->actual_closing);
        $this->assertSame(-5.0, $register->difference);
        $this->assertNotNull($register->closed_at);
    }

    public function test_cannot_sell_after_closing_own_register(): void
    {
        $cashier = $this->userWithPermissions(['sales.create', 'cash-registers.close']);
        $register = CashRegister::factory()->openFor($cashier)->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 25.00]);
        Sanctum::actingAs($cashier);

        $this->postJson("/api/cash-registers/{$register->id}/close", ['actual_closing' => 100.00])->assertOk();

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
        ])->assertUnprocessable();

        $register->refresh();
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(0.0, $register->total_sales);
        $this->assertSame(100.0, $register->expected_closing);
    }

    public function test_cannot_close_an_already_closed_register(): void
    {
        $manager = $this->userWithPermissions(['cash-registers.close']);
        $register = CashRegister::create([
            'name' => 'Caja 1',
            'status' => 'closed',
        ]);
        Sanctum::actingAs($manager);

        $this->postJson("/api/cash-registers/{$register->id}/close", [
            'actual_closing' => 100.00,
        ])->assertStatus(422);
    }

    public function test_returns_current_open_register_for_user(): void
    {
        $cashier = $this->userWithPermissions(['cash-registers.view']);
        CashRegister::create([
            'name' => 'Caja 1',
            'user_id' => $cashier->id,
            'status' => 'open',
            'opening_balance' => 50.00,
        ]);
        Sanctum::actingAs($cashier);

        $response = $this->getJson('/api/cash-registers/current');

        $response->assertOk()->assertJsonPath('cash_register.name', 'Caja 1');
    }

    public function test_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/cash-registers')->assertForbidden();
        $this->postJson('/api/cash-registers', [
            'name' => 'Caja 1',
            'opening_balance' => 100,
        ])->assertForbidden();
    }
}
