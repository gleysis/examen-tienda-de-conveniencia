<?php

namespace App\Policies;

use App\Models\CashRegister;
use App\Models\User;

class CashRegisterPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CashRegister $cashRegister): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CashRegister $cashRegister): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CashRegister $cashRegister): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CashRegister $cashRegister): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CashRegister $cashRegister): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can open the model.
     */
    public function open(User $user, CashRegister $cashRegister): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can close the model.
     */
    public function close(User $user, CashRegister $cashRegister): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager') || $user->hasRole('cashier');
    }
}
