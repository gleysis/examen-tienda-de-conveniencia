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
}
