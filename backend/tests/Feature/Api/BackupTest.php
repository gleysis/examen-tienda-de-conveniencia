<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    public function test_creates_and_lists_backup(): void
    {
        $admin = $this->userWithPermissions(['backups.manage']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/backups');

        $response->assertOk();
        $filename = $response->json('backup.filename');
        $this->assertNotNull($filename);
        Storage::disk('local')->assertExists('backups/'.$filename);

        $this->getJson('/api/backups')->assertOk()->assertJsonPath('backups.0.filename', $filename);
    }

    public function test_downloads_backup_file(): void
    {
        $admin = $this->userWithPermissions(['backups.manage']);
        Sanctum::actingAs($admin);

        $filename = $this->postJson('/api/backups')->json('backup.filename');

        $this->get("/api/backups/{$filename}")->assertOk();
    }

    public function test_restores_database_from_sql_file(): void
    {
        $admin = $this->userWithPermissions(['backups.manage']);
        Sanctum::actingAs($admin);

        $product = Product::factory()->create(['name' => 'Producto Respaldo']);
        $filename = $this->postJson('/api/backups')->json('backup.filename');

        $product->delete();
        $this->assertDatabaseMissing('products', ['name' => 'Producto Respaldo']);

        $sql = Storage::disk('local')->get('backups/'.$filename);
        $file = UploadedFile::fake()->createWithContent('respaldo.sql', $sql);

        $this->post('/api/backups/restore', ['backup' => $file])->assertOk();

        $this->assertDatabaseHas('products', ['name' => 'Producto Respaldo']);
    }

    public function test_rejects_non_sql_restore_file(): void
    {
        $admin = $this->userWithPermissions(['backups.manage']);
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->createWithContent('respaldo.txt', 'select 1');

        $this->post('/api/backups/restore', ['backup' => $file])->assertStatus(422);
    }

    public function test_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/backups')->assertForbidden();
        $this->postJson('/api/backups')->assertForbidden();
    }
}
