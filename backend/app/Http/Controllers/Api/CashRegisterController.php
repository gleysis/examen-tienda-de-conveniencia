<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashRegisterRequest;
use App\Http\Requests\OpenCashRegisterRequest;
use App\Http\Requests\StoreCashRegisterRequest;
use App\Http\Requests\UpdateCashRegisterRequest;
use App\Http\Resources\CashRegisterResource;
use App\Models\CashRegister;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashRegisterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CashRegister::with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $cashRegisters = $query->latest()->paginate($request->integer('per_page', 15));

        return CashRegisterResource::collection($cashRegisters);
    }

    public function store(StoreCashRegisterRequest $request): array
    {
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
            'cash_register' => new CashRegisterResource($cashRegister),
            'message' => 'Caja registradora creada exitosamente',
        ];
    }

    public function show(CashRegister $cashRegister): array
    {
        $cashRegister->load(['user', 'sales']);

        return [
            'cash_register' => new CashRegisterResource($cashRegister),
        ];
    }

    public function update(UpdateCashRegisterRequest $request, CashRegister $cashRegister): array
    {
        $cashRegister->update($request->validated());

        return [
            'cash_register' => new CashRegisterResource($cashRegister),
            'message' => 'Caja registradora actualizada exitosamente',
        ];
    }

    public function open(OpenCashRegisterRequest $request, CashRegister $cashRegister): array
    {
        if ($cashRegister->status === 'open') {
            abort(422, 'Esta caja registradora ya está abierta');
        }

        $cashRegister->open($request->opening_balance, auth()->id());

        return [
            'cash_register' => new CashRegisterResource($cashRegister->fresh()),
            'message' => 'Caja registradora abierta exitosamente',
        ];
    }

    public function close(CloseCashRegisterRequest $request, CashRegister $cashRegister): array
    {
        if ($cashRegister->status === 'closed') {
            abort(422, 'Esta caja registradora ya está cerrada');
        }

        $cashRegister->close($request->actual_closing);

        return [
            'cash_register' => new CashRegisterResource($cashRegister->fresh()),
            'message' => 'Caja registradora cerrada exitosamente',
        ];
    }

    public function destroy(CashRegister $cashRegister): array
    {
        if ($cashRegister->status === 'open') {
            abort(422, 'No se puede eliminar una caja registradora abierta');
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
            'cash_register' => new CashRegisterResource($cashRegister),
        ];
    }
}
