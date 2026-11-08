<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ReturnRefund;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class SalesOverviewTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    private function userWithRole(string $roleSlug, array $permissionSlugs): User
    {
        $role = Role::factory()->create(['slug' => $roleSlug]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::factory()->create(['slug' => $slug, 'name' => $slug]);
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }

    public function test_sales_overview_returns_daily_series_summary_and_top_product(): void
    {
        $user = $this->userWithPermissions(['sales.view']);
        $category = Category::factory()->create(['name' => 'Bebidas']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $sale = Sale::factory()->create(['total' => 100, 'status' => 'completed', 'created_at' => now()]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 20,
            'discount' => 0,
            'subtotal' => 100,
        ]);
        Sale::factory()->create(['total' => 200, 'status' => 'completed', 'created_at' => now()->subDays(2)]);
        Sale::factory()->create(['total' => 999, 'status' => 'cancelled', 'created_at' => now()]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cashier-reports/sales-overview?date_from='.now()->subDays(2)->toDateString().'&date_to='.now()->toDateString());

        $response->assertOk()
            ->assertJsonCount(3, 'daily')
            ->assertJsonPath('summary.total', 300)
            ->assertJsonPath('summary.transactions', 2)
            ->assertJsonPath('top_product.name', $product->name)
            ->assertJsonPath('by_category.0.name', 'Bebidas');
        $this->assertEquals(100, $response->json('by_category.0.percentage'));
    }

    public function test_sales_overview_nets_out_processed_returns_in_categories(): void
    {
        $user = $this->userWithPermissions(['sales.view']);
        $category = Category::factory()->create(['name' => 'Snacks']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $sale = Sale::factory()->create(['total' => 50, 'status' => 'completed', 'created_at' => now()]);
        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 5,
            'discount' => 0,
            'subtotal' => 50,
        ]);
        ReturnRefund::create([
            'return_number' => 'RET-000001',
            'sale_id' => $sale->id,
            'sale_item_id' => $item->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'quantity' => 2,
            'refund_amount' => 10,
            'status' => 'processed',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cashier-reports/sales-overview?date_from='.now()->toDateString().'&date_to='.now()->toDateString());

        $response->assertOk();
        $this->assertEquals(40, $response->json('by_category.0.total'));
    }

    public function test_sales_overview_scopes_to_cashier_sales(): void
    {
        $cashier = $this->userWithRole('cashier', ['sales.view']);
        $other = User::factory()->create();

        Sale::factory()->create(['user_id' => $cashier->id, 'total' => 100, 'status' => 'completed', 'created_at' => now()]);
        Sale::factory()->create(['user_id' => $other->id, 'total' => 900, 'status' => 'completed', 'created_at' => now()]);

        Sanctum::actingAs($cashier);

        $this->getJson('/api/cashier-reports/sales-overview?date_from='.now()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('summary.total', 100);
    }

    public function test_sales_overview_requires_sales_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/cashier-reports/sales-overview')->assertForbidden();
    }
}
