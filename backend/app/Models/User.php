<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('slug', $role);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles
            ->loadMissing('permissions')
            ->contains(fn (Role $role) => $role->permissions->contains('slug', $permission));
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return $this->roles
            ->loadMissing('permissions')
            ->contains(fn (Role $role) => $role->permissions->whereIn('slug', $permissions)->isNotEmpty());
    }

    /**
     * Cashiers without a supervising role may only see and operate their own
     * sales, returns and cash registers.
     */
    public function isLimitedToOwnRecords(): bool
    {
        return $this->hasRole('cashier') && ! $this->hasRole('admin') && ! $this->hasRole('manager');
    }

    /**
     * Slugs of every permission granted to the user through their roles.
     *
     * @return Collection<int, string>
     */
    public function permissionSlugs(): Collection
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values();
    }

    /**
     * Whether the user already holds every given permission, so granting or
     * managing them cannot escalate the user's own privileges.
     *
     * @param  iterable<int, string>  $permissionSlugs
     */
    public function holdsAllPermissions(iterable $permissionSlugs): bool
    {
        return collect($permissionSlugs)->diff($this->permissionSlugs())->isEmpty();
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * The cash registers permanently assigned to this cashier.
     */
    public function cashRegisters()
    {
        return $this->hasMany(CashRegister::class);
    }

    /**
     * Every session this user has been responsible for.
     */
    public function cashRegisterSessions()
    {
        return $this->hasMany(CashRegisterSession::class);
    }

    /**
     * The session this user is currently responsible for, if any.
     */
    public function openCashRegisterSession()
    {
        return $this->hasOne(CashRegisterSession::class)->where('status', 'open');
    }

    /**
     * Every cashier owns a closed register from the moment the role is
     * granted, so they can always start a shift without manager setup.
     */
    public function ensureCashRegister(): ?CashRegister
    {
        if (! $this->hasRole('cashier')) {
            return null;
        }

        return DB::transaction(function (): CashRegister {
            self::whereKey($this->id)->lockForUpdate()->first();

            $register = $this->cashRegisters()->first();

            if ($register) {
                return $register;
            }

            $register = $this->cashRegisters()->create(['name' => 'Caja', 'status' => 'closed']);
            $register->update(['name' => 'Caja '.$register->id]);

            return $register;
        });
    }

    /**
     * Assigns a register when the cashier role is present in the given
     * role ids; used after creating users or syncing their roles.
     *
     * @param  iterable<int, int>|null  $roleIds
     */
    public function assignCashRegisterIfCashier(?iterable $roleIds): void
    {
        if ($roleIds === null) {
            return;
        }

        $cashierRoleId = Role::where('slug', 'cashier')->value('id');

        if ($cashierRoleId !== null && in_array($cashierRoleId, collect($roleIds)->all())) {
            $this->ensureCashRegister();
        }
    }
}
