<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class BulkProductSeeder extends Seeder
{
    /**
     * ~80 productos adicionales repartidos en todas las categorías.
     * [nombre, sku, precio (C$), costo, stock, slug categoría]
     */
    public function run(): void
    {
        $categories = Category::all()->keyBy('slug');
        $supplier = Supplier::first();
        $barcode = 7501001000;

        $products = [
            // ---------- Bebidas ----------
            ['Fanta Naranja 600 ml', 'BEB-006', 25, 18, 42, 'bebidas'],
            ['Sprite 600 ml', 'BEB-007', 25, 18, 38, 'bebidas'],
            ['Coca Cola 1.25 L', 'BEB-008', 45, 34, 30, 'bebidas'],
            ['Pepsi 1.25 L', 'BEB-009', 45, 34, 28, 'bebidas'],
            ['Cerveza Toña Lata', 'BEB-010', 50, 38, 60, 'bebidas'],
            ['Cerveza Victoria Lata', 'BEB-011', 50, 38, 55, 'bebidas'],
            ['Red Bull 250 ml', 'BEB-012', 65, 50, 24, 'bebidas'],
            ['Té Frío Lipton 500 ml', 'BEB-013', 28, 20, 26, 'bebidas'],
            ['Energizante Volt 473 ml', 'BEB-014', 22, 15, 40, 'bebidas'],
            ['Agua 1.5 L', 'BEB-015', 25, 16, 45, 'bebidas'],

            // ---------- Snacks ----------
            ['Tajaditas de Plátano', 'SNK-005', 15, 9, 60, 'snacks'],
            ['Cheetos 50 g', 'SNK-006', 20, 13, 48, 'snacks'],
            ['Pringles Original', 'SNK-007', 40, 30, 18, 'snacks'],
            ['Chicle Trident', 'SNK-008', 10, 5, 90, 'snacks'],
            ['M&M 45 g', 'SNK-009', 30, 21, 35, 'snacks'],
            ['Palomitas Microondas', 'SNK-010', 18, 11, 30, 'snacks'],
            ['Galletas María', 'SNK-011', 15, 9, 50, 'snacks'],
            ['Maní Salado 100 g', 'SNK-012', 25, 16, 40, 'snacks'],
            ['Paleta Mixta', 'SNK-013', 5, 2, 120, 'snacks'],
            ['Snickers 52 g', 'SNK-014', 32, 23, 28, 'snacks'],
            ['Galletas Soda', 'SNK-015', 12, 7, 70, 'snacks'],

            // ---------- Abarrotes ----------
            ['Frijol Rojo 1 kg', 'ABA-007', 55, 42, 40, 'abarrotes'],
            ['Sal Yodada 1 kg', 'ABA-008', 15, 9, 55, 'abarrotes'],
            ['Harina de Maíz 1 kg', 'ABA-009', 35, 26, 38, 'abarrotes'],
            ['Espagueti 500 g', 'ABA-010', 28, 19, 45, 'abarrotes'],
            ['Sardina en Lata', 'ABA-011', 35, 25, 32, 'abarrotes'],
            ['Atún en Lata', 'ABA-012', 45, 33, 36, 'abarrotes'],
            ['Sopa Instantánea', 'ABA-013', 12, 7, 85, 'abarrotes'],
            ['Vinagre 500 ml', 'ABA-014', 30, 20, 20, 'abarrotes'],
            ['Avena 500 g', 'ABA-015', 38, 28, 25, 'abarrotes'],
            ['Pan Tostado', 'ABA-016', 30, 21, 18, 'abarrotes'],

            // ---------- Lácteos ----------
            ['Mantequilla 250 g', 'LAC-005', 60, 46, 20, 'lacteos'],
            ['Crema 500 ml', 'LAC-006', 55, 41, 18, 'lacteos'],
            ['Leche Deslactosada 1 L', 'LAC-007', 42, 32, 24, 'lacteos'],
            ['Queso Seco 250 g', 'LAC-008', 70, 54, 12, 'lacteos'],
            ['Cuajada 250 g', 'LAC-009', 65, 50, 10, 'lacteos'],
            ['Leche en Polvo 400 g', 'LAC-010', 120, 95, 15, 'lacteos'],
            ['Helado 1 L', 'LAC-011', 90, 68, 14, 'lacteos'],
            ['Chocomilk 1 L', 'LAC-012', 40, 30, 22, 'lacteos'],

            // ---------- Higiene ----------
            ['Jabón de Ropa Barra', 'HIG-006', 25, 16, 40, 'higiene'],
            ['Cloro 1 L', 'HIG-007', 30, 20, 35, 'higiene'],
            ['Desinfectante 1 L', 'HIG-008', 55, 40, 22, 'higiene'],
            ['Suavizante 1 L', 'HIG-009', 65, 48, 18, 'higiene'],
            ['Papel Toalla', 'HIG-010', 38, 27, 25, 'higiene'],
            ['Cepillo Dental', 'HIG-011', 20, 11, 45, 'higiene'],
            ['Enjuague Bucal 500 ml', 'HIG-012', 70, 52, 12, 'higiene'],
            ['Desodorante', 'HIG-013', 60, 44, 20, 'higiene'],
            ['Toallas Sanitarias', 'HIG-014', 48, 35, 28, 'higiene'],
            ['Talco 200 g', 'HIG-015', 50, 36, 16, 'higiene'],

            // ---------- Alimentos (frescos) ----------
            ['Plátano (unidad)', 'ALI-001', 5, 3, 100, 'alimentos'],
            ['Tomate 1 lb', 'ALI-002', 18, 12, 60, 'alimentos'],
            ['Cebolla 1 lb', 'ALI-003', 20, 14, 55, 'alimentos'],
            ['Papa 1 lb', 'ALI-004', 22, 15, 50, 'alimentos'],
            ['Aguacate (unidad)', 'ALI-005', 15, 10, 35, 'alimentos'],
            ['Limón (unidad)', 'ALI-006', 5, 3, 80, 'alimentos'],
            ['Chiltoma 1 lb', 'ALI-007', 12, 8, 40, 'alimentos'],
            ['Repollo 1 lb', 'ALI-008', 18, 12, 30, 'alimentos'],

            // ---------- Hogar ----------
            ['Velas (paquete)', 'HOG-002', 20, 12, 35, 'hogar'],
            ['Pilas AA x2', 'HOG-003', 60, 42, 30, 'hogar'],
            ['Foco LED 9W', 'HOG-004', 75, 55, 25, 'hogar'],
            ['Escoba', 'HOG-005', 95, 70, 12, 'hogar'],
            ['Trapeador', 'HOG-006', 85, 62, 10, 'hogar'],
            ['Cubeta Plástica', 'HOG-007', 70, 50, 15, 'hogar'],
            ['Esponja de Cocina', 'HOG-008', 15, 8, 50, 'hogar'],
            ['Bolsas de Basura', 'HOG-009', 30, 20, 40, 'hogar'],

            // ---------- Electrónicos ----------
            ['Cable USB', 'ELE-001', 150, 95, 20, 'electronicos'],
            ['Cargador de Celular', 'ELE-002', 250, 170, 15, 'electronicos'],
            ['Audífonos Básicos', 'ELE-003', 180, 120, 18, 'electronicos'],
            ['Bocina Bluetooth', 'ELE-004', 450, 330, 8, 'electronicos'],
            ['Mouse Inalámbrico', 'ELE-005', 300, 210, 12, 'electronicos'],

            // ---------- Ropa ----------
            ['Calcetines (par)', 'ROP-002', 50, 30, 40, 'ropa'],
            ['Camiseta Básica', 'ROP-003', 150, 95, 25, 'ropa'],
            ['Gorra', 'ROP-004', 120, 75, 18, 'ropa'],
            ['Sandalias', 'ROP-005', 200, 140, 15, 'ropa'],
            ['Boxer', 'ROP-006', 80, 50, 22, 'ropa'],

            // ---------- Salud ----------
            ['Alcohol 500 ml', 'SAL-002', 45, 32, 30, 'salud'],
            ['Paracetamol Caja', 'SAL-003', 60, 40, 35, 'salud'],
            ['Curitas (caja)', 'SAL-004', 25, 15, 45, 'salud'],
            ['Vitamina C 60 und', 'SAL-005', 90, 65, 18, 'salud'],
            ['Suero Oral', 'SAL-006', 20, 12, 50, 'salud'],
            ['Alcohol Gel 250 ml', 'SAL-007', 40, 27, 25, 'salud'],
        ];

        foreach ($products as [$name, $sku, $price, $cost, $stock, $catSlug]) {
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
                    'supplier_id' => $supplier?->id,
                    'barcode' => (string) $barcode++,
                    'is_active' => true,
                ]
            );
        }
    }
}
