<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    public function test_creates_sale_and_decrements_stock(): void
    {
        $cashier = $this->userWithPermissions(['sales.create']);
        $product = Product::factory()->create(['stock' => 10]);
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
            'total' => 60.00,
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
        $cashier = $this->userWithPermissions(['sales.create']);
        $product = Product::factory()->create(['stock' => 2]);
        Sanctum::actingAs($cashier);

        $response = $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 20.00],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertOk()->assertJsonStructure(['error']);

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(2, $product->fresh()->stock);
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
            ->assertJsonCount(3, 'sales');
    }

    public function test_forbids_listing_sales_without_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/sales')->assertForbidden();
    }

    public function test_applies_discount_and_tax_to_total(): void
    {
        $cashier = $this->userWithPermissions(['sales.create']);
        $product = Product::factory()->create(['stock' => 10]);
        Sanctum::actingAs($cashier);

        $response = $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 50.00],
            ],
            'payment_method' => 'card',
            'discount' => 10.00,
            'tax' => 9.00,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('sales', [
            'subtotal' => 100.00,
            'discount' => 10.00,
            'tax' => 9.00,
            'total' => 99.00,
        ]);
    }
}
