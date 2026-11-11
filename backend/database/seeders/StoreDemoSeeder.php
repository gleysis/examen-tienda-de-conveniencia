<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StoreDemoSeeder extends Seeder
{
    /**
     * Datos de demostración para "Tienda La Esquina":
     * categorías, proveedores, productos de abarrotes y usuarios del equipo.
     */
    public function run(): void
    {
        // ---------- Categorías ----------
        $categoryNames = [
            ['name' => 'Bebidas', 'slug' => 'bebidas', 'description' => 'Refrescos, agua y bebidas'],
            ['name' => 'Snacks', 'slug' => 'snacks', 'description' => 'Botanas, galletas y golosinas'],
            ['name' => 'Abarrotes', 'slug' => 'abarrotes', 'description' => 'Granos, despensa y panadería'],
            ['name' => 'Lácteos', 'slug' => 'lacteos', 'description' => 'Leche, huevo y derivados'],
            ['name' => 'Higiene', 'slug' => 'higiene', 'description' => 'Cuidado personal y limpieza'],
        ];

        $categories = collect();
        foreach ($categoryNames as $cat) {
            $categories->push(Category::firstOrCreate(['slug' => $cat['slug']], $cat));
        }
        $categories = $categories->keyBy('slug');

        // ---------- Proveedores ----------
        $suppliers = collect([
            ['name' => 'Distribuidora La Esquina', 'contact_person' => 'Pedro Ruiz', 'phone' => '8888-1234'],
            ['name' => 'Embotelladora Nacional', 'contact_person' => 'Laura Soto', 'phone' => '2255-8899'],
        ])->map(function ($s) {
            return Supplier::firstOrCreate(['name' => $s['name']], $s + ['is_active' => true]);
        });
        $defaultSupplier = $suppliers->first();

        // ---------- Productos ----------
        $products = [
            // Bebidas
            ['Coca Cola 600 ml', 'BEB-001', 25, 18, 48, 'bebidas', '7501000001'],
            ['Pepsi 600 ml', 'BEB-002', 25, 18, 40, 'bebidas', '7501000002'],
            ['Gatorade 500 ml', 'BEB-003', 30, 22, 30, 'bebidas', '7501000003'],
            ['Agua 600 ml', 'BEB-004', 15, 9, 60, 'bebidas', '7501000004'],
            ['Jugo de Naranja 1 L', 'BEB-005', 45, 32, 20, 'bebidas', '7501000005'],
            // Snacks
            ['Sabritas 45 g', 'SNK-001', 20, 13, 55, 'snacks', '7501000101'],
            ['Galletas Oreo', 'SNK-002', 22, 15, 40, 'snacks', '7501000102'],
            ['Doritos 60 g', 'SNK-003', 20, 13, 45, 'snacks', '7501000103'],
            ['Chocolate Barra', 'SNK-004', 35, 24, 30, 'snacks', '7501000104'],
            // Abarrotes
            ['Pan Bimbo', 'ABA-001', 28, 20, 25, 'abarrotes', '7501000201'],
            ['Arroz 1 kg', 'ABA-002', 40, 30, 50, 'abarrotes', '7501000202'],
            ['Azúcar 1 kg', 'ABA-003', 32, 24, 45, 'abarrotes', '7501000203'],
            ['Café Molido 250 g', 'ABA-004', 65, 48, 20, 'abarrotes', '7501000204'],
            ['Aceite 1 L', 'ABA-005', 75, 58, 18, 'abarrotes', '7501000205'],
            ['Fósforos Caja', 'ABA-006', 12, 6, 80, 'abarrotes', '7501000206'],
            // Lácteos
            ['Leche 1 L', 'LAC-001', 38, 28, 35, 'lacteos', '7501000301'],
            ['Huevo Cartón 12', 'LAC-002', 55, 42, 22, 'lacteos', '7501000302'],
            ['Yogurt Natural 1 L', 'LAC-003', 45, 33, 15, 'lacteos', '7501000303'],
            ['Queso Fresco 250 g', 'LAC-004', 60, 44, 12, 'lacteos', '7501000304'],
            // Higiene
            ['Papel Higiénico 4 und', 'HIG-001', 45, 32, 30, 'higiene', '7501000401'],
            ['Pasta Dental', 'HIG-002', 40, 27, 25, 'higiene', '7501000402'],
            ['Jabón de Baño', 'HIG-003', 28, 18, 35, 'higiene', '7501000403'],
            ['Shampoo 350 ml', 'HIG-004', 85, 62, 15, 'higiene', '7501000404'],
            ['Detergente 1 kg', 'HIG-005', 70, 52, 20, 'higiene', '7501000405'],
        ];

        foreach ($products as [$name, $sku, $price, $cost, $stock, $catSlug, $barcode]) {
            Product::firstOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'description' => $name,
                    'price' => $price,
                    'cost' => $cost,
                    'stock' => $stock,
                    'min_stock' => 8,
                    'category_id' => $categories->get($catSlug)?->id,
                    'supplier_id' => $defaultSupplier?->id,
                    'barcode' => $barcode,
                    'is_active' => true,
                ]
            );
        }

        // ---------- Usuarios del equipo ----------
        $roleBySlug = fn (string $slug) => Role::where('slug', $slug)->first();

        $users = [
            ['Gleysis Leiva', 'gleysis@tienda.com', 'admin'],
            ['Ana Torres', 'ana@tienda.com', 'cashier'],
            ['Carlos Méndez', 'carlos@tienda.com', 'cashier'],
            ['María López', 'maria@tienda.com', 'warehouse'],
        ];

        foreach ($users as [$name, $email, $roleSlug]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password')]
            );

            $role = $roleBySlug($roleSlug);
            if ($role && ! $user->roles()->where('role_id', $role->id)->exists()) {
                $user->roles()->attach($role->id);
            }

            $user->ensureCashRegister();
        }

        // ---------- Datos de la tienda ----------
        $settings = [
            'store_name' => 'Tienda La Esquina',
            'store_address' => 'Del parque central 2 cuadras al este',
            'store_phone' => '8888-0000',
        ];

        foreach ($settings as $key => $value) {
            SystemSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => 'string', 'group' => 'store', 'is_public' => true]
            );
        }
    }
}
