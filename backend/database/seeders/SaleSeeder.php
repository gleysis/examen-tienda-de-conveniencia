<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $products = Product::all();

        for ($i = 0; $i < 20; $i++) {
            $daysAgo = rand(0, 6);
            $saleDate = now()->subDays($daysAgo);

            $numItems = rand(1, 3);
            $selectedProducts = $products->random($numItems);
            $subtotal = 0;
            $saleItems = [];

            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 3);
                $itemSubtotal = $quantity * $product->price;
                $subtotal += $itemSubtotal;

                $saleItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'discount' => 0,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $discount = 0;
            $tax = $subtotal * 0.16;
            $total = $subtotal - $discount + $tax;

            $invoiceNumber = 'INV-'.str_pad(Sale::count() + $i + 1, 6, '0', STR_PAD_LEFT);

            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'user_id' => $user->id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => ['cash', 'card', 'transfer'][rand(0, 2)],
                'status' => 'completed',
                'created_at' => $saleDate,
                'updated_at' => $saleDate,
            ]);

            foreach ($saleItems as $item) {
                $sale->items()->create($item);
            }
        }
    }
}
