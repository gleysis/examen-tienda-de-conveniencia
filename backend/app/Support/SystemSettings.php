<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\SystemSettingChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SystemSettings
{
    /**
     * Return the settings catalog grouped as defined in config.
     *
     * @return array<string, array{label: string, description: string, fields: array<string, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return config('system_settings.groups', []);
    }

    /**
     * All setting keys managed by the catalog.
     *
     * @return list<string>
     */
    public static function catalogKeys(): array
    {
        return collect(self::groups())
            ->flatMap(fn (array $group) => array_keys($group['fields']))
            ->values()
            ->all();
    }

    /**
     * Whether the given key is managed by the catalog.
     */
    public static function isCatalogKey(string $key): bool
    {
        return self::field($key) !== null;
    }

    /**
     * Locate a field definition and its group by setting key.
     *
     * @return array{group: string, field: array<string, mixed>}|null
     */
    public static function field(string $key): ?array
    {
        foreach (self::groups() as $groupKey => $group) {
            if (isset($group['fields'][$key])) {
                return ['group' => $groupKey, 'field' => $group['fields'][$key]];
            }
        }

        return null;
    }

    /**
     * Whether the user may edit the given group: "settings.edit" covers every
     * group, and a group may grant access to other roles via "edit_permission".
     */
    public static function canEditGroup(User $user, string $groupKey): bool
    {
        $permission = self::groups()[$groupKey]['edit_permission'] ?? null;

        return $user->hasPermission('settings.edit')
            || ($permission !== null && $user->hasPermission($permission));
    }

    /**
     * Whether the user may see the given group in the settings panel.
     */
    public static function canViewGroup(User $user, string $groupKey): bool
    {
        return $user->hasPermission('settings.view') || self::canEditGroup($user, $groupKey);
    }

    /**
     * Fields that every user can override with their own preferences.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function personalFields(): array
    {
        return collect(self::groups())
            ->filter(fn (array $group) => $group['personal'] ?? false)
            ->flatMap(fn (array $group) => $group['fields'])
            ->all();
    }

    /**
     * Effective personal preferences: the user's own choices over the system defaults.
     *
     * @return array<string, mixed>
     */
    public static function preferencesFor(User $user): array
    {
        $overrides = $user->preferences ?? [];

        return collect(self::personalFields())
            ->map(fn (array $field, string $key) => $overrides[$key] ?? self::value($key))
            ->all();
    }

    /**
     * Return the schema with the current (or default) value per field.
     * When a user is given, only the groups they may view are returned,
     * each flagged with whether the user may edit it.
     */
    public static function schema(?User $user = null): array
    {
        $stored = SystemSetting::all()->keyBy('key');

        return collect(self::groups())
            ->filter(fn (array $group, string $groupKey) => $user === null || self::canViewGroup($user, $groupKey))
            ->map(function (array $group, string $groupKey) use ($stored, $user) {
                $group = [
                    'key' => $groupKey,
                    'label' => $group['label'],
                    'description' => $group['description'] ?? '',
                    'can_edit' => $user === null || self::canEditGroup($user, $groupKey),
                    'fields' => $group['fields'],
                ];
                $group['fields'] = collect($group['fields'])
                    ->map(function (array $field, string $key) use ($stored, $groupKey) {
                        $setting = $stored->get($key);

                        return [
                            'key' => $key,
                            'label' => $field['label'],
                            'type' => $field['type'],
                            'description' => $field['description'] ?? null,
                            'options' => $field['options'] ?? null,
                            'value' => $setting
                                ? SystemSetting::castValue($setting->value, $setting->type)
                                : $field['default'],
                            'default' => $field['default'],
                            'group' => $groupKey,
                        ];
                    })
                    ->values()
                    ->all();

                return $group;
            })
            ->values()
            ->all();
    }

    /**
     * Validation rules for a bulk update of catalog settings.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    public static function rulesFor(array $values): array
    {
        $rules = [];

        foreach (array_keys($values) as $key) {
            $definition = self::field($key);
            if ($definition && $definition['field']['type'] !== 'image') {
                $rules["values.{$key}"] = $definition['field']['rules'];
            }
        }

        return $rules;
    }

    /**
     * Persist catalog values and record every effective change in the history.
     * Unknown keys are ignored; image fields are only written when explicitly allowed,
     * because they are managed through their own upload endpoint.
     *
     * @param  array<string, mixed>  $values
     */
    public static function updateMany(array $values, bool $includeImages = false): void
    {
        DB::transaction(function () use ($values, $includeImages) {
            $stored = SystemSetting::whereIn('key', array_keys($values))->get()->keyBy('key');

            foreach ($values as $key => $value) {
                $definition = self::field($key);

                if (! $definition || ($definition['field']['type'] === 'image' && ! $includeImages)) {
                    continue;
                }

                $field = $definition['field'];
                $oldValue = $stored->has($key)
                    ? $stored->get($key)->value
                    : SystemSetting::normalizeValue($field['default'] ?? null);
                $newValue = SystemSetting::normalizeValue($value);

                SystemSetting::set(
                    $key,
                    $value,
                    self::storageType($field['type']),
                    $definition['group'],
                    $field['description'] ?? $field['label'],
                    (bool) ($field['is_public'] ?? false)
                );

                if ((string) $oldValue !== (string) $newValue) {
                    SystemSettingChange::create([
                        'key' => $key,
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                        'user_id' => auth()->id(),
                    ]);
                }
            }
        });
    }

    /**
     * Restore every field of a group to its catalog default.
     */
    public static function reset(string $group): void
    {
        $fields = self::groups()[$group]['fields'] ?? [];

        self::updateMany(
            collect($fields)->map(fn (array $field) => $field['default'] ?? null)->all(),
            includeImages: true
        );
    }

    /**
     * Values exposed publicly: catalog defaults merged with stored values.
     *
     * @return array<string, mixed>
     */
    public static function publicValues(): array
    {
        $publicKeys = [];

        foreach (self::groups() as $group) {
            foreach ($group['fields'] as $key => $field) {
                if ($field['is_public'] ?? false) {
                    $publicKeys[$key] = $field['default'] ?? null;
                }
            }
        }

        // The catalog decides visibility for its own keys, regardless of the stored flag.
        $stored = SystemSetting::whereIn('key', array_keys($publicKeys))->get();

        foreach ($stored as $setting) {
            $publicKeys[$setting->key] = SystemSetting::castValue($setting->value, $setting->type);
        }

        // Include custom public settings outside the catalog.
        SystemSetting::public()
            ->whereNotIn('key', array_keys($publicKeys))
            ->get()
            ->each(function (SystemSetting $setting) use (&$publicKeys) {
                $publicKeys[$setting->key] = SystemSetting::castValue($setting->value, $setting->type);
            });

        return $publicKeys;
    }

    /**
     * Get a catalog-aware value: stored value or catalog default.
     */
    public static function value(string $key, mixed $default = null): mixed
    {
        $definition = self::field($key);
        $fallback = $definition['field']['default'] ?? $default;

        return SystemSetting::get($key, $fallback);
    }

    /**
     * Map form control types to storage types.
     */
    public static function storageType(string $type): string
    {
        return match ($type) {
            'text', 'select', 'image' => 'string',
            default => $type,
        };
    }
}
