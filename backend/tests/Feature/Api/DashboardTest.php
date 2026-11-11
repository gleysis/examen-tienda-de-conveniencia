<?php

namespace Tests\Feature\Api;

use App\Models\CashRegisterSession;
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

class DashboardTest extends TestCase
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

    public function test_index_returns_dashboard_cards(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        Sale::factory()->create(['total' => 100, 'status' => 'completed', 'created_at' => now()]);
        Sale::factory()->create(['total' => 250, 'status' => 'completed', 'created_at' => now()]);
        Sale::factory()->create(['total' => 500, 'status' => 'cancelled', 'created_at' => now()]);
        Product::factory()->create(['stock' => 2, 'min_stock' => 5]);
        CashRegisterSession::factory()->create(['status' => 'open', 'opened_at' => now()]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('scope', 'all')
            ->assertJsonPath('cards.sales_today', 350)
            ->assertJsonPath('cards.invoices_today', 2)
            ->assertJsonPath('cards.low_stock_count', 1)
            ->assertJsonPath('cards.open_registers', 1);
    }

    public function test_cashier_sees_only_own_sales(): void
    {
        $cashier = $this->userWithRole('cashier', ['reports.view']);
        $other = User::factory()->create();

        Sale::factory()->create(['user_id' => $cashier->id, 'total' => 100, 'created_at' => now()]);
        Sale::factory()->create(['user_id' => $other->id, 'total' => 900, 'created_at' => now()]);

        Sanctum::actingAs($cashier);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('scope', 'own')
            ->assertJsonPath('cards.sales_today', 100);
    }

    public function test_sales_chart_returns_filled_series_and_comparison(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        Sale::factory()->create(['total' => 80, 'created_at' => now()->subDays(2)]);
        Sale::factory()->create(['total' => 120, 'created_at' => now()]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/sales-chart?compare=1&date_from='.now()->subDays(2)->toDateString().'&date_to='.now()->toDateString());

        $response->assertOk()
            ->assertJsonPath('total', 200)
            ->assertJsonPath('invoices', 2)
            ->assertJsonCount(3, 'series')
            ->assertJsonStructure(['previous' => ['from', 'to', 'series', 'total', 'invoices']]);
    }

    public function test_sales_by_payment_method_returns_percentages(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        Sale::factory()->create(['payment_method' => 'cash', 'total' => 75, 'created_at' => now()]);
        Sale::factory()->create(['payment_method' => 'card', 'total' => 25, 'created_at' => now()]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/sales-by-payment?date_from='.now()->toDateString().'&date_to='.now()->toDateString());

        $response->assertOk()
            ->assertJsonPath('total_revenue', 100)
            ->assertJsonPath('sales_by_payment_method.0.payment_method', 'cash')
            ->assertJsonPath('sales_by_payment_method.0.percentage', 75);
    }

    public function test_top_products_nets_out_processed_returns(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $product = Product::factory()->create();
        $sale = Sale::factory()->create(['status' => 'completed', 'created_at' => now()]);
        $saleItem = SaleItem::create([
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
            'sale_item_id' => $saleItem->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'quantity' => 4,
            'refund_amount' => 20,
            'status' => 'processed',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/top-products?date_from='.now()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('top_products.0.total_quantity', 6)
            ->assertJsonPath('top_products.0.total_revenue', 30);
    }

    public function test_inventory_status_categorizes_products(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        Product::factory()->create(['stock' => 10, 'min_stock' => 5]);
        Product::factory()->create(['stock' => 2, 'min_stock' => 5]);
        Product::factory()->create(['stock' => 0, 'min_stock' => 5]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/inventory-status')
            ->assertOk()
            ->assertJsonPath('summary.normal', 1)
            ->assertJsonPath('summary.low_stock', 1)
            ->assertJsonPath('summary.out_of_stock', 1);
    }

    public function test_sales_by_cashier_groups_totals(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        $cashierA = User::factory()->create();
        $cashierB = User::factory()->create();
        Sale::factory()->create(['user_id' => $cashierA->id, 'total' => 300, 'created_at' => now()]);
        Sale::factory()->count(2)->create(['user_id' => $cashierB->id, 'total' => 50, 'created_at' => now()]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/sales-by-cashier?date_from='.now()->toDateString().'&date_to='.now()->toDateString());

        $response->assertOk()->assertJsonPath('scoped', false);
        $this->assertEquals($cashierA->id, $response->json('cashiers.0.user_id'));
        $this->assertEquals(300, $response->json('cashiers.0.total'));
        $this->assertEquals(2, $response->json('cashiers.1.invoices'));
    }

    public function test_sales_by_hour_returns_24_buckets(): void
    {
        $user = $this->userWithPermissions(['reports.view']);
        Sale::factory()->create(['total' => 40, 'created_at' => now()->setTime(14, 30)]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/sales-by-hour?date_from='.now()->toDateString().'&date_to='.now()->toDateString());

        $response->assertOk()->assertJsonCount(24, 'hours');
        $this->assertEquals(40, $response->json('hours.14.total'));
        $this->assertEquals(1, $response->json('hours.14.invoices'));
    }

    public function test_alerts_hides_discrepancies_from_scoped_cashier(): void
    {
        $cashier = $this->userWithRole('cashier', ['reports.view', 'cash-registers.view']);
        $session = CashRegisterSession::factory()->create(['status' => 'closed', 'closed_at' => now()]);
        $session->cashCount()->create([
            'expected_amount' => 150,
            'counted_amount' => 100,
            'difference' => -50,
        ]);
        $session->cashRegister()->update(['status' => 'closed']);
        Product::factory()->create(['stock' => 0]);
        Product::factory()->create(['stock' => 3, 'expiry_date' => now()->addDays(10)]);

        Sanctum::actingAs($cashier);

        $this->getJson('/api/dashboard/alerts')
            ->assertOk()
            ->assertJsonPath('can_view_discrepancies', false)
            ->assertJsonCount(0, 'discrepancies')
            ->assertJsonCount(1, 'out_of_stock')
            ->assertJsonCount(1, 'expiring_soon');
    }

    public function test_alerts_shows_discrepancies_to_admin(): void
    {
        $admin = $this->userWithPermissions(['reports.view', 'cash-registers.view']);
        $session = CashRegisterSession::factory()->create(['status' => 'closed', 'closed_at' => now()]);
        $session->cashCount()->create([
            'expected_amount' => 150,
            'counted_amount' => 100,
            'difference' => -50,
        ]);
        $session->cashRegister()->update(['status' => 'closed']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/dashboard/alerts')
            ->assertOk()
            ->assertJsonPath('can_view_discrepancies', true)
            ->assertJsonCount(1, 'discrepancies');
    }

    public function test_forbids_dashboard_without_reports_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
