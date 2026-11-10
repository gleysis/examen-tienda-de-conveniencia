<?php

namespace Tests\Feature\Api;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    private function cashierWithOpenRegister(): User
    {
        $cashier = $this->userWithPermissions(['sales.create']);
        CashRegister::factory()->openFor($cashier)->create();

        return $cashier;
    }

    public function test_creates_sale_and_decrements_stock(): void
    {
        $cashier = $this->cashierWithOpenRegister();
        $product = Product::factory()->create(['stock' => 10, 'price' => 20.00]);
        Sanctum::actingAs($cashier);

        $response = $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 20.00],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('sales', [
            'user_id' => $cashier->id,
            'subtotal' => 60.00,
            'tax' => 9.00,
            'total' => 69.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 20.00,
            'subtotal' => 60.00,
        ]);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'user_id' => $cashier->id,
            'type' => 'exit',
            'quantity' => 3,
            'previous_stock' => 10,
            'new_stock' => 7,
        ]);
    }

    public function test_does_not_create_sale_when_stock_is_insufficient(): void
    {
        $cashier = $this->cashierWithOpenRegister();
        $product = Product::factory()->create(['stock' => 2]);
        Sanctum::actingAs($cashier);

        $response = $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 20.00],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_does_not_create_cash_sale_when_amount_received_is_less_than_total(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 100.00]);
        Sanctum::actingAs($this->cashierWithOpenRegister());

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'payment_method' => 'cash',
            'amount_received' => 200,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Pago insuficiente. El dinero recibido (200.00) es menor que el total a pagar (230.00).');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_creates_cash_sale_when_amount_received_covers_total(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 100.00]);
        Sanctum::actingAs($this->cashierWithOpenRegister());

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'payment_method' => 'cash',
            'amount_received' => 230,
        ])->assertOk();

        $this->assertDatabaseCount('sales', 1);
    }

    public function test_returns_422_when_required_fields_are_missing(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['sales.create']));

        $this->postJson('/api/sales', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items', 'payment_method']);
    }

    public function test_returns_422_for_invalid_payment_method(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        Sanctum::actingAs($this->userWithPermissions(['sales.create']));

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10.00],
            ],
            'payment_method' => 'bitcoin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_forbids_creating_sale_without_permission(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10.00],
            ],
            'payment_method' => 'cash',
        ])->assertForbidden();

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => 1, 'quantity' => 1, 'unit_price' => 10.00],
            ],
            'payment_method' => 'cash',
        ])->assertUnauthorized();
    }

    public function test_lists_sales_for_user_with_view_permission(): void
    {
        Sale::factory()->count(3)->create();
        Sanctum::actingAs($this->userWithPermissions(['sales.view']));

        $this->getJson('/api/sales')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_forbids_listing_sales_without_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/sales')->assertForbidden();
    }

    public function test_ignores_client_supplied_prices_tax_and_discount(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 50.00]);
        Sanctum::actingAs($this->cashierWithOpenRegister());

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 0.01],
            ],
            'payment_method' => 'card',
            'discount' => 100.00,
            'tax' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('sales', [
            'subtotal' => 100.00,
            'discount' => 0,
            'tax' => 15.00,
            'total' => 115.00,
        ]);
        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'unit_price' => 50.00,
            'subtotal' => 100.00,
        ]);
    }

    public function test_applies_configured_tax_rate(): void
    {
        SystemSettings::updateMany(['tax_rate' => 10]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 33.33]);
        Sanctum::actingAs($this->cashierWithOpenRegister());

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'card',
        ])->assertOk();

        $this->assertDatabaseHas('sales', [
            'subtotal' => 33.33,
            'tax' => 3.33,
            'total' => 36.66,
        ]);
    }

    public function test_assigns_sale_to_own_open_register_ignoring_client_register(): void
    {
        $cashier = $this->userWithPermissions(['sales.create']);
        $ownRegister = CashRegister::factory()->openFor($cashier)->create();
        $otherRegister = CashRegister::factory()->openFor(User::factory()->create(), 0)->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'cash_register_id' => $otherRegister->id,
        ])->assertOk();

        $this->assertDatabaseHas('sales', ['user_id' => $cashier->id, 'cash_register_id' => $ownRegister->id]);
        $this->assertEquals(0, $otherRegister->fresh()->current_balance);
    }

    public function test_rejects_sale_when_cashier_has_no_open_register(): void
    {
        $cashier = $this->userWithPermissions(['sales.create']);
        CashRegister::factory()->create(['user_id' => $cashier->id, 'status' => 'closed']);
        CashRegister::factory()->openFor(User::factory()->create())->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'No tienes una caja abierta. Abre tu caja antes de registrar ventas.');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(10, $product->fresh()->stock);
    }
}
