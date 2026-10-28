<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): array
    {
        $today = now()->startOfDay();
        $sevenDaysAgo = now()->subDays(7)->startOfDay();

        $totalSalesToday = Sale::where('status', 'completed')
            ->where('created_at', '>=', $today)
            ->sum('total');

        $totalProducts = Product::where('is_active', true)->count();

        $lowStockProducts = Product::where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->count();

        $activeUsers = User::count();

        $salesLast7Days = Sale::where('status', 'completed')
            ->where('created_at', '>=', $sevenDaysAgo)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($sale) {
                return [
                    'date' => $sale->date,
                    'total' => (float) $sale->total,
                ];
            });

        $topCategories = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->where('sales.created_at', '>=', $sevenDaysAgo)
            ->select('categories.name', DB::raw('SUM(sale_items.quantity) as total_sold'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get()
            ->map(function ($category) {
                return [
                    'name' => $category->name,
                    'total_sold' => (int) $category->total_sold,
                ];
            });

        return [
            'total_sales_today' => (float) $totalSalesToday,
            'total_products' => $totalProducts,
            'low_stock_products' => $lowStockProducts,
            'active_users' => $activeUsers,
            'sales_last_7_days' => $salesLast7Days,
            'top_categories' => $topCategories,
        ];
    }

    public function topProducts(Request $request): array
    {
        $dateFrom = $request->input('date_from', now()->subDays(30)->startOfDay());
        $dateTo = $request->input('date_to', now()->endOfDay());

        $topProducts = SaleItem::with('product')
            ->whereHas('sale', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->where('status', 'completed');
            })
            ->selectRaw('product_id, SUM(quantity) as total_quantity, SUM(quantity * unit_price) as total_revenue')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'product' => $item->product,
                    'total_quantity' => $item->total_quantity,
                    'total_revenue' => (float) $item->total_revenue,
                ];
            });

        return [
            'top_products' => $topProducts,
        ];
    }

    public function salesByPaymentMethod(Request $request): array
    {
        $dateFrom = $request->input('date_from', now()->subDays(30)->startOfDay());
        $dateTo = $request->input('date_to', now()->endOfDay());

        $salesByMethod = Sale::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'completed')
            ->selectRaw('payment_method, COUNT(*) as total_transactions, SUM(total) as total_revenue')
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'payment_method' => $item->payment_method,
                    'total_transactions' => $item->total_transactions,
                    'total_revenue' => (float) $item->total_revenue,
                ];
            });

        return [
            'sales_by_payment_method' => $salesByMethod,
        ];
    }
}
