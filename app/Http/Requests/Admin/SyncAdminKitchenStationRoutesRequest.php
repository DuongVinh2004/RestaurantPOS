<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncAdminKitchenStationRoutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $routes = $this->input('routes', []);

        if (! is_array($routes)) {
            return;
        }

        $normalized = array_map(static function ($route, int $index): array {
            $route = is_array($route) ? $route : [];

            return [
                'category_id' => isset($route['category_id']) ? (int) $route['category_id'] : null,
                'sort_order' => array_key_exists('sort_order', $route) ? (int) $route['sort_order'] : (($index + 1) * 10),
                'is_active' => array_key_exists('is_active', $route) ? filter_var($route['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false : true,
            ];
        }, $routes, array_keys($routes));

        $this->merge(['routes' => $normalized]);
    }

    public function rules(): array
    {
        return [
            'routes' => ['required', 'array'],
            'routes.*.category_id' => ['required', 'integer', Rule::exists('menu_categories', 'category_id'), 'distinct'],
            'routes.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'routes.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
