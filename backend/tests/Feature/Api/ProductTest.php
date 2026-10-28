<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    public function test_creates_product_with_valid_payload(): void
    {
        $category = Category::factory()->create();
        Sanctum::actingAs($this->userWithPermissions(['products.create']));

        $response = $this->postJson('/api/products', [
            'name' => 'Coca Cola 600ml',
            'sku' => 'CC-600',
            'price' => 15.50,
            'stock' => 24,
            'min_stock' => 6,
            'category_id' => $category->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('products', [
            'name' => 'Coca Cola 600ml',
            'sku' => 'CC-600',
            'stock' => 24,
            'category_id' => $category->id,
        ]);
    }

    public function test_returns_422_when_required_fields_are_missing(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['products.create']));

        $this->postJson('/api/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'sku', 'price', 'stock', 'min_stock', 'category_id']);
    }

    public function test_forbids_deleting_product_without_permission(): void
    {
        $product = Product::factory()->create();
        Sanctum::actingAs($this->userWithPermissions(['products.view', 'products.edit']));

        $this->deleteJson("/api/products/{$product->id}")->assertForbidden();

        $this->assertModelExists($product);
    }

    public function test_deletes_product_with_permission(): void
    {
        $product = Product::factory()->create();
        Sanctum::actingAs($this->userWithPermissions(['products.delete']));

        $this->deleteJson("/api/products/{$product->id}")->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_index_filters_products_by_search(): void
    {
        Product::factory()->create(['name' => 'Pepsi Lata']);
        Product::factory()->create(['name' => 'Sabritas']);
        Sanctum::actingAs($this->userWithPermissions(['products.view']));

        $this->getJson('/api/products?search=Pepsi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pepsi Lata');
    }

    public function test_index_excludes_inactive_products(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);
        Sanctum::actingAs($this->userWithPermissions(['products.view']));

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
