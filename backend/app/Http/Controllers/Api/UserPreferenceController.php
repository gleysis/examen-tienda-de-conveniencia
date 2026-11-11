<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserPreferenceController extends Controller
{
    public function show(Request $request): array
    {
        return $this->payload($request->user());
    }

    /**
     * Save the user's own preferences. A null value falls back to the system default.
     */
    public function update(Request $request): array
    {
        $fields = SystemSettings::personalFields();

        $validated = $request->validate([
            'preferences' => 'required|array',
            ...collect($fields)
                ->mapWithKeys(fn (array $field, string $key) => [
                    "preferences.{$key}" => Str::replace('required', 'nullable', $field['rules']),
                ])
                ->all(),
        ]);

        $user = $request->user();
        $preferences = collect([...($user->preferences ?? []), ...$validated['preferences']])
            ->only(array_keys($fields))
            ->reject(fn (mixed $value) => $value === null)
            ->all();

        $user->update(['preferences' => $preferences ?: null]);

        return [
            ...$this->payload($user),
            'message' => 'Preferencias guardadas',
        ];
    }

    /**
     * @return array{groups: array<int, array<string, mixed>>, preferences: array<string, mixed>}
     */
    private function payload(User $user): array
    {
        $preferences = SystemSettings::preferencesFor($user);

        return [
            'groups' => collect(SystemSettings::personalGroups())
                ->map(fn (array $group, string $groupKey) => [
                    'key' => "personal.{$groupKey}",
                    'label' => $group['label'],
                    'description' => $group['description'],
                    'can_edit' => true,
                    'fields' => collect($group['fields'])
                        ->map(fn (array $field, string $key) => [
                            'key' => $key,
                            'label' => $field['label'],
                            'type' => $field['type'],
                            'description' => $field['description'] ?? null,
                            'options' => SystemSettings::fieldOptions($field),
                            'value' => $preferences[$key],
                            'default' => SystemSettings::value($key),
                            'group' => "personal.{$groupKey}",
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'preferences' => $preferences,
        ];
    }
}
