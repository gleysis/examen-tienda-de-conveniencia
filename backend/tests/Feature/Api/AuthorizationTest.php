<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    public static function protectedEndpoints(): array
    {
        return [
            'list products' => ['GET', '/api/products'],
            'create product' => ['POST', '/api/products'],
            'list categories' => ['GET', '/api/categories'],
            'list sales' => ['GET', '/api/sales'],
            'list users' => ['GET', '/api/users'],
            'list inventory' => ['GET', '/api/inventory'],
            'low stock report' => ['GET', '/api/inventory/low-stock'],
            'inventory report' => ['GET', '/api/inventory/report'],
            'list suppliers' => ['GET', '/api/suppliers'],
            'list returns' => ['GET', '/api/returns'],
            'list discounts' => ['GET', '/api/discounts'],
            'list cash registers' => ['GET', '/api/cash-registers'],
            'current cash register' => ['GET', '/api/cash-registers/current'],
            'list settings' => ['GET', '/api/settings'],
            'dashboard' => ['GET', '/api/dashboard'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_returns_401_for_unauthenticated_requests(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('protectedEndpoints')]
    public function test_returns_403_for_user_without_permissions(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->json($method, $uri)->assertForbidden();
    }

    public function test_allows_access_when_user_has_matching_permission(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['products.view']));

        $this->getJson('/api/products')->assertOk();
    }

    public function test_user_with_one_permission_cannot_access_other_resources(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['products.view']));

        $this->getJson('/api/sales')->assertForbidden();
    }

    public function test_public_settings_endpoint_is_reachable_without_settings_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/settings/public')->assertOk();
    }
}
