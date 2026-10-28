<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrador',
                'slug' => 'admin',
                'description' => 'Acceso total al sistema',
            ],
            [
                'name' => 'Gerente',
                'slug' => 'manager',
                'description' => 'Gestión de inventario y reportes',
            ],
            [
                'name' => 'Cajero',
                'slug' => 'cashier',
                'description' => 'Solo ventas y caja',
            ],
            [
                'name' => 'Almacén',
                'slug' => 'warehouse',
                'description' => 'Gestión de inventario',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
