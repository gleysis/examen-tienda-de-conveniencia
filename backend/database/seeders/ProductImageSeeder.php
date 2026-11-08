<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageSeeder extends Seeder
{
    /**
     * Genera una imagen SVG por producto (emoji de categoría + nombre)
     * y la asigna al campo image.
     */
    private const STYLES = [
        'bebidas' => ['emoji' => '🥤', 'from' => '#ef4444', 'to' => '#f87171'],
        'snacks' => ['emoji' => '🍪', 'from' => '#f59e0b', 'to' => '#fbbf24'],
        'abarrotes' => ['emoji' => '🥫', 'from' => '#a16207', 'to' => '#ca8a04'],
        'lacteos' => ['emoji' => '🥛', 'from' => '#0ea5e9', 'to' => '#38bdf8'],
        'higiene' => ['emoji' => '🧴', 'from' => '#0d9488', 'to' => '#2dd4bf'],
        'alimentos' => ['emoji' => '🍎', 'from' => '#16a34a', 'to' => '#4ade80'],
        'hogar' => ['emoji' => '🏠', 'from' => '#7c3aed', 'to' => '#a78bfa'],
        'electronicos' => ['emoji' => '💻', 'from' => '#4f46e5', 'to' => '#818cf8'],
        'ropa' => ['emoji' => '👕', 'from' => '#db2777', 'to' => '#f472b6'],
        'salud' => ['emoji' => '💊', 'from' => '#dc2626', 'to' => '#f87171'],
        'default' => ['emoji' => '📦', 'from' => '#64748b', 'to' => '#94a3b8'],
    ];

    public function run(): void
    {
        Product::with('category:id,slug')->chunk(50, function ($products) {
            foreach ($products as $product) {
                $style = self::STYLES[$product->category?->slug] ?? self::STYLES['default'];

                $fileName = 'products/'.Str::slug($product->sku).'.svg';
                Storage::disk('public')->put($fileName, $this->svg($product->name, $style));

                $product->update(['image' => asset('storage/'.$fileName)]);
            }
        });
    }

    /**
     * @param  array{emoji: string, from: string, to: string}  $style
     */
    private function svg(string $name, array $style): string
    {
        $name = htmlspecialchars($name, ENT_XML1);
        [$line1, $line2] = $this->wrapName($name);

        $nameText = $line2 === ''
            ? '<text x="200" y="235" text-anchor="middle" font-family="Nunito, Arial, sans-serif" font-size="30" font-weight="800" fill="#ffffff">'.$line1.'</text>'
            : '<text x="200" y="222" text-anchor="middle" font-family="Nunito, Arial, sans-serif" font-size="26" font-weight="800" fill="#ffffff">'.$line1.'</text>'
              .'<text x="200" y="255" text-anchor="middle" font-family="Nunito, Arial, sans-serif" font-size="26" font-weight="800" fill="#ffffff">'.$line2.'</text>';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$style['from']}"/>
      <stop offset="1" stop-color="{$style['to']}"/>
    </linearGradient>
  </defs>
  <rect width="400" height="300" rx="16" fill="url(#g)"/>
  <circle cx="200" cy="115" r="70" fill="rgba(255,255,255,0.2)"/>
  <text x="200" y="142" text-anchor="middle" font-size="80">{$style['emoji']}</text>
  {$nameText}
</svg>
SVG;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function wrapName(string $name): array
    {
        if (mb_strlen($name) <= 20) {
            return [$name, ''];
        }

        $words = explode(' ', $name);
        $line1 = '';
        $line2 = '';
        $target = $line1;

        foreach ($words as $word) {
            if (mb_strlen($target.' '.$word) > 20 && $target === $line1) {
                $target = &$line2;
            }
            $target = trim($target.' '.$word);
        }

        return [$line1, $line2];
    }
}
