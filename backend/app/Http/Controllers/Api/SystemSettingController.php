<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSystemSettingRequest;
use App\Http\Requests\UpdateSystemSettingRequest;
use App\Http\Resources\SystemSettingChangeResource;
use App\Models\SystemSetting;
use App\Models\SystemSettingChange;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SystemSettingController extends Controller
{
    private const LOGO_DIRECTORY = 'branding';

    public function index(Request $request): array
    {
        $query = SystemSetting::query();

        if ($request->has('group')) {
            $query->byGroup($request->group);
        }

        if ($request->has('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        $settings = $query->get();

        return [
            'settings' => $settings,
        ];
    }

    public function store(StoreSystemSettingRequest $request): array
    {
        $setting = SystemSetting::create($request->validated());

        return [
            'setting' => $setting,
            'message' => 'Configuración creada exitosamente',
        ];
    }

    public function show(SystemSetting $setting): array
    {
        return [
            'setting' => $setting,
        ];
    }

    public function update(UpdateSystemSettingRequest $request, SystemSetting $setting): array
    {
        $this->ensureNotCatalogKey($setting);

        $setting->update($request->validated());

        return [
            'setting' => $setting,
            'message' => 'Configuración actualizada exitosamente',
        ];
    }

    public function destroy(SystemSetting $setting): array
    {
        $this->ensureNotCatalogKey($setting);

        $setting->delete();

        return [
            'message' => 'Configuración eliminada exitosamente',
        ];
    }

    public function get(Request $request): array
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        $value = SystemSetting::get($request->key);

        return [
            'key' => $request->key,
            'value' => $value,
        ];
    }

    public function set(Request $request): array
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'required',
            'type' => 'sometimes|in:string,integer,float,boolean,array,json',
            'group' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'is_public' => 'sometimes|boolean',
        ]);

        if (SystemSettings::isCatalogKey($request->key)) {
            if (SystemSettings::field($request->key)['field']['type'] === 'image') {
                abort(422, 'El logo se administra desde la sección Tienda.');
            }

            $values = [$request->key => $request->value];
            validator(['values' => $values], SystemSettings::rulesFor($values))->validate();
            SystemSettings::updateMany($values);

            return [
                'key' => $request->key,
                'value' => SystemSettings::value($request->key),
                'message' => 'Configuración actualizada exitosamente',
            ];
        }

        SystemSetting::set(
            $request->key,
            $request->value,
            $request->type ?? 'string',
            $request->group ?? 'general',
            $request->description,
            $request->boolean('is_public', false)
        );

        return [
            'key' => $request->key,
            'value' => $request->value,
            'message' => 'Configuración actualizada exitosamente',
        ];
    }

    /**
     * Catalog settings must be edited through the typed settings panel.
     */
    private function ensureNotCatalogKey(SystemSetting $setting): void
    {
        if (SystemSettings::isCatalogKey($setting->key)) {
            abort(422, 'Esta configuración pertenece al sistema. Edítala desde su sección correspondiente.');
        }
    }

    public function getPublic(): array
    {
        return [
            'settings' => SystemSettings::publicValues(),
        ];
    }

    public function schema(Request $request): array
    {
        $groups = SystemSettings::schema($request->user());

        abort_if($groups === [], 403, 'No tienes permiso para ver la configuración.');

        return [
            'groups' => $groups,
        ];
    }

    public function bulkUpdate(Request $request): array
    {
        $values = $request->input('values', []);
        $groupKeys = collect(is_array($values) ? array_keys($values) : [])
            ->map(fn (string|int $key) => SystemSettings::field((string) $key)['group'] ?? null)
            ->filter()
            ->unique();
        $editableGroups = collect(array_keys(SystemSettings::groups()))
            ->filter(fn (string $groupKey) => SystemSettings::canEditGroup($request->user(), $groupKey));

        abort_if(
            $editableGroups->isEmpty() || $groupKeys->diff($editableGroups)->isNotEmpty(),
            403,
            'No tienes permiso para editar esta sección de la configuración.'
        );

        $request->validate([
            'values' => 'required|array',
            ...SystemSettings::rulesFor($values),
        ]);

        SystemSettings::updateMany($values);

        return [
            'groups' => SystemSettings::schema($request->user()),
            'message' => 'Configuración guardada exitosamente',
        ];
    }

    public function reset(Request $request): array
    {
        $request->validate([
            'group' => ['required', 'string', Rule::in(array_keys(SystemSettings::groups()))],
        ]);

        abort_unless(
            SystemSettings::canEditGroup($request->user(), $request->group),
            403,
            'No tienes permiso para editar esta sección de la configuración.'
        );

        $previousLogo = (string) SystemSettings::value('store_logo', '');

        SystemSettings::reset($request->group);

        if ($request->group === 'store') {
            $this->deleteLogoFile($previousLogo);
        }

        return [
            'groups' => SystemSettings::schema($request->user()),
            'message' => 'Valores predeterminados restablecidos',
        ];
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        $changes = SystemSettingChange::with('user:id,name')
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return SystemSettingChangeResource::collection($changes);
    }

    public function uploadLogo(Request $request): array
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $path = $request->file('logo')->store(self::LOGO_DIRECTORY, 'public');

        if ($path === false) {
            abort(500, 'No se pudo guardar el logo.');
        }

        $previousLogo = (string) SystemSettings::value('store_logo', '');
        SystemSettings::updateMany(['store_logo' => asset('storage/'.$path)], includeImages: true);
        $this->deleteLogoFile($previousLogo);

        return [
            'groups' => SystemSettings::schema($request->user()),
            'message' => 'Logo actualizado exitosamente',
        ];
    }

    public function deleteLogo(Request $request): array
    {
        $previousLogo = (string) SystemSettings::value('store_logo', '');
        SystemSettings::updateMany(['store_logo' => ''], includeImages: true);
        $this->deleteLogoFile($previousLogo);

        return [
            'groups' => SystemSettings::schema($request->user()),
            'message' => 'Logo eliminado',
        ];
    }

    /**
     * Remove a logo file previously uploaded through this panel.
     */
    private function deleteLogoFile(string $url): void
    {
        $path = Str::after($url, '/storage/');

        if ($url !== '' && Str::startsWith($path, self::LOGO_DIRECTORY.'/')) {
            Storage::disk('public')->delete($path);
        }
    }

    public function getByGroup(Request $request): array
    {
        $request->validate([
            'group' => 'required|string',
        ]);

        $settings = SystemSetting::byGroup($request->group)->get();

        return [
            'group' => $request->group,
            'settings' => $settings,
        ];
    }
}
