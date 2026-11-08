<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashMovementController extends Controller
{
    /**
     * Registra una entrada o salida de efectivo en la caja abierta del usuario.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:income,withdrawal,expense',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'cash_register_id' => 'nullable|exists:cash_registers,id',
        ]);

        $register = isset($validated['cash_register_id'])
            ? CashRegister::findOrFail($validated['cash_register_id'])
            : CashRegister::where('user_id', $request->user()->id)->where('status', 'open')->latest('opened_at')->first();

        if ($register && $request->user()->isLimitedToOwnRecords() && $register->user_id !== $request->user()->id) {
            abort(403, 'Solo puedes registrar movimientos en tu propia caja.');
        }

        if (! $register || $register->status !== 'open') {
            return response()->json([
                'message' => 'No hay una caja abierta para registrar el movimiento.',
            ], 422);
        }

        $movement = $register->movements()->create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'reason' => $validated['reason'],
        ]);

        $register->addMovement($movement->type, (float) $movement->amount);

        return response()->json([
            'movement' => $movement->load(['user:id,name', 'cashRegister:id,name']),
            'expected_closing' => $register->expected_closing,
        ], 201);
    }
}
