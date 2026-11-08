<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\ReturnRefund;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\SystemSettings;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Tarjetas de indicadores principales.
     */
    public function index(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($request, 30);
        $scopedCashier = $this->scopedCashierId($request);

        $todayFrom = now()->startOfDay();
        $todayTo = now()->endOfDay();

        $todaySales = $this->completedSales($request)->whereBetween('created_at', [$todayFrom, $todayTo]);
        $periodSales = $this->completedSales($request)->whereBetween('created_at', [$dateFrom, $dateTo]);

        $lowStockCount = SystemSettings::value('low_stock_alerts', true)
            ? Product::where('is_active', true)->lowStock()->count()
            : 0;

        $outOfStockCount = SystemSettings::value('notify_out_of_stock', true)
            ? Product::where('is_active', true)->where('stock', '<=', 0)->count()
            : 0;

        $openRegistersQuery = CashRegister::query()->open();
        if ($scopedCashier !== null) {
            $openRegistersQuery->where('user_id', $scopedCashier);
        }

        $response = [
            'scope' => $scopedCashier !== null ? 'own' : 'all',
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            'cards' => [
                'sales_today' => (float) $todaySales->sum('total'),
                'invoices_today' => (int) $todaySales->count(),
                'sales_period' => (float) $periodSales->sum('total'),
                'invoices_period' => (int) $periodSales->count(),
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'open_registers' => $openRegistersQuery->count(),
            ],
        ];

        if ($scopedCashier !== null) {
            $register = CashRegister::query()->open()->where('user_id', $scopedCashier)->first();
            $response['my_register'] = $register ? [
                'id' => $register->id,
                'name' => $register->name,
                'opened_at' => $register->opened_at,
                'opening_balance' => (float) $register->opening_balance,
                'current_balance' => (float) $register->current_balance,
                'cash_sales' => (float) $register->cash_sales,
                'card_sales' => (float) $register->card_sales,
                'transfer_sales' => (float) $register->transfer_sales,
                'total_sales' => (float) $register->total_sales,
            ] : null;
        }

        return $response;
    }

    /**
     * Ventas por día con comparación opcional contra el período anterior.
     */
    public function salesChart(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($request, 7);

        $current = $this->dailySeries($request, $dateFrom, $dateTo);

        $response = [
            'series' => $current['series'],
            'total' => $current['total'],
            'invoices' => $current['invoices'],
        ];

        if ($request->boolean('compare')) {
            $days = max(1, (int) $dateFrom->diffInDays($dateTo) + 1);
            $previousFrom = $dateFrom->copy()->subDays($days)->startOfDay();
            $previousTo = $dateFrom->copy()->subDay()->endOfDay();
            $previous = $this->dailySeries($request, $previousFrom, $previousTo);

            $response['previous'] = [
                'from' => $previousFrom->toDateString(),
                'to' => $previousTo->toDateString(),
                'series' => $previous['series'],
                'total' => $previous['total'],
                'invoices' => $previous['invoices'],
            ];
        }

        return $response;
    }

    /**
     * Productos más vendidos por unidades o ingresos (neto de devoluciones).
     */
    public function topProducts(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($request, 30);
        $metric = $request->input('metric') === 'revenue' ? 'revenue' : 'quantity';
        $limit = min(20, max(1, $request->integer('limit', 10)));
        $scopedCashier = $this->scopedCashierId($request);

        $items = SaleItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(subtotal) as total_revenue'))
            ->whereHas('sale', function (Builder $query) use ($dateFrom, $dateTo, $scopedCashier) {
                $query->where('status', 'completed')
                    ->whereBetween('created_at', [$dateFrom, $dateTo]);

                if ($scopedCashier !== null) {
                    $query->where('user_id', $scopedCashier);
                }
            })
            ->groupBy('product_id')
            ->get();

        $returnsQuery = ReturnRefund::query()
            ->select('product_id', DB::raw('SUM(quantity) as returned_quantity'), DB::raw('SUM(refund_amount) as returned_amount'))
            ->where('status', 'processed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('product_id');

        if ($scopedCashier !== null) {
            $returnsQuery->where('user_id', $scopedCashier);
        }

        $returns = $returnsQuery->get()->keyBy('product_id');

        $products = Product::whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');

        $topProducts = $items
            ->map(function ($item) use ($returns, $products) {
                $returned = $returns->get($item->product_id);
                $netQuantity = (int) $item->total_quantity - (int) ($returned->returned_quantity ?? 0);
                $netRevenue = (float) $item->total_revenue - (float) ($returned->returned_amount ?? 0);

                $product = $products->get($item->product_id);

                return [
                    'product' => $product ? [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                    ] : null,
                    'total_quantity' => $netQuantity,
                    'total_revenue' => $netRevenue,
                ];
            })
            ->filter(fn ($item) => $item['total_quantity'] > 0)
            ->sortByDesc($metric === 'revenue' ? 'total_revenue' : 'total_quantity')
            ->take($limit)
            ->values();

        return [
            'metric' => $metric,
            'top_products' => $topProducts,
        ];
    }

    /**
     * Distribución de ventas por método de pago.
     */
    public function salesByPaymentMethod(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($request, 30);

        $rows = $this->completedSales($request)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('payment_method, COUNT(*) as total_transactions, SUM(total) as total_revenue')
            ->groupBy('payment_method')
            ->orderByDesc('total_revenue')
            ->get();

        $grandTotal = (float) $rows->sum('total_revenue');

        $salesByMethod = $rows->map(function ($item) use ($grandTotal) {
            return [
                'payment_method' => $item->payment_method,
                'total_transactions' => (int) $item->total_transactions,
                'total_revenue' => (float) $item->total_revenue,
                'percentage' => $grandTotal > 0 ? round(((float) $item->total_revenue / $grandTotal) * 100, 1) : 0.0,
            ];
        });

        return [
            'total_revenue' => $grandTotal,
            'sales_by_payment_method' => $salesByMethod,
        ];
    }

    /**
     * Estado del inventario: normal, stock bajo y agotados.
     */
    public function inventoryStatus(): array
    {
        $products = Product::where('is_active', true)
            ->get(['id', 'name', 'sku', 'stock', 'min_stock', 'category_id']);

        $outOfStock = $products->where('stock', '<=', 0)->sortBy('name')->values();
        $lowStock = $products
            ->filter(fn ($product) => $product->stock > 0 && $product->stock <= $product->effectiveMinStock())
            ->sortBy('stock')
            ->values();
        $normalCount = $products->count() - $outOfStock->count() - $lowStock->count();

        return [
            'summary' => [
                'normal' => $normalCount,
                'low_stock' => $lowStock->count(),
                'out_of_stock' => $outOfStock->count(),
            ],
            'low_stock_products' => $lowStock->take(15)->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => $product->stock,
                'min_stock' => $product->min_stock,
            ])->values(),
            'out_of_stock_products' => $outOfStock->take(15)->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => $product->stock,
                'min_stock' => $product->min_stock,
            ])->values(),
        ];
    }

    /**
     * Ventas por cajero en el período (los cajeros solo ven sus propios datos).
     */
    public function salesByCashier(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($request, 30);
        $scopedCashier = $this->scopedCashierId($request);

        $query = $this->completedSales($request)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('user_id, COUNT(*) as invoices, SUM(total) as total')
            ->groupBy('user_id')
            ->orderByDesc('total');

        $rows = $query->get();

        $users = User::whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return [
            'scoped' => $scopedCashier !== null,
            'cashiers' => $rows->map(function ($row) use ($users) {
                return [
                    'user_id' => $row->user_id,
                    'name' => $users->get($row->user_id)?->name ?? 'Desconocido',
                    'invoices' => (int) $row->invoices,
                    'total' => (float) $row->total,
                ];
            })->values(),
        ];
    }

    /**
     * Ventas agrupadas por hora del día.
     */
    public function salesByHour(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($request, 7);

        $hourExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', created_at) AS INTEGER)"
            : 'HOUR(created_at)';

        $rows = $this->completedSales($request)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw("{$hourExpression} as hour, COUNT(*) as invoices, SUM(total) as total")
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        $hours = collect(range(0, 23))->map(function ($hour) use ($rows) {
            $row = $rows->get($hour);

            return [
                'hour' => $hour,
                'total' => (float) ($row->total ?? 0),
                'invoices' => (int) ($row->invoices ?? 0),
            ];
        });

        return [
            'hours' => $hours,
        ];
    }

    /**
     * Alertas operativas: inventario, vencimientos y caja.
     */
    public function alerts(Request $request): array
    {
        $user = $request->user();
        $scopedCashier = $this->scopedCashierId($request);
        $today = now()->startOfDay();
        $expiryThreshold = now()->addDays(30)->endOfDay();

        $productColumns = ['id', 'name', 'sku', 'stock', 'min_stock', 'expiry_date'];

        $lowStock = SystemSettings::value('low_stock_alerts', true)
            ? Product::where('is_active', true)
                ->lowStock()
                ->orderBy('stock')
                ->limit(15)
                ->get($productColumns)
            : collect();

        $outOfStock = SystemSettings::value('notify_out_of_stock', true)
            ? Product::where('is_active', true)
                ->where('stock', '<=', 0)
                ->orderBy('name')
                ->limit(15)
                ->get($productColumns)
            : collect();

        $expiringSoon = Product::where('is_active', true)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', $today)
            ->where('expiry_date', '<=', $expiryThreshold)
            ->orderBy('expiry_date')
            ->limit(15)
            ->get(['id', 'name', 'sku', 'stock', 'expiry_date'])
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => $product->stock,
                'expiry_date' => $product->expiry_date->toDateString(),
                'days_left' => (int) $today->diffInDays($product->expiry_date),
            ]);

        $expired = Product::where('is_active', true)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->orderBy('expiry_date')
            ->limit(15)
            ->get(['id', 'name', 'sku', 'stock', 'expiry_date'])
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => $product->stock,
                'expiry_date' => $product->expiry_date->toDateString(),
                'days_expired' => (int) $product->expiry_date->diffInDays($today),
            ]);

        $openRegistersQuery = CashRegister::query()->open()->with('user:id,name')->latest('opened_at');
        if ($scopedCashier !== null) {
            $openRegistersQuery->where('user_id', $scopedCashier);
        }
        $openRegisters = $openRegistersQuery->limit(15)->get()->map(fn (CashRegister $register) => [
            'id' => $register->id,
            'name' => $register->name,
            'user' => $register->user?->name,
            'opened_at' => $register->opened_at,
            'total_sales' => (float) $register->total_sales,
        ]);

        $canViewDiscrepancies = $scopedCashier === null && $user->hasPermission('cash-registers.view');
        $discrepancies = $canViewDiscrepancies
            ? CashRegister::closed()
                ->with('user:id,name')
                ->where('difference', '!=', 0)
                ->latest('closed_at')
                ->limit(15)
                ->get()
                ->map(fn (CashRegister $register) => [
                    'id' => $register->id,
                    'name' => $register->name,
                    'user' => $register->user?->name,
                    'closed_at' => $register->closed_at,
                    'expected_closing' => (float) $register->expected_closing,
                    'actual_closing' => (float) $register->actual_closing,
                    'difference' => (float) $register->difference,
                ])
            : collect();

        return [
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'expiring_soon' => $expiringSoon,
            'expired' => $expired,
            'open_registers' => $openRegisters,
            'discrepancies' => $discrepancies,
            'can_view_discrepancies' => $canViewDiscrepancies,
        ];
    }

    private function completedSales(Request $request): Builder
    {
        $query = Sale::query()->where('status', 'completed');

        $scopedCashier = $this->scopedCashierId($request);
        if ($scopedCashier !== null) {
            $query->where('user_id', $scopedCashier);
        }

        return $query;
    }

    /**
     * @return array{series: array<int, array{date: string, total: float, invoices: int}>, total: float, invoices: int}
     */
    private function dailySeries(Request $request, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = $this->completedSales($request)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(created_at) as date, SUM(total) as total, COUNT(*) as invoices')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $series = [];
        $period = CarbonPeriod::create($dateFrom->copy()->startOfDay(), $dateTo->copy()->startOfDay());
        foreach ($period as $day) {
            $key = $day->toDateString();
            $row = $rows->get($key);
            $series[] = [
                'date' => $key,
                'total' => (float) ($row->total ?? 0),
                'invoices' => (int) ($row->invoices ?? 0),
            ];
        }

        return [
            'series' => $series,
            'total' => (float) collect($series)->sum('total'),
            'invoices' => (int) collect($series)->sum('invoices'),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request, int $defaultDays): array
    {
        try {
            $dateFrom = $request->filled('date_from')
                ? Carbon::parse($request->input('date_from'))->startOfDay()
                : now()->subDays($defaultDays - 1)->startOfDay();
            $dateTo = $request->filled('date_to')
                ? Carbon::parse($request->input('date_to'))->endOfDay()
                : now()->endOfDay();
        } catch (\Throwable) {
            $dateFrom = now()->subDays($defaultDays - 1)->startOfDay();
            $dateTo = now()->endOfDay();
        }

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        if ($dateFrom->diffInDays($dateTo) > 366) {
            $dateFrom = $dateTo->copy()->subDays(366)->startOfDay();
        }

        return [$dateFrom, $dateTo];
    }

    /**
     * Cajeros solo ven sus propios datos; admin/gerente ven todo.
     */
    private function scopedCashierId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->hasRole('cashier') && ! $user->hasRole('admin') && ! $user->hasRole('manager')) {
            return $user->id;
        }

        return null;
    }
}
