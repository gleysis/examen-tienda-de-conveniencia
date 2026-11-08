<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn (SystemSetting $setting) => Cache::forget("setting_{$setting->key}"));
        static::deleted(fn (SystemSetting $setting) => Cache::forget("setting_{$setting->key}"));
    }

    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            if (! $setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    public static function set(string $key, $value, string $type = 'string', string $group = 'general', ?string $description = null, bool $isPublic = false): void
    {
        self::updateOrCreate(
            ['key' => $key],
            [
                'value' => self::normalizeValue($value),
                'type' => $type,
                'group' => $group,
                'description' => $description,
                'is_public' => $isPublic,
            ]
        );

        Cache::forget("setting_{$key}");
    }

    /**
     * Convert a value to the string representation stored in the database.
     */
    public static function normalizeValue($value)
    {
        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value;
    }

    public static function castValue($value, string $type)
    {
        return match ($type) {
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $value,
            'float', 'decimal' => (float) $value,
            'array', 'json' => json_decode($value, true),
            default => $value,
        };
    }

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
