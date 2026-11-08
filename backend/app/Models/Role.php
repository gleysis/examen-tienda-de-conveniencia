<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

#[Hidden(['pivot'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * Roles referenced by slug in code; their slug cannot change and they cannot be deleted.
     */
    public const SYSTEM_SLUGS = ['admin', 'manager', 'cashier', 'warehouse'];

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Slugs of every permission granted by the given roles.
     *
     * @return Collection<int, string>
     */
    public static function permissionSlugsFor(mixed $roleIds): Collection
    {
        $roleIds = collect(is_array($roleIds) ? $roleIds : [])->filter(fn ($id) => is_numeric($id));

        return Permission::whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
            ->pluck('slug');
    }

    public function isSystemRole(): bool
    {
        return in_array($this->getOriginal('slug'), self::SYSTEM_SLUGS, true);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('slug', $permission)->exists();
    }
}
