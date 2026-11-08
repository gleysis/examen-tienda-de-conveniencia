<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Support\SystemSettings;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Seed the catalog defaults without overwriting existing values.
     */
    public function run(): void
    {
        foreach (config('system_settings.groups', []) as $groupKey => $group) {
            foreach ($group['fields'] as $key => $field) {
                $default = $field['default'] ?? null;

                SystemSetting::firstOrCreate(
                    ['key' => $key],
                    [
                        'value' => match (true) {
                            is_bool($default) => $default ? '1' : '0',
                            is_array($default) => json_encode($default),
                            default => $default,
                        },
                        'type' => SystemSettings::storageType($field['type']),
                        'group' => $groupKey,
                        'description' => $field['description'] ?? $field['label'],
                        'is_public' => (bool) ($field['is_public'] ?? false),
                    ]
                );
            }
        }
    }
}
