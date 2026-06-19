<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Models\MenuModifierGroup;
use App\Modules\Catalog\Domain\Models\MenuModifier;
use App\Modules\Catalog\Http\Resources\Admin\MenuModifierGroupResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuModifierGroupController extends Controller
{
    public function index(Request $request)
    {
        $query = MenuModifierGroup::with('modifiers')->orderBy('group_id', 'desc');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        return MenuModifierGroupResource::collection($query->paginate($request->input('per_page', 15)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:400',
            'min_selections' => 'required|integer|min:0',
            'max_selections' => 'required|integer|min:1|gte:min_selections',
            'is_active' => 'boolean',
            'modifiers' => 'array',
            'modifiers.*.name' => 'required|string|max:150',
            'modifiers.*.price_adjustment' => 'required|numeric|min:0',
            'modifiers.*.sort_order' => 'integer',
        ]);

        return DB::transaction(function () use ($validated) {
            $group = MenuModifierGroup::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'min_selections' => $validated['min_selections'],
                'max_selections' => $validated['max_selections'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (!empty($validated['modifiers'])) {
                foreach ($validated['modifiers'] as $idx => $modData) {
                    $group->modifiers()->create([
                        'name' => $modData['name'],
                        'price_adjustment' => $modData['price_adjustment'],
                        'sort_order' => $modData['sort_order'] ?? $idx,
                        'is_active' => true,
                    ]);
                }
            }

            return new MenuModifierGroupResource($group->load('modifiers'));
        });
    }

    public function show(MenuModifierGroup $modifierGroup)
    {
        return new MenuModifierGroupResource($modifierGroup->load('modifiers'));
    }

    public function update(Request $request, MenuModifierGroup $modifierGroup)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:400',
            'min_selections' => 'required|integer|min:0',
            'max_selections' => 'required|integer|min:1|gte:min_selections',
            'is_active' => 'boolean',
            'modifiers' => 'array',
            'modifiers.*.modifier_id' => 'nullable|integer',
            'modifiers.*.name' => 'required|string|max:150',
            'modifiers.*.price_adjustment' => 'required|numeric|min:0',
            'modifiers.*.sort_order' => 'integer',
        ]);

        return DB::transaction(function () use ($validated, $modifierGroup) {
            $modifierGroup->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'min_selections' => $validated['min_selections'],
                'max_selections' => $validated['max_selections'],
                'is_active' => $validated['is_active'] ?? $modifierGroup->is_active,
            ]);

            if (isset($validated['modifiers'])) {
                $existingIds = $modifierGroup->modifiers()->pluck('modifier_id')->toArray();
                $keptIds = [];

                foreach ($validated['modifiers'] as $idx => $modData) {
                    if (!empty($modData['modifier_id']) && in_array($modData['modifier_id'], $existingIds)) {
                        $modifier = MenuModifier::find($modData['modifier_id']);
                        $modifier->update([
                            'name' => $modData['name'],
                            'price_adjustment' => $modData['price_adjustment'],
                            'sort_order' => $modData['sort_order'] ?? $idx,
                        ]);
                        $keptIds[] = $modifier->modifier_id;
                    } else {
                        $newMod = $modifierGroup->modifiers()->create([
                            'name' => $modData['name'],
                            'price_adjustment' => $modData['price_adjustment'],
                            'sort_order' => $modData['sort_order'] ?? $idx,
                            'is_active' => true,
                        ]);
                        $keptIds[] = $newMod->modifier_id;
                    }
                }

                $modifierGroup->modifiers()->whereNotIn('modifier_id', $keptIds)->delete();
            }

            return new MenuModifierGroupResource($modifierGroup->load('modifiers'));
        });
    }

    public function destroy(MenuModifierGroup $modifierGroup)
    {
        $modifierGroup->delete();
        return response()->noContent();
    }
}
