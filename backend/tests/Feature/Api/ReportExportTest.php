<?php

namespace Tests\Feature\Api;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    public function test_exports_sales_csv(): void
    {
        $manager = $this->userWithPermissions(['reports.view']);
        Sale::factory()->create(['invoice_number' => 'INV-000001', 'user_id' => $manager->id]);
        Sanctum::actingAs($manager);

        $response = $this->get('/api/reports/sales/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Factura', $response->streamedContent());
        $this->assertStringContainsString('INV-000001', $response->streamedContent());
    }

    public function test_exports_inventory_movements_csv_with_user(): void
    {
        $manager = $this->userWithPermissions(['reports.view']);
        $product = Product::factory()->create(['name' => 'Producto A']);
        InventoryMovement::create([
            'product_id' => $product->id,
            'user_id' => $manager->id,
            'type' => 'entry',
            'quantity' => 5,
            'previous_stock' => 0,
            'new_stock' => 5,
            'reference' => 'Compra #1',
        ]);
        Sanctum::actingAs($manager);

        $response = $this->get('/api/reports/inventory-movements/export');

        $response->assertOk();
        $this->assertStringContainsString('Producto A', $response->streamedContent());
        $this->assertStringContainsString($manager->name, $response->streamedContent());
    }

    public function test_exports_inventory_stock_csv(): void
    {
        $manager = $this->userWithPermissions(['reports.view']);
        Product::factory()->create(['name' => 'Producto A', 'stock' => 7]);
        Sanctum::actingAs($manager);

        $response = $this->get('/api/reports/inventory-stock/export');

        $response->assertOk();
        $this->assertStringContainsString('Producto A', $response->streamedContent());
    }

    public function test_exports_sales_xlsx(): void
    {
        $manager = $this->userWithPermissions(['reports.view']);
        Sale::factory()->create(['invoice_number' => 'INV-000001', 'user_id' => $manager->id]);
        Sanctum::actingAs($manager);

        $response = $this->get('/api/reports/sales/export?format=xlsx');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $response->assertHeader('content-disposition', 'attachment; filename=ventas.xlsx');
    }

    public function test_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->get('/api/reports/sales/export')->assertForbidden();
        $this->get('/api/reports/inventory-movements/export')->assertForbidden();
        $this->get('/api/reports/inventory-stock/export')->assertForbidden();
    }
}
