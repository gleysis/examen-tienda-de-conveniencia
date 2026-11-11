<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashMovementController extends Controller
{
    /**
     * Registra una entrada o salida de efectivo en la sesión de caja abierta.
     * Un cajero siempre registra en su propia sesión; un gerente puede
     * indicar la caja destino.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:income,withdrawal,expense',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'cash_register_id' => 'nullable|exists:cash_registers,id',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            $session = isset($validated['cash_register_id'])
                ? CashRegister::findOrFail($validated['cash_register_id'])->openSession()->lockForUpdate()->first()
                : CashRegisterSession::where('user_id', $request->user()->id)->open()->lockForUpdate()->first();

            if ($session && $request->user()->isLimitedToOwnRecords() && $session->user_id !== $request->user()->id) {
                abort(403, 'Solo puedes registrar movimientos en tu propia caja.');
            }

            if (! $session) {
                return response()->json([
                    'message' => 'No hay una caja abierta para registrar el movimiento.',
                ], 422);
            }

            if ($validated['type'] !== CashMovement::TYPE_INCOME && (float) $validated['amount'] > $session->expected_closing) {
                return response()->json([
                    'message' => 'El monto supera el efectivo esperado en caja.',
                ], 422);
            }

            $movement = $session->movements()->create([
                'cash_register_id' => $session->cash_register_id,
                'user_id' => $request->user()->id,
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'reason' => $validated['reason'],
            ]);

            $session->addMovement($movement->type, (float) $movement->amount);

            return response()->json([
                'movement' => $movement->load(['user:id,name', 'cashRegister:id,name']),
                'expected_closing' => $session->fresh()->expected_closing,
            ], 201);
        });
    }
}
