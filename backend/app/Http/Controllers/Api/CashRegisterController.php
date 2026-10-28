<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    public function index(Request $request): array
    {
        $query = CashRegister::with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $cashRegisters = $query->latest()->get();

        return [
            'cash_registers' => $cashRegisters,
        ];
    }

    public function store(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'opening_balance' => 'required|numeric|min:0',
        ]);

        $cashRegister = CashRegister::create([
            'name' => $request->name,
            'location' => $request->location,
            'opening_balance' => $request->opening_balance,
            'current_balance' => $request->opening_balance,
            'expected_closing' => $request->opening_balance,
            'status' => 'open',
            'user_id' => auth()->id(),
            'opened_at' => now(),
        ]);

        return [
            'cash_register' => $cashRegister,
            'message' => 'Caja registradora creada exitosamente',
        ];
    }

    public function show(CashRegister $cashRegister): array
    {
        $cashRegister->load(['user', 'sales']);

        return [
            'cash_register' => $cashRegister,
        ];
    }

    public function update(Request $request, CashRegister $cashRegister): array
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $cashRegister->update($request->all());

        return [
            'cash_register' => $cashRegister,
            'message' => 'Caja registradora actualizada exitosamente',
        ];
    }

    public function open(Request $request, CashRegister $cashRegister): array
    {
        if ($cashRegister->status === 'open') {
            return [
                'error' => 'Esta caja registradora ya está abierta',
            ];
        }

        $request->validate([
            'opening_balance' => 'required|numeric|min:0',
        ]);

        $cashRegister->open($request->opening_balance, auth()->id());

        return [
            'cash_register' => $cashRegister->fresh(),
            'message' => 'Caja registradora abierta exitosamente',
        ];
    }

    public function close(Request $request, CashRegister $cashRegister): array
    {
        if ($cashRegister->status === 'closed') {
            return [
                'error' => 'Esta caja registradora ya está cerrada',
            ];
        }

        $request->validate([
            'actual_closing' => 'required|numeric|min:0',
        ]);

        $cashRegister->close($request->actual_closing);

        return [
            'cash_register' => $cashRegister->fresh(),
            'message' => 'Caja registradora cerrada exitosamente',
        ];
    }

    public function destroy(CashRegister $cashRegister): array
    {
        if ($cashRegister->status === 'open') {
            return [
                'error' => 'No se puede eliminar una caja registradora abierta',
            ];
        }

        $cashRegister->delete();

        return [
            'message' => 'Caja registradora eliminada exitosamente',
        ];
    }

    public function getCurrent(): array
    {
        $cashRegister = CashRegister::where('user_id', auth()->id())
            ->where('status', 'open')
            ->first();

        if (! $cashRegister) {
            return [
                'cash_register' => null,
                'message' => 'No hay caja registradora abierta para el usuario actual',
            ];
        }

        return [
            'cash_register' => $cashRegister,
        ];
    }
}
