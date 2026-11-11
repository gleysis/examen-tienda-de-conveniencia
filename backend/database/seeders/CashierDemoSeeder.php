<?php

namespace Database\Seeders;

use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Seeder;

class CashierDemoSeeder extends Seeder
{
    public function run(): void
    {
        $ana = User::find(8);
        $carlos = User::find(9);
        $lucia = User::find(6);

        if (! $ana || ! $carlos || ! $lucia) {
            return;
        }

        $sales = Sale::orderBy('created_at')->get();
        $cashiers = [$ana, $carlos, $lucia];

        $sales->each(function (Sale $sale, int $index) use ($cashiers) {
            $sale->update([
                'user_id' => $cashiers[$index % count($cashiers)]->id,
                'created_at' => now()->subDays(5 - intdiv($index, 5))->setTime(9 + ($index % 8), ($index * 17) % 60),
            ]);
        });

        $this->closedSession($ana, 'Caja 1', -50, [
            ['type' => 'withdrawal', 'amount' => 500, 'reason' => 'Pago a proveedor'],
            ['type' => 'expense', 'amount' => 120, 'reason' => 'Compra de bolsas y papel'],
        ]);

        $this->closedSession($carlos, 'Caja 2', 25, [
            ['type' => 'income', 'amount' => 200, 'reason' => 'Fondo adicional autorizado'],
        ]);

        $this->openSession($lucia, 'Caja 3', [
            ['type' => 'withdrawal', 'amount' => 100, 'reason' => 'Retiro parcial de efectivo'],
        ]);

        $cancelled = Sale::where('user_id', $carlos->id)->latest('created_at')->first();
        $cancelled?->update(['status' => 'cancelled', 'notes' => 'Anulada por error en el cobro']);
    }

    /**
     * @param  array<int, array{type: string, amount: float, reason: string}>  $movements
     */
    private function closedSession(User $cashier, string $name, float $difference, array $movements): void
    {
        $sales = Sale::where('user_id', $cashier->id)->where('status', 'completed')->get();

        $register = CashRegister::create([
            'name' => $name,
            'location' => 'Mostrador principal',
            'user_id' => $cashier->id,
            'status' => 'closed',
        ]);

        $session = $register->sessions()->create([
            'user_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'closed_by' => $cashier->id,
            'opening_balance' => 1000,
            'status' => 'closed',
            'opened_at' => $sales->min('created_at')?->subHour() ?? now()->subDay(),
        ]);

        Sale::where('user_id', $cashier->id)->update([
            'cash_register_id' => $register->id,
            'cash_register_session_id' => $session->id,
        ]);

        foreach ($movements as $movement) {
            $session->movements()->create([
                'cash_register_id' => $register->id,
                'user_id' => $cashier->id,
                'type' => $movement['type'],
                'amount' => $movement['amount'],
                'reason' => $movement['reason'],
                'created_at' => $session->opened_at->copy()->addHours(4),
            ]);
        }

        $cashSales = (float) $sales->where('payment_method', 'cash')->sum('total');
        $cashOut = collect($movements)->where('type', '!=', 'income')->sum('amount');
        $cashIn = collect($movements)->where('type', 'income')->sum('amount');
        $expected = 1000 + $cashSales + $cashIn - $cashOut;
        $closedAt = $sales->max('created_at')?->addHours(2) ?? now()->subDay();

        $session->update([
            'cash_sales' => $cashSales,
            'card_sales' => (float) $sales->where('payment_method', 'card')->sum('total'),
            'transfer_sales' => (float) $sales->where('payment_method', 'transfer')->sum('total'),
            'total_sales' => (float) $sales->sum('total'),
            'expected_closing' => $expected,
            'closed_at' => $closedAt,
            'notes' => $difference < 0 ? 'Faltante reportado al administrador' : null,
        ]);

        $session->cashCount()->create([
            'user_id' => $cashier->id,
            'expected_amount' => $expected,
            'counted_amount' => $expected + $difference,
            'difference' => $difference,
            'type' => 'closing',
            'notes' => $difference < 0 ? 'Faltante reportado al administrador' : null,
            'created_at' => $closedAt,
            'updated_at' => $closedAt,
        ]);
    }

    /**
     * @param  array<int, array{type: string, amount: float, reason: string}>  $movements
     */
    private function openSession(User $cashier, string $name, array $movements): void
    {
        $register = CashRegister::create([
            'name' => $name,
            'location' => 'Mostrador principal',
            'user_id' => $cashier->id,
            'status' => 'open',
        ]);

        $session = $register->sessions()->create([
            'user_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 800,
            'status' => 'open',
            'opened_at' => now()->setTime(8, 0),
        ]);

        foreach ($movements as $movement) {
            $session->movements()->create([
                'cash_register_id' => $register->id,
                'user_id' => $cashier->id,
                'type' => $movement['type'],
                'amount' => $movement['amount'],
                'reason' => $movement['reason'],
            ]);
        }

        $cashOut = collect($movements)->where('type', '!=', 'income')->sum('amount');
        $cashIn = collect($movements)->where('type', 'income')->sum('amount');

        $session->update(['expected_closing' => 800 + $cashIn - $cashOut]);
    }
}
