<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use CreatesUsersWithPermissions, RefreshDatabase;

    public function test_backup_can_be_created_listed_downloaded_and_deleted(): void
    {
        Storage::fake('local');
        $admin = $this->userWithPermissions(['backups.manage']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/backups');

        $response->assertOk();
        $filename = $response->json('backup.filename');
        $this->assertNotEmpty($filename);
        Storage::disk('local')->assertExists('backups/'.$filename);

        $this->getJson('/api/backups')
            ->assertOk()
            ->assertJsonPath('backups.0.filename', $filename);

        $this->get("/api/backups/{$filename}")->assertOk();

        $this->deleteJson("/api/backups/{$filename}")->assertOk();
        Storage::disk('local')->assertMissing('backups/'.$filename);
    }

    public function test_stored_backup_restores_database_contents(): void
    {
        Storage::fake('local');
        $admin = $this->userWithPermissions(['backups.manage']);
        Sanctum::actingAs($admin);

        Product::factory()->create(['name' => 'Producto Respaldo']);

        $filename = $this->postJson('/api/backups')->assertOk()->json('backup.filename');

        Product::query()->delete();
        $this->assertDatabaseMissing('products', ['name' => 'Producto Respaldo']);

        $this->postJson("/api/backups/{$filename}/restore")->assertOk();

        $this->assertDatabaseHas('products', ['name' => 'Producto Respaldo']);
    }

    public function test_uploaded_backup_file_restores_database_contents(): void
    {
        Storage::fake('local');
        $admin = $this->userWithPermissions(['backups.manage']);
        Sanctum::actingAs($admin);

        Product::factory()->create(['name' => 'Producto Respaldo']);

        $filename = $this->postJson('/api/backups')->assertOk()->json('backup.filename');
        $sql = Storage::disk('local')->get('backups/'.$filename);

        Product::query()->delete();
        $this->assertDatabaseMissing('products', ['name' => 'Producto Respaldo']);

        $file = UploadedFile::fake()->createWithContent('respaldo.sql', $sql);

        $this->postJson('/api/backups/restore', ['backup' => $file])->assertOk();

        $this->assertDatabaseHas('products', ['name' => 'Producto Respaldo']);
    }

    public function test_uploaded_backup_with_injected_sql_is_rejected(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->userWithPermissions(['backups.manage']));

        $filename = $this->postJson('/api/backups')->assertOk()->json('backup.filename');
        $sql = Storage::disk('local')->get('backups/'.$filename);
        $tampered = preg_replace('/\n-- Signature: /', "\nINSERT INTO `roles` (`name`, `slug`) VALUES ('x', 'injected');\n-- Signature: ", $sql);

        $this->postJson('/api/backups/restore', ['backup' => UploadedFile::fake()->createWithContent('respaldo.sql', $tampered)])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El respaldo no tiene una firma válida de este sistema o fue modificado');

        $this->assertDatabaseMissing('roles', ['slug' => 'injected']);
    }

    public function test_uploaded_backup_without_signature_is_rejected(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->userWithPermissions(['backups.manage']));

        $sql = "-- POS Inventory backup 2026-01-01 00:00:00\n-- Driver: sqlite\n\nINSERT INTO `roles` (`name`, `slug`) VALUES ('x', 'injected');\n";

        $this->postJson('/api/backups/restore', ['backup' => UploadedFile::fake()->createWithContent('respaldo.sql', $sql)])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('roles', ['slug' => 'injected']);
    }

    public function test_restore_saves_safety_backup_of_current_data(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->userWithPermissions(['backups.manage']));

        $filename = $this->postJson('/api/backups')->assertOk()->json('backup.filename');

        Product::factory()->create(['name' => 'Producto Posterior']);

        $safetyBackup = $this->postJson("/api/backups/{$filename}/restore")
            ->assertOk()
            ->json('safety_backup');

        $this->assertStringStartsWith('auto-before-restore-', $safetyBackup);
        $this->assertStringContainsString(
            'Producto Posterior',
            Storage::disk('local')->get('backups/'.$safetyBackup)
        );
        $this->assertDatabaseMissing('products', ['name' => 'Producto Posterior']);
    }

    public function test_dump_uses_explicit_column_lists(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->userWithPermissions(['backups.manage']));

        Product::factory()->create(['name' => 'Producto Columnas']);

        $filename = $this->postJson('/api/backups')->assertOk()->json('backup.filename');
        $sql = Storage::disk('local')->get('backups/'.$filename);

        $this->assertMatchesRegularExpression('/INSERT INTO `products` \(`id`, `[^`]+`.*\) VALUES/', $sql);
        $this->assertStringNotContainsString('INSERT INTO `personal_access_tokens`', $sql);
    }

    public function test_uploaded_file_that_is_not_a_system_backup_is_rejected(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->userWithPermissions(['backups.manage']));

        Product::factory()->create(['name' => 'Producto Intacto']);

        $file = UploadedFile::fake()->createWithContent('malicioso.sql', 'DROP TABLE products;');

        $this->postJson('/api/backups/restore', ['backup' => $file])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El archivo no es un respaldo válido generado por el sistema');

        $this->assertDatabaseHas('products', ['name' => 'Producto Intacto']);
        $this->assertEmpty(Storage::disk('local')->files('backups'));
    }

    public function test_backup_from_another_database_driver_is_rejected(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->userWithPermissions(['backups.manage']));

        $sql = "-- POS Inventory backup 2026-01-01 00:00:00\n-- Driver: mysql\n\nSET FOREIGN_KEY_CHECKS = 0;\n";
        $file = UploadedFile::fake()->createWithContent('respaldo.sql', $sql);

        $this->postJson('/api/backups/restore', ['backup' => $file])->assertUnprocessable();
    }

    public function test_backups_require_permission(): void
    {
        $user = $this->userWithPermissions([]);
        Sanctum::actingAs($user);

        $this->getJson('/api/backups')->assertForbidden();
        $this->postJson('/api/backups')->assertForbidden();
    }

    public function test_settings_edit_permission_does_not_grant_backup_access(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['settings.view', 'settings.edit']));

        $this->getJson('/api/backups')->assertForbidden();
        $this->postJson('/api/backups')->assertForbidden();
        $this->postJson('/api/backups/restore')->assertForbidden();
    }
}
