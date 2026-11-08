<?php

namespace Tests\Feature\Api;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\ReturnRefund;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class ReturnRefundTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    private function saleItem(array $saleAttributes = []): SaleItem
    {
        $product = Product::factory()->create(['price' => 50.00, 'stock' => 10]);
        $sale = Sale::factory()->create([
            'subtotal' => 100.00,
            'tax' => 15.00,
            'discount' => 0,
            'total' => 115.00,
            ...$saleAttributes,
        ]);

        return SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50.00,
            'discount' => 0,
            'subtotal' => 100.00,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SaleItem $saleItem, array $overrides = []): array
    {
        return [
            'sale_id' => $saleItem->sale_id,
            'sale_item_id' => $saleItem->id,
            'product_id' => $saleItem->product_id,
            'quantity' => 1,
            'refund_amount' => 57.50,
            'reason' => 'defective',
            'refund_method' => 'cash',
            ...$overrides,
        ];
    }

    public function test_creates_return_for_amount_actually_paid(): void
    {
        $saleItem = $this->saleItem();
        Sanctum::actingAs($this->userWithPermissions(['returns.create']));

        $this->postJson('/api/returns', $this->payload($saleItem))->assertOk();

        $this->assertDatabaseHas('return_refunds', ['sale_item_id' => $saleItem->id, 'refund_amount' => 57.50]);
    }

    public function test_rejects_refund_greater_than_amount_paid(): void
    {
        $saleItem = $this->saleItem();
        Sanctum::actingAs($this->userWithPermissions(['returns.create']));

        $this->postJson('/api/returns', $this->payload($saleItem, ['refund_amount' => 5000]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El monto a reembolsar no puede superar lo pagado por el cliente (57.50)');

        $this->assertDatabaseCount('return_refunds', 0);
    }

    public function test_prorates_refund_limit_by_sale_discount(): void
    {
        $saleItem = $this->saleItem(['discount' => 20.00, 'total' => 95.00]);
        Sanctum::actingAs($this->userWithPermissions(['returns.create']));

        $this->postJson('/api/returns', $this->payload($saleItem, ['refund_amount' => 50.00]))->assertUnprocessable();
        $this->postJson('/api/returns', $this->payload($saleItem, ['refund_amount' => 47.50]))->assertOk();
    }

    public function test_cannot_return_the_same_units_twice(): void
    {
        $saleItem = $this->saleItem();
        Sanctum::actingAs($this->userWithPermissions(['returns.create']));

        $this->postJson('/api/returns', $this->payload($saleItem, ['quantity' => 2, 'refund_amount' => 115.00]))->assertOk();
        $this->postJson('/api/returns', $this->payload($saleItem))->assertUnprocessable();

        $this->assertDatabaseCount('return_refunds', 1);
    }

    public function test_allows_returning_units_of_a_rejected_return_again(): void
    {
        $saleItem = $this->saleItem();
        Sanctum::actingAs($this->userWithPermissions(['returns.create']));

        $this->postJson('/api/returns', $this->payload($saleItem, ['quantity' => 2, 'refund_amount' => 115.00]))->assertOk();
        ReturnRefund::query()->update(['status' => 'rejected']);

        $this->postJson('/api/returns', $this->payload($saleItem))->assertOk();
    }

    public function test_rejects_item_that_does_not_belong_to_the_sale(): void
    {
        $saleItem = $this->saleItem();
        $otherSale = Sale::factory()->create();
        Sanctum::actingAs($this->userWithPermissions(['returns.create']));

        $this->postJson('/api/returns', $this->payload($saleItem, ['sale_id' => $otherSale->id]))->assertUnprocessable();
        $this->postJson('/api/returns', $this->payload($saleItem, ['product_id' => Product::factory()->create()->id]))->assertUnprocessable();

        $this->assertDatabaseCount('return_refunds', 0);
    }

    public function test_rejects_return_for_cancelled_sale(): void
    {
        $saleItem = $this->saleItem(['status' => 'cancelled']);
        Sanctum::actingAs($this->userWithPermissions(['returns.create']));

        $this->postJson('/api/returns', $this->payload($saleItem))->assertUnprocessable();
    }

    private function approvedCashReturn(SaleItem $saleItem): ReturnRefund
    {
        return ReturnRefund::create([
            'return_number' => 'RET-000001',
            'sale_id' => $saleItem->sale_id,
            'sale_item_id' => $saleItem->id,
            'product_id' => $saleItem->product_id,
            'user_id' => $saleItem->sale->user_id,
            'quantity' => 1,
            'refund_amount' => 57.50,
            'reason' => 'defective',
            'status' => 'approved',
            'refund_method' => 'cash',
        ]);
    }

    private function openRegisterFor(User $user): CashRegister
    {
        return CashRegister::create([
            'name' => 'Caja 1',
            'user_id' => $user->id,
            'status' => 'open',
            'opening_balance' => 100.00,
            'current_balance' => 100.00,
            'expected_closing' => 100.00,
            'opened_at' => now(),
        ]);
    }

    public function test_processed_return_cannot_be_reopened_to_restock_twice(): void
    {
        $saleItem = $this->saleItem();
        $returnRefund = $this->approvedCashReturn($saleItem);
        $processor = $this->userWithPermissions(['returns.process', 'returns.edit']);
        $this->openRegisterFor($processor);
        Sanctum::actingAs($processor);

        $this->postJson("/api/returns/{$returnRefund->id}/process")->assertOk();
        $this->putJson("/api/returns/{$returnRefund->id}", ['status' => 'approved'])->assertUnprocessable();
        $this->postJson("/api/returns/{$returnRefund->id}/process")->assertUnprocessable();

        $this->assertSame(11, $saleItem->product->fresh()->stock);
    }

    public function test_cash_refund_is_withdrawn_from_the_processors_register(): void
    {
        $returnRefund = $this->approvedCashReturn($this->saleItem());
        $processor = $this->userWithPermissions(['returns.process']);
        $register = $this->openRegisterFor($processor);
        Sanctum::actingAs($processor);

        $this->postJson("/api/returns/{$returnRefund->id}/process")->assertOk();

        $register->refresh();
        $this->assertEquals(42.50, $register->current_balance);
        $this->assertEquals(42.50, $register->expected_closing);
        $this->assertDatabaseHas('cash_movements', [
            'cash_register_id' => $register->id,
            'type' => 'withdrawal',
            'amount' => 57.50,
        ]);
    }

    public function test_cash_refund_requires_an_open_register(): void
    {
        $saleItem = $this->saleItem();
        $returnRefund = $this->approvedCashReturn($saleItem);
        Sanctum::actingAs($this->userWithPermissions(['returns.process']));

        $this->postJson("/api/returns/{$returnRefund->id}/process")->assertUnprocessable();

        $this->assertSame('approved', $returnRefund->fresh()->status);
        $this->assertSame(10, $saleItem->product->fresh()->stock);
    }
}
