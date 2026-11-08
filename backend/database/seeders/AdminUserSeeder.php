<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('app.admin.email');
        $configuredPassword = config('app.admin.password');
        $password = $configuredPassword ?: Str::password(16, symbols: false);

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
            ]
        );

        if ($admin->wasRecentlyCreated && ! $configuredPassword) {
            $this->command?->warn("Administrador creado: {$email} / {$password} (cámbiala después de iniciar sesión)");
        }

        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole && ! $admin->roles()->where('role_id', $adminRole->id)->exists()) {
            $admin->roles()->attach($adminRole->id);
        }
    }
}
