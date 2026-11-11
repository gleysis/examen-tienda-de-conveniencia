<?php

namespace Tests\Feature\Api;

use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    /**
     * A user holding the real cashier role, so own-record scoping applies.
     */
    private function cashier(array $permissionSlugs = ['sales.create']): User
    {
        return $this->userWithRoleSlug('cashier', $permissionSlugs);
    }

    private function userWithRoleSlug(string $slug, array $permissionSlugs): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $slug]);
        $role->permissions()->syncWithoutDetaching(
            collect($permissionSlugs)->map(
                fn (string $permissionSlug) => Permission::firstOrCreate(['slug' => $permissionSlug], ['name' => $permissionSlug])->id
            )
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }

    public function test_creates_register_closed_without_float(): void
    {
        $manager = $this->userWithPermissions(['cash-registers.create']);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/cash-registers', [
            'name' => 'Caja 1',
            'location' => 'Planta baja',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('cash_registers', [
            'name' => 'Caja 1',
            'status' => 'closed',
            'user_id' => null,
        ]);
        $this->assertDatabaseCount('cash_register_sessions', 0);
    }

    public function test_register_is_created_automatically_for_new_cashiers(): void
    {
        $cashierRole = $this->cashier()->roles->first();
        $admin = $this->userWithPermissions(['users.create', 'sales.create']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'name' => 'Ana Torres',
            'email' => 'ana@example.com',
            'password' => 'password1',
            'roles' => [$cashierRole->id],
        ]);

        $response->assertOk();
        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertDatabaseHas('cash_registers', [
            'user_id' => $user->id,
            'status' => 'closed',
        ]);
    }

    public function test_manager_can_open_a_register_for_a_cashier(): void
    {
        $cashier = $this->cashier();
        $manager = $this->userWithPermissions(['cash-registers.open']);
        $register = CashRegister::factory()->create(['user_id' => $cashier->id]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/cash-registers/{$register->id}/open", [
            'opening_balance' => 200.00,
            'user_id' => $cashier->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('session.user_id', $cashier->id)
            ->assertJsonPath('session.opened_by', $manager->id);

        $this->assertDatabaseHas('cash_register_sessions', [
            'cash_register_id' => $register->id,
            'user_id' => $cashier->id,
            'opened_by' => $manager->id,
            'status' => 'open',
        ]);
    }

    public function test_cashier_sees_the_session_a_manager_opened_for_them(): void
    {
        $cashier = $this->cashier(['sales.create', 'cash-registers.view']);
        $manager = $this->userWithPermissions(['cash-registers.open']);
        $register = CashRegister::factory()->create(['user_id' => $cashier->id]);

        Sanctum::actingAs($manager);
        $this->postJson("/api/cash-registers/{$register->id}/open", [
            'opening_balance' => 150.00,
            'user_id' => $cashier->id,
        ])->assertOk();

        Sanctum::actingAs($cashier);
        $this->getJson('/api/cash-registers/current')
            ->assertOk()
            ->assertJsonPath('cash_register.id', $register->id)
            ->assertJsonPath('cash_register.current_session.user_id', $cashier->id)
            ->assertJsonPath('cash_register.current_session.opening_balance', 150);
    }

    public function test_cannot_open_two_sessions_on_same_register_or_for_same_cashier(): void
    {
        $cashier = $this->cashier();
        $manager = $this->userWithPermissions(['cash-registers.open']);
        $register = CashRegister::factory()->openFor($cashier)->create();
        $otherRegister = CashRegister::factory()->create();
        Sanctum::actingAs($manager);

        $this->postJson("/api/cash-registers/{$register->id}/open", [
            'opening_balance' => 0,
        ])->assertUnprocessable();

        $this->postJson("/api/cash-registers/{$otherRegister->id}/open", [
            'opening_balance' => 0,
            'user_id' => $cashier->id,
        ])->assertUnprocessable();
    }

    public function test_cashier_cannot_open_someone_elses_register(): void
    {
        $cashier = $this->cashier(['sales.create', 'cash-registers.open']);
        $register = CashRegister::factory()->create(['user_id' => User::factory()->create()->id]);
        Sanctum::actingAs($cashier);

        $this->postJson("/api/cash-registers/{$register->id}/open", [
            'opening_balance' => 0,
        ])->assertForbidden();
    }

    public function test_cash_sale_updates_open_session_automatically(): void
    {
        $cashier = $this->userWithPermissions(['sales.create']);
        $register = CashRegister::factory()->openFor($cashier)->create();
        $session = $register->openSession;
        SystemSettings::updateMany(['tax_rate' => 0]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 25.00]);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'payment_method' => 'cash',
        ])->assertOk();

        $session->refresh();
        $this->assertSame(50.0, $session->cash_sales);
        $this->assertSame(50.0, $session->total_sales);
        $this->assertSame(150.0, $session->expected_closing);
        $this->assertDatabaseHas('sales', [
            'cash_register_id' => $register->id,
            'cash_register_session_id' => $session->id,
        ]);
    }

    public function test_card_sale_does_not_add_to_drawer_balance(): void
    {
        $cashier = $this->userWithPermissions(['sales.create']);
        $register = CashRegister::factory()->openFor($cashier)->create();
        $session = $register->openSession;
        SystemSettings::updateMany(['tax_rate' => 0]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 80.00]);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'card',
        ])->assertOk();

        $session->refresh();
        $this->assertSame(80.0, $session->card_sales);
        $this->assertSame(100.0, $session->expected_closing);
    }

    public function test_closing_register_records_the_cash_count(): void
    {
        $manager = $this->userWithPermissions(['cash-registers.close']);
        $register = CashRegister::factory()->openFor($manager)->create();
        $session = $register->openSession;
        $session->update(['cash_sales' => 50.00, 'total_sales' => 50.00, 'expected_closing' => 150.00]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/cash-registers/{$register->id}/close", [
            'actual_closing' => 145.00,
        ]);

        $response->assertOk()
            ->assertJsonPath('session.cash_count.counted_amount', 145)
            ->assertJsonPath('session.cash_count.difference', -5);

        $this->assertSame('closed', $register->fresh()->status);
        $this->assertSame('closed', $session->fresh()->status);
        $this->assertSame($manager->id, $session->fresh()->closed_by);
        $this->assertDatabaseHas('cash_counts', [
            'cash_register_session_id' => $session->id,
            'expected_amount' => 150.00,
            'counted_amount' => 145.00,
            'difference' => -5.00,
        ]);
    }

    public function test_reopening_creates_a_new_session_and_keeps_history(): void
    {
        $cashier = $this->cashier(['sales.create', 'cash-registers.open', 'cash-registers.close']);
        $register = CashRegister::factory()->create(['user_id' => $cashier->id]);
        Sanctum::actingAs($cashier);

        $this->postJson("/api/cash-registers/{$register->id}/open", ['opening_balance' => 100])->assertOk();
        $this->postJson("/api/cash-registers/{$register->id}/close", ['actual_closing' => 100])->assertOk();
        $this->postJson("/api/cash-registers/{$register->id}/open", ['opening_balance' => 50])->assertOk();

        $this->assertSame(2, $register->sessions()->count());
        $this->assertSame(1, $register->sessions()->closed()->count());
        $this->assertSame(1, $register->openSession()->count());
    }

    public function test_closing_a_session_twice_records_a_single_cash_count(): void
    {
        $manager = $this->userWithPermissions(['cash-registers.close']);
        $register = CashRegister::factory()->openFor($manager)->create();
        $session = $register->openSession;

        $session->close(100, $manager->id);

        try {
            $session->close(90, $manager->id);
            $this->fail('A closed session must not be closed again.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('cash_counts', 1);
    }

    public function test_manager_opening_an_unassigned_register_assigns_it_to_the_cashier(): void
    {
        $cashier = $this->cashier();
        $manager = $this->userWithPermissions(['cash-registers.open']);
        $register = CashRegister::factory()->create();
        Sanctum::actingAs($manager);

        $this->postJson("/api/cash-registers/{$register->id}/open", [
            'opening_balance' => 100,
            'user_id' => $cashier->id,
        ])->assertOk();

        $this->assertSame($cashier->id, $register->fresh()->user_id);
    }

    public function test_responsible_users_lists_only_cashiers_with_session_state(): void
    {
        $busyCashier = $this->cashier();
        $freeCashier = $this->cashier();
        CashRegister::factory()->openFor($busyCashier)->create();
        Sanctum::actingAs($this->userWithPermissions(['cash-registers.open', 'sales.create']));

        $users = collect($this->getJson('/api/cash-registers/responsible-users')->assertOk()->json('users'))
            ->pluck('has_open_session', 'id');

        $this->assertEqualsCanonicalizing([$busyCashier->id, $freeCashier->id], $users->keys()->all());
        $this->assertTrue($users[$busyCashier->id]);
        $this->assertFalse($users[$freeCashier->id]);
    }

    public function test_register_can_only_be_reassigned_to_a_cashier(): void
    {
        $register = CashRegister::factory()->create();
        Sanctum::actingAs($this->userWithPermissions(['cash-registers.edit']));

        $this->putJson("/api/cash-registers/{$register->id}", ['user_id' => User::factory()->create()->id])
            ->assertUnprocessable();

        $cashier = $this->cashier();
        $this->putJson("/api/cash-registers/{$register->id}", ['user_id' => $cashier->id])->assertOk();

        $this->assertSame($cashier->id, $register->fresh()->user_id);
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

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(0, $register->openSession()->count());
    }

    public function test_cannot_close_an_already_closed_register(): void
    {
        $manager = $this->userWithPermissions(['cash-registers.close']);
        $register = CashRegister::factory()->create();
        Sanctum::actingAs($manager);

        $this->postJson("/api/cash-registers/{$register->id}/close", [
            'actual_closing' => 100.00,
        ])->assertStatus(422);
    }

    public function test_movements_update_expected_closing(): void
    {
        $cashier = $this->cashier(['sales.create', 'cash-registers.open']);
        CashRegister::factory()->openFor($cashier)->create();
        $session = CashRegisterSession::where('user_id', $cashier->id)->firstOrFail();
        Sanctum::actingAs($cashier);

        $this->postJson('/api/cash-movements', [
            'type' => 'withdrawal',
            'amount' => 30,
            'reason' => 'Retiro parcial',
        ])->assertCreated();

        $this->assertSame(70.0, $session->fresh()->expected_closing);
        $this->assertDatabaseHas('cash_movements', [
            'cash_register_session_id' => $session->id,
            'type' => 'withdrawal',
            'amount' => 30,
        ]);
    }

    public function test_withdrawal_cannot_exceed_expected_cash(): void
    {
        $cashier = $this->cashier(['sales.create', 'cash-registers.open']);
        CashRegister::factory()->openFor($cashier, 50)->create();
        Sanctum::actingAs($cashier);

        $this->postJson('/api/cash-movements', [
            'type' => 'withdrawal',
            'amount' => 80,
            'reason' => 'Retiro mayor al disponible',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_returns_assigned_register_for_user(): void
    {
        $cashier = $this->userWithPermissions(['cash-registers.view']);
        CashRegister::create([
            'name' => 'Caja 1',
            'user_id' => $cashier->id,
            'status' => 'closed',
        ]);
        Sanctum::actingAs($cashier);

        $response = $this->getJson('/api/cash-registers/current');

        $response->assertOk()->assertJsonPath('cash_register.name', 'Caja 1');
    }

    public function test_cashier_only_lists_own_registers_and_sessions(): void
    {
        $cashier = $this->cashier(['sales.create', 'cash-registers.view']);
        $ownRegister = CashRegister::factory()->create(['user_id' => $cashier->id]);
        CashRegister::factory()->create(['user_id' => User::factory()->create()->id]);
        CashRegisterSession::factory()->openFor($cashier)->create(['cash_register_id' => $ownRegister->id]);
        CashRegisterSession::factory()->openFor(User::factory()->create())->create();
        Sanctum::actingAs($cashier);

        $this->getJson('/api/cash-registers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownRegister->id);

        $this->getJson('/api/cash-registers/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $cashier->id);
    }

    public function test_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/cash-registers')->assertForbidden();
        $this->postJson('/api/cash-registers', [
            'name' => 'Caja 1',
        ])->assertForbidden();
    }
}
