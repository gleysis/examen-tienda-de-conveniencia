<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\ReturnRefund;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashierReportController extends Controller
{
    /**
     * Resumen general para la pestaña Ventas: serie diaria, ventas por
     * categoría (netas de devoluciones procesadas), producto más vendido
     * y totales del período.
     */
    public function salesOverview(Request $request): array
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->subDays(29)->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();
        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }
        $dateFrom = $dateFrom->copy()->max($dateTo->copy()->subDays(399));

        $scopedCashier = $this->scopedCashierId($request);
        $userId = $scopedCashier ?? ($request->filled('user_id') ? (int) $request->user_id : null);

        $byDay = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId))
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('SUM(total) as total')
            ->selectRaw('COUNT(*) as invoices')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $daily = [];
        for ($day = $dateFrom->copy(); $day->lte($dateTo); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $daily[] = [
                'date' => $key,
                'total' => round((float) ($byDay[$key]->total ?? 0), 2),
                'invoices' => (int) ($byDay[$key]->invoices ?? 0),
            ];
        }

        $refunds = ReturnRefund::select('sale_item_id', DB::raw('SUM(refund_amount) as refunded'))
            ->where('status', 'processed')
            ->groupBy('sale_item_id');

        $itemBase = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoinSub($refunds, 'refunds', 'refunds.sale_item_id', '=', 'sale_items.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$dateFrom, $dateTo])
            ->when($userId, fn (Builder $q) => $q->where('sales.user_id', $userId));

        $byCategory = (clone $itemBase)
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw("COALESCE(categories.name, 'Sin categoría') as category")
            ->selectRaw('SUM(sale_items.subtotal - COALESCE(refunds.refunded, 0)) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['name' => $row->category, 'total' => round((float) $row->total, 2)])
            ->filter(fn ($row) => $row['total'] > 0)
            ->values();

        $categoryTotal = $byCategory->sum('total');
        $byCategory = $byCategory->map(fn ($row) => $row + [
            'percentage' => $categoryTotal > 0 ? round($row['total'] / $categoryTotal * 100, 1) : 0,
        ]);

        $topProduct = (clone $itemBase)
            ->selectRaw('products.name as name')
            ->selectRaw('SUM(sale_items.quantity) as quantity')
            ->selectRaw('SUM(sale_items.subtotal - COALESCE(refunds.refunded, 0)) as total')
            ->groupBy('sale_items.product_id', 'products.name')
            ->orderByDesc('quantity')
            ->first();

        return [
            'daily' => $daily,
            'by_category' => $byCategory,
            'top_product' => $topProduct ? [
                'name' => $topProduct->name,
                'quantity' => (int) $topProduct->quantity,
                'total' => round((float) $topProduct->total, 2),
            ] : null,
            'summary' => [
                'total' => round(collect($daily)->sum('total'), 2),
                'transactions' => collect($daily)->sum('invoices'),
            ],
        ];
    }

    /**
     * 1. Ventas por cajero.
     */
    public function sales(Request $request): array
    {
        $query = Sale::with(['user:id,name'])
            ->withCount('items')
            ->latest('created_at');

        $this->applySaleFilters($query, $request);

        $sales = $query->limit(500)->get();

        return [
            'sales' => $sales,
            'summary' => [
                'count' => $sales->count(),
                'subtotal' => $sales->sum('subtotal'),
                'tax' => $sales->sum('tax'),
                'discount' => $sales->sum('discount'),
                'total' => $sales->sum('total'),
            ],
        ];
    }

    /**
     * 4. Turnos de cajero (cajas abiertas/cerradas).
     */
    public function shifts(Request $request): array
    {
        $query = CashRegister::with('user:id,name')->latest('opened_at');

        $this->applyRegisterFilters($query, $request);

        $shifts = $query->limit(200)->get()->map(function (CashRegister $register) {
            $register->worked_minutes = $register->opened_at
                ? max(0, (int) $register->opened_at->diffInMinutes($register->closed_at ?? now()))
                : null;

            return $register;
        });

        return [
            'shifts' => $shifts,
            'summary' => [
                'total' => $shifts->count(),
                'open' => $shifts->where('status', 'open')->count(),
                'closed' => $shifts->where('status', 'closed')->count(),
                'total_sold' => $shifts->sum('total_sales'),
            ],
        ];
    }

    /**
     * 2. Cierre de caja: fondo, efectivo, retiros, esperado, contado y diferencia.
     */
    public function closures(Request $request): array
    {
        $query = CashRegister::with('user:id,name')->closed()->latest('closed_at');

        $this->applyRegisterFilters($query, $request);

        $closures = $query->limit(200)->get()->map(function (CashRegister $register) {
            $register->cash_in = (float) $register->movements()->where('type', CashMovement::TYPE_INCOME)->sum('amount');
            $register->cash_out = (float) $register->movements()
                ->whereIn('type', [CashMovement::TYPE_WITHDRAWAL, CashMovement::TYPE_EXPENSE])
                ->sum('amount');

            return $register;
        });

        return [
            'closures' => $closures,
            'summary' => [
                'total' => $closures->count(),
                'cash_sales' => $closures->sum('cash_sales'),
                'difference' => $closures->sum('difference'),
            ],
        ];
    }

    /**
     * 6. Faltantes y sobrantes de caja.
     */
    public function discrepancies(Request $request): array
    {
        $query = CashRegister::with('user:id,name')->closed()
            ->where('difference', '!=', 0)
            ->latest('closed_at');

        $this->applyRegisterFilters($query, $request);

        $discrepancies = $query->limit(200)->get();

        return [
            'discrepancies' => $discrepancies,
            'summary' => [
                'total' => $discrepancies->count(),
                'shortages' => $discrepancies->where('difference', '<', 0)->count(),
                'overages' => $discrepancies->where('difference', '>', 0)->count(),
                'net_difference' => $discrepancies->sum('difference'),
            ],
        ];
    }

    /**
     * 5. Facturas anuladas y devoluciones.
     */
    public function voided(Request $request): array
    {
        $salesQuery = Sale::with(['user:id,name'])
            ->withCount('items')
            ->where('status', '!=', 'completed')
            ->latest('created_at');

        $returnsQuery = ReturnRefund::with(['user:id,name', 'sale:id,invoice_number', 'product:id,name'])
            ->latest('created_at');

        $this->applySaleFilters($salesQuery, $request);
        $this->applyDateFilters($returnsQuery, $request);
        $scopedCashier = $this->scopedCashierId($request);
        if ($scopedCashier !== null) {
            $returnsQuery->where('user_id', $scopedCashier);
        } elseif ($request->filled('user_id')) {
            $returnsQuery->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $returnsQuery->where('status', $request->status);
        }

        $voidedSales = $salesQuery->limit(250)->get();
        $returns = $returnsQuery->limit(250)->get();

        return [
            'voided_sales' => $voidedSales,
            'returns' => $returns,
            'summary' => [
                'voided_count' => $voidedSales->count(),
                'voided_total' => $voidedSales->sum('total'),
                'returns_count' => $returns->count(),
                'returns_total' => $returns->sum('refund_amount'),
            ],
        ];
    }

    /**
     * 8. Resumen diario de ventas.
     */
    public function dailySummary(Request $request): array
    {
        $query = Sale::with('user:id,name')->latest('created_at');
        $this->applySaleFilters($query, $request);

        $byDay = $query->limit(2000)->get()
            ->groupBy(fn (Sale $sale) => $sale->created_at->format('Y-m-d'))
            ->map(fn ($sales, string $day) => [
                'date' => $day,
                'invoices' => $sales->count(),
                'subtotal' => $sales->sum('subtotal'),
                'discount' => $sales->sum('discount'),
                'tax' => $sales->sum('tax'),
                'total' => $sales->sum('total'),
                'cancelled' => $sales->where('status', '!=', 'completed')->count(),
                'net' => $sales->where('status', 'completed')->sum('total'),
                'cash' => $sales->where('payment_method', 'cash')->sum('total'),
                'card' => $sales->where('payment_method', 'card')->sum('total'),
                'transfer' => $sales->where('payment_method', 'transfer')->sum('total'),
            ])
            ->sortByDesc('date')
            ->values();

        return [
            'days' => $byDay,
            'summary' => [
                'days_count' => $byDay->count(),
                'invoices' => $byDay->sum('invoices'),
                'total' => $byDay->sum('total'),
                'net' => $byDay->sum('net'),
            ],
        ];
    }

    /**
     * 9. Productos vendidos por cajero.
     */
    public function products(Request $request): array
    {
        $items = SaleItem::with(['product:id,name,sku,price', 'sale:id,user_id', 'sale.user:id,name'])
            ->whereHas('sale', function (Builder $q) use ($request) {
                $this->applySaleFilters($q, $request);
                $q->where('status', 'completed');
            })
            ->limit(3000)
            ->get();

        $grouped = $items
            ->groupBy(fn (SaleItem $item) => $item->product_id.'|'.($item->sale?->user_id ?? 0))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'product' => $first->product?->name,
                    'sku' => $first->product?->sku,
                    'cashier' => $first->sale?->user?->name,
                    'quantity' => $group->sum('quantity'),
                    'unit_price' => $first->unit_price,
                    'discount' => $group->sum('discount'),
                    'total' => $group->sum(fn (SaleItem $i) => $i->quantity * $i->unit_price),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return [
            'items' => $grouped,
            'summary' => [
                'products' => $grouped->count(),
                'units' => $grouped->sum('quantity'),
                'total' => $grouped->sum('total'),
            ],
        ];
    }

    /**
     * 7. Entradas y salidas de efectivo.
     */
    public function cashMovements(Request $request): array
    {
        $query = CashMovement::with(['user:id,name', 'cashRegister:id,name'])
            ->latest('created_at');

        $this->applyDateFilters($query, $request);
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        $scopedCashier = $this->scopedCashierId($request);
        if ($scopedCashier !== null) {
            $query->where('user_id', $scopedCashier);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $movements = $query->limit(500)->get();

        return [
            'movements' => $movements,
            'summary' => [
                'total' => $movements->count(),
                'income' => $movements->where('type', CashMovement::TYPE_INCOME)->sum('amount'),
                'out' => $movements->where('type', '!=', CashMovement::TYPE_INCOME)->sum('amount'),
            ],
        ];
    }

    /**
     * Cajeros disponibles para los filtros.
     */
    public function cashiers(Request $request): array
    {
        $scopedCashier = $this->scopedCashierId($request);
        if ($scopedCashier !== null) {
            return [
                'cashiers' => User::where('id', $scopedCashier)->get(['id', 'name']),
            ];
        }

        $ids = Sale::query()->select('user_id')->distinct()->pluck('user_id')
            ->merge(CashRegister::query()->select('user_id')->distinct()->pluck('user_id'))
            ->unique()
            ->filter();

        return [
            'cashiers' => User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Cajeros solo ven sus propios datos; admin/gerente ven todo.
     */
    private function scopedCashierId(Request $request): ?int
    {
        return $request->user()->isLimitedToOwnRecords() ? $request->user()->id : null;
    }

    private function applySaleFilters(Builder $query, Request $request): void
    {
        $this->applyDateFilters($query, $request);

        $scopedCashier = $this->scopedCashierId($request);
        if ($scopedCashier !== null) {
            $query->where('user_id', $scopedCashier);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('invoice')) {
            $query->where('invoice_number', 'like', '%'.$request->invoice.'%');
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', $request->cash_register_id);
        }
    }

    private function applyRegisterFilters(Builder $query, Request $request): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate('opened_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('opened_at', '<=', $request->date_to);
        }
        $scopedCashier = $this->scopedCashierId($request);
        if ($scopedCashier !== null) {
            $query->where('user_id', $scopedCashier);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    }

    private function applyDateFilters(Builder $query, Request $request): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
    }
}
