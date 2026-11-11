<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashRegisterRequest;
use App\Http\Requests\OpenCashRegisterRequest;
use App\Http\Requests\StoreCashRegisterRequest;
use App\Http\Requests\UpdateCashRegisterRequest;
use App\Http\Resources\CashRegisterResource;
use App\Http\Resources\CashRegisterSessionResource;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CashRegister::with(['user:id,name', 'openSession.user:id,name', 'openSession.openedBy:id,name']);

        if ($request->user()->isLimitedToOwnRecords()) {
            $query->where('user_id', $request->user()->id);
        } else {
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->integer('user_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
        }

        $cashRegisters = $query->latest()->paginate($request->integer('per_page', 15));

        return CashRegisterResource::collection($cashRegisters);
    }

    /**
     * Creates a physical drawer. It starts closed with no float; a session
     * must be opened before it can be used.
     */
    public function store(StoreCashRegisterRequest $request): array
    {
        $cashRegister = CashRegister::create([
            'name' => $request->name,
            'location' => $request->location,
            'user_id' => $request->user_id,
            'status' => 'closed',
        ]);

        return [
            'cash_register' => new CashRegisterResource($cashRegister->load('user')),
            'message' => 'Caja registradora creada exitosamente',
        ];
    }

    public function show(Request $request, CashRegister $cashRegister): array
    {
        abort_if($request->user()->isLimitedToOwnRecords() && $cashRegister->user_id !== $request->user()->id, 404);

        $cashRegister->load([
            'user:id,name',
            'openSession.user:id,name',
            'openSession.cashCount',
        ]);

        return [
            'cash_register' => new CashRegisterResource($cashRegister),
        ];
    }

    public function update(UpdateCashRegisterRequest $request, CashRegister $cashRegister): array
    {
        if ($request->filled('user_id')) {
            abort_unless(
                User::findOrFail($request->integer('user_id'))->hasRole('cashier'),
                422,
                'Solo un cajero puede ser responsable de una caja.'
            );
        }

        $cashRegister->update($request->validated());

        return [
            'cash_register' => new CashRegisterResource($cashRegister->load('user')),
            'message' => 'Caja registradora actualizada exitosamente',
        ];
    }

    /**
     * Opens a new session. A cashier always opens for themselves on their own
     * drawer; a manager may choose the responsible cashier. The register row
     * and the responsible user are locked so two sessions can never start at
     * once for the same drawer or the same cashier.
     */
    public function open(OpenCashRegisterRequest $request, CashRegister $cashRegister): array
    {
        return DB::transaction(function () use ($request, $cashRegister) {
            $register = CashRegister::lockForUpdate()->findOrFail($cashRegister->id);
            $actor = $request->user();

            if ($actor->isLimitedToOwnRecords()) {
                abort_if($register->user_id !== $actor->id, 403, 'Solo puedes abrir tu propia caja.');
                abort_if($request->filled('user_id') && $request->integer('user_id') !== $actor->id, 403, 'Solo puedes abrir cajas a tu nombre.');
                $responsible = $actor;
            } else {
                $responsible = $request->filled('user_id')
                    ? User::findOrFail($request->integer('user_id'))
                    : ($register->user ?? $actor);

                abort_unless(
                    $responsible->is($actor) || $responsible->hasPermission('sales.create'),
                    422,
                    'El usuario seleccionado no puede responsabilizarse de una caja.'
                );
            }

            if ($register->status === 'open' || $register->openSession()->exists()) {
                abort(422, 'Esta caja registradora ya está abierta');
            }

            $responsible = User::lockForUpdate()->findOrFail($responsible->id);

            if ($responsible->openCashRegisterSession()->exists()) {
                abort(422, 'El cajero seleccionado ya tiene una caja abierta. Debe cerrarla antes de abrir otra.');
            }

            $session = $register->openSessionFor($responsible, $actor, (float) $request->opening_balance);

            return [
                'cash_register' => new CashRegisterResource($register->fresh(['user', 'openSession.user', 'openSession.openedBy'])),
                'session' => new CashRegisterSessionResource($session),
                'message' => 'Caja registradora abierta exitosamente',
            ];
        });
    }

    /**
     * Closing arqueo. The session row is locked so a sale in progress cannot
     * land on the drawer after its totals have been counted.
     */
    public function close(CloseCashRegisterRequest $request, CashRegister $cashRegister): array
    {
        return DB::transaction(function () use ($request, $cashRegister) {
            $session = $cashRegister->openSession()->lockForUpdate()->first();

            if (! $session) {
                abort(422, 'Esta caja registradora ya está cerrada');
            }

            if ($request->user()->isLimitedToOwnRecords() && $session->user_id !== $request->user()->id) {
                abort(403, 'Solo puedes cerrar tu propia caja.');
            }

            $session->close((float) $request->actual_closing, $request->user()->id);

            $cashRegister = $cashRegister->fresh([
                'user:id,name',
                'openSession',
            ]);

            return [
                'cash_register' => new CashRegisterResource($cashRegister),
                'session' => new CashRegisterSessionResource(
                    $session->fresh(['user:id,name', 'openedBy:id,name', 'closedBy:id,name', 'cashCount'])
                ),
                'message' => 'Caja registradora cerrada exitosamente',
            ];
        });
    }

    /**
     * Session history (shifts). Cashiers only see their own.
     */
    public function sessions(Request $request): AnonymousResourceCollection
    {
        $query = CashRegisterSession::with([
            'cashRegister:id,name',
            'user:id,name',
            'openedBy:id,name',
            'closedBy:id,name',
            'cashCount',
        ])->latest('opened_at');

        if ($request->user()->isLimitedToOwnRecords()) {
            $query->where('user_id', $request->user()->id);
        } else {
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->integer('user_id'));
            }
            if ($request->filled('cash_register_id')) {
                $query->where('cash_register_id', $request->integer('cash_register_id'));
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('opened_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('opened_at', '<=', $request->date_to);
        }

        return CashRegisterSessionResource::collection(
            $query->paginate($request->integer('per_page', 15))
        );
    }

    /**
     * Movements recorded inside one session.
     */
    public function sessionMovements(Request $request, CashRegisterSession $session): array
    {
        abort_if($request->user()->isLimitedToOwnRecords() && $session->user_id !== $request->user()->id, 404);

        return [
            'movements' => $session->movements()->with('user:id,name')->latest('created_at')->get(),
        ];
    }

    /**
     * Cashiers that a supervisor can make responsible for a session.
     */
    public function responsibleUsers(): array
    {
        $users = User::query()
            ->select(['id', 'name'])
            ->whereHas('roles', fn ($q) => $q->where('slug', 'cashier'))
            ->withExists('openCashRegisterSession')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'has_open_session' => (bool) $user->open_cash_register_session_exists,
            ]);

        return ['users' => $users];
    }

    public function destroy(CashRegister $cashRegister): array
    {
        if ($cashRegister->status === 'open') {
            abort(422, 'No se puede eliminar una caja registradora abierta');
        }

        if ($cashRegister->sessions()->exists()) {
            abort(422, 'No se puede eliminar una caja con historial de sesiones');
        }

        $cashRegister->delete();

        return [
            'message' => 'Caja registradora eliminada exitosamente',
        ];
    }

    /**
     * The drawer assigned to the current user plus its live session, so the
     * POS can tell whether they may sell.
     */
    public function getCurrent(): array
    {
        $cashRegister = CashRegister::with([
            'user:id,name',
            'openSession.user:id,name',
            'openSession.openedBy:id,name',
            'openSession.cashCount',
        ])
            ->whereHas('openSession', fn ($q) => $q->where('user_id', auth()->id()))
            ->first();

        $cashRegister ??= CashRegister::where('user_id', auth()->id())->first();

        if (! $cashRegister) {
            return [
                'cash_register' => null,
                'message' => 'No hay caja registradora asignada al usuario actual',
            ];
        }

        return [
            'cash_register' => new CashRegisterResource($cashRegister),
        ];
    }
}
