<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
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

    public function store(Request $request): array
    {
        $request->validate([
            'key' => 'required|string|max:255|unique:system_settings',
            'value' => 'nullable|string',
            'type' => 'required|in:string,integer,float,boolean,array,json',
            'group' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $setting = SystemSetting::create($request->all());

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

    public function update(Request $request, SystemSetting $setting): array
    {
        $request->validate([
            'value' => 'nullable|string',
            'type' => 'sometimes|in:string,integer,float,boolean,array,json',
            'group' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'is_public' => 'sometimes|boolean',
        ]);

        $setting->update($request->all());

        return [
            'setting' => $setting,
            'message' => 'Configuración actualizada exitosamente',
        ];
    }

    public function destroy(SystemSetting $setting): array
    {
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

    public function getPublic(): array
    {
        $settings = SystemSetting::public()->get()->pluck('value', 'key');

        return [
            'settings' => $settings,
        ];
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
