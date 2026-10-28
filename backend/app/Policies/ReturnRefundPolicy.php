<?php

namespace App\Policies;

use App\Models\ReturnRefund;
use App\Models\User;

class ReturnRefundPolicy
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
    public function view(User $user, ReturnRefund $returnRefund): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReturnRefund $returnRefund): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReturnRefund $returnRefund): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ReturnRefund $returnRefund): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ReturnRefund $returnRefund): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can process the model.
     */
    public function process(User $user, ReturnRefund $returnRefund): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager');
    }
}
