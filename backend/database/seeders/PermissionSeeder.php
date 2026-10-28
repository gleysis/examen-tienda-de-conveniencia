<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Users permissions
        $permissions = [
            // Users
            ['name' => 'Ver usuarios', 'slug' => 'users.view', 'description' => 'Ver lista de usuarios'],
            ['name' => 'Crear usuarios', 'slug' => 'users.create', 'description' => 'Crear nuevos usuarios'],
            ['name' => 'Editar usuarios', 'slug' => 'users.edit', 'description' => 'Editar usuarios existentes'],
            ['name' => 'Eliminar usuarios', 'slug' => 'users.delete', 'description' => 'Eliminar usuarios'],

            // Suppliers
            ['name' => 'Ver proveedores', 'slug' => 'suppliers.view', 'description' => 'Ver lista de proveedores'],
            ['name' => 'Crear proveedores', 'slug' => 'suppliers.create', 'description' => 'Crear nuevos proveedores'],
            ['name' => 'Editar proveedores', 'slug' => 'suppliers.edit', 'description' => 'Editar proveedores existentes'],
            ['name' => 'Eliminar proveedores', 'slug' => 'suppliers.delete', 'description' => 'Eliminar proveedores'],

            // Products
            ['name' => 'Ver productos', 'slug' => 'products.view', 'description' => 'Ver lista de productos'],
            ['name' => 'Crear productos', 'slug' => 'products.create', 'description' => 'Crear nuevos productos'],
            ['name' => 'Editar productos', 'slug' => 'products.edit', 'description' => 'Editar productos existentes'],
            ['name' => 'Eliminar productos', 'slug' => 'products.delete', 'description' => 'Eliminar productos'],

            // Categories
            ['name' => 'Ver categorías', 'slug' => 'categories.view', 'description' => 'Ver lista de categorías'],
            ['name' => 'Crear categorías', 'slug' => 'categories.create', 'description' => 'Crear nuevas categorías'],
            ['name' => 'Editar categorías', 'slug' => 'categories.edit', 'description' => 'Editar categorías existentes'],
            ['name' => 'Eliminar categorías', 'slug' => 'categories.delete', 'description' => 'Eliminar categorías'],

            // Sales
            ['name' => 'Ver ventas', 'slug' => 'sales.view', 'description' => 'Ver lista de ventas'],
            ['name' => 'Crear ventas', 'slug' => 'sales.create', 'description' => 'Crear nuevas ventas (POS)'],
            ['name' => 'Editar ventas', 'slug' => 'sales.edit', 'description' => 'Editar ventas existentes'],
            ['name' => 'Eliminar ventas', 'slug' => 'sales.delete', 'description' => 'Eliminar ventas'],

            // Inventory
            ['name' => 'Ver inventario', 'slug' => 'inventory.view', 'description' => 'Ver movimientos de inventario'],
            ['name' => 'Crear movimientos', 'slug' => 'inventory.create', 'description' => 'Crear movimientos de inventario'],
            ['name' => 'Ver stock bajo', 'slug' => 'inventory.low-stock', 'description' => 'Ver reporte de stock bajo'],
            ['name' => 'Ver reportes', 'slug' => 'inventory.reports', 'description' => 'Ver reportes de inventario'],

            // Returns
            ['name' => 'Ver devoluciones', 'slug' => 'returns.view', 'description' => 'Ver lista de devoluciones'],
            ['name' => 'Crear devoluciones', 'slug' => 'returns.create', 'description' => 'Crear nuevas devoluciones'],
            ['name' => 'Editar devoluciones', 'slug' => 'returns.edit', 'description' => 'Editar devoluciones existentes'],
            ['name' => 'Eliminar devoluciones', 'slug' => 'returns.delete', 'description' => 'Eliminar devoluciones'],
            ['name' => 'Procesar devoluciones', 'slug' => 'returns.process', 'description' => 'Procesar devoluciones pendientes'],

            // Discounts
            ['name' => 'Ver descuentos', 'slug' => 'discounts.view', 'description' => 'Ver lista de descuentos'],
            ['name' => 'Crear descuentos', 'slug' => 'discounts.create', 'description' => 'Crear nuevos descuentos'],
            ['name' => 'Editar descuentos', 'slug' => 'discounts.edit', 'description' => 'Editar descuentos existentes'],
            ['name' => 'Eliminar descuentos', 'slug' => 'discounts.delete', 'description' => 'Eliminar descuentos'],
            ['name' => 'Validar descuentos', 'slug' => 'discounts.validate', 'description' => 'Validar códigos de descuento'],

            // Cash Registers
            ['name' => 'Ver cajas', 'slug' => 'cash-registers.view', 'description' => 'Ver lista de cajas registradoras'],
            ['name' => 'Crear cajas', 'slug' => 'cash-registers.create', 'description' => 'Crear nuevas cajas'],
            ['name' => 'Editar cajas', 'slug' => 'cash-registers.edit', 'description' => 'Editar cajas existentes'],
            ['name' => 'Eliminar cajas', 'slug' => 'cash-registers.delete', 'description' => 'Eliminar cajas'],
            ['name' => 'Abrir cajas', 'slug' => 'cash-registers.open', 'description' => 'Abrir cajas registradoras'],
            ['name' => 'Cerrar cajas', 'slug' => 'cash-registers.close', 'description' => 'Cerrar cajas registradoras'],

            // Settings
            ['name' => 'Ver configuración', 'slug' => 'settings.view', 'description' => 'Ver configuración del sistema'],
            ['name' => 'Editar configuración', 'slug' => 'settings.edit', 'description' => 'Editar configuración del sistema'],

            // Reports
            ['name' => 'Ver reportes', 'slug' => 'reports.view', 'description' => 'Ver reportes del sistema'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                ]
            );
        }

        // Assign permissions to roles
        $adminRole = Role::where('slug', 'admin')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $warehouseRole = Role::where('slug', 'warehouse')->first();

        if ($adminRole) {
            // Admin has all permissions
            $adminRole->permissions()->sync(Permission::pluck('id'));
        }

        if ($managerRole) {
            // Manager has most permissions except user management
            $managerPermissions = Permission::whereNotIn('slug', ['users.create', 'users.edit', 'users.delete'])->pluck('id');
            $managerRole->permissions()->sync($managerPermissions);
        }

        if ($cashierRole) {
            // Cashier can create sales, view inventory, use POS
            $cashierPermissions = Permission::whereIn('slug', [
                'sales.view',
                'sales.create',
                'inventory.view',
                'inventory.low-stock',
                'discounts.validate',
                'cash-registers.open',
                'cash-registers.close',
                'cash-registers.view',
                'returns.view',
                'returns.create',
                'reports.view',
            ])->pluck('id');
            $cashierRole->permissions()->sync($cashierPermissions);
        }

        if ($warehouseRole) {
            // Warehouse can manage inventory, products, suppliers
            $warehousePermissions = Permission::whereIn('slug', [
                'inventory.view',
                'inventory.create',
                'inventory.low-stock',
                'inventory.reports',
                'products.view',
                'products.create',
                'products.edit',
                'suppliers.view',
                'suppliers.create',
                'suppliers.edit',
                'categories.view',
                'categories.create',
                'categories.edit',
                'reports.view',
            ])->pluck('id');
            $warehouseRole->permissions()->sync($warehousePermissions);
        }
    }
}
