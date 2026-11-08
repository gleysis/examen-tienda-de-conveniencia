<?php

namespace Tests\Feature\Api;

use App\Models\CashRegister;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ReturnRefund;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashierIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const CASHIER_PERMISSIONS = [
        'sales.view', 'sales.create', 'returns.view', 'returns.create',
        'cash-registers.view', 'cash-registers.open', 'cash-registers.close',
    ];

    private function userWithRole(string $roleSlug, array $permissionSlugs): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => $roleSlug]);
        $role->permissions()->syncWithoutDetaching(
            collect($permissionSlugs)->map(fn (string $slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id)
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }

    private function cashier(): User
    {
        return $this->userWithRole('cashier', self::CASHIER_PERMISSIONS);
    }

    private function openRegisterFor(User $user, float $balance = 100.00): CashRegister
    {
        return CashRegister::create([
            'name' => 'Caja de '.$user->name,
            'user_id' => $user->id,
            'status' => 'open',
            'opening_balance' => $balance,
            'current_balance' => $balance,
            'expected_closing' => $balance,
            'opened_at' => now(),
        ]);
    }

    public function test_cashier_cannot_view_another_cashiers_sale_or_return(): void
    {
        $sale = Sale::factory()->create();
        $returnRefund = ReturnRefund::create([
            'return_number' => 'RET-000001',
            'sale_id' => $sale->id,
            'sale_item_id' => SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => Product::factory()->create()->id,
                'quantity' => 1,
                'unit_price' => 10,
                'subtotal' => 10,
            ])->id,
            'product_id' => Product::first()->id,
            'user_id' => $sale->user_id,
            'quantity' => 1,
            'refund_amount' => 10,
            'reason' => 'defective',
            'status' => 'pending',
            'refund_method' => 'cash',
        ]);
        Sanctum::actingAs($this->cashier());

        $this->getJson("/api/sales/{$sale->id}")->assertNotFound();
        $this->getJson("/api/returns/{$returnRefund->id}")->assertNotFound();
    }

    public function test_cashier_can_view_own_sale_and_manager_can_view_any(): void
    {
        $cashier = $this->cashier();
        $ownSale = Sale::factory()->create(['user_id' => $cashier->id]);

        Sanctum::actingAs($cashier);
        $this->getJson("/api/sales/{$ownSale->id}")->assertOk();

        Sanctum::actingAs($this->userWithRole('manager', ['sales.view']));
        $this->getJson("/api/sales/{$ownSale->id}")->assertOk();
    }

    public function test_cashier_cannot_withdraw_cash_from_another_register(): void
    {
        $otherRegister = $this->openRegisterFor(User::factory()->create());
        Sanctum::actingAs($this->cashier());

        $this->postJson('/api/cash-movements', [
            'type' => 'withdrawal',
            'amount' => 50,
            'reason' => 'Retiro',
            'cash_register_id' => $otherRegister->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('cash_movements', 0);
        $this->assertEquals(100, $otherRegister->fresh()->current_balance);
    }

    public function test_cashier_cannot_close_or_view_another_register(): void
    {
        $otherRegister = $this->openRegisterFor(User::factory()->create());
        Sanctum::actingAs($this->cashier());

        $this->postJson("/api/cash-registers/{$otherRegister->id}/close", ['actual_closing' => 0])->assertForbidden();
        $this->getJson("/api/cash-registers/{$otherRegister->id}")->assertNotFound();

        $this->assertSame('open', $otherRegister->fresh()->status);
    }

    public function test_manager_can_close_a_cashiers_register(): void
    {
        $register = $this->openRegisterFor($this->cashier());
        Sanctum::actingAs($this->userWithRole('manager', ['cash-registers.close']));

        $this->postJson("/api/cash-registers/{$register->id}/close", ['actual_closing' => 100])->assertOk();
    }

    public function test_cannot_open_a_second_register(): void
    {
        $cashier = $this->cashier();
        $this->openRegisterFor($cashier);
        $closedRegister = CashRegister::create(['name' => 'Caja 2', 'status' => 'closed']);
        Sanctum::actingAs($cashier);

        $this->postJson("/api/cash-registers/{$closedRegister->id}/open", ['opening_balance' => 0])->assertUnprocessable();

        $this->assertSame('closed', $closedRegister->fresh()->status);
    }

    public function test_cancelling_sale_restores_stock_and_register_totals(): void
    {
        SystemSettings::updateMany(['tax_rate' => 0]);
        $cashier = $this->cashier();
        $register = $this->openRegisterFor($cashier);
        $product = Product::factory()->create(['stock' => 10, 'price' => 25.00]);
        Sanctum::actingAs($cashier);

        $saleId = $this->postJson('/api/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
        ])->assertOk()->json('sale.id');

        Sanctum::actingAs($this->userWithRole('manager', ['sales.edit']));
        $this->putJson("/api/sales/{$saleId}", ['status' => 'cancelled'])->assertOk();
        $this->putJson("/api/sales/{$saleId}", ['status' => 'cancelled'])->assertOk();

        $register->refresh();
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertEquals(100, $register->current_balance);
        $this->assertEquals(0, $register->cash_sales);
        $this->assertEquals(100, $register->expected_closing);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'cancelled']);
    }

    public function test_sale_cannot_be_reopened_or_change_payment_method(): void
    {
        $sale = Sale::factory()->create(['status' => 'cancelled', 'payment_method' => 'cash']);
        Sanctum::actingAs($this->userWithRole('manager', ['sales.edit']));

        $this->putJson("/api/sales/{$sale->id}", ['status' => 'completed'])->assertUnprocessable();
        $this->putJson("/api/sales/{$sale->id}", ['payment_method' => 'card'])->assertOk();

        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'status' => 'cancelled', 'payment_method' => 'cash']);
    }
}
