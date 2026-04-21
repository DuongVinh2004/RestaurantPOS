<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Modules\Catalog\Http\Requests\Admin\UpdateMenuCategoryRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateMenuItemRequest;
use Tests\TestCase;

final class MenuCompatibilityRequestContractTest extends TestCase
{
    public function test_update_menu_category_request_supports_legacy_id_route_parameter(): void
    {
        $legacyRules = $this->normalizeRules($this->makeUpdateMenuCategoryRequest('id', 42)->rules());
        $splitRules = $this->normalizeRules($this->makeUpdateMenuCategoryRequest('category_id', 42)->rules());

        self::assertSame($splitRules, $legacyRules);
    }

    public function test_update_menu_item_request_supports_legacy_id_route_parameter(): void
    {
        $legacyRules = $this->normalizeRules($this->makeUpdateMenuItemRequest('id', 84)->rules());
        $splitRules = $this->normalizeRules($this->makeUpdateMenuItemRequest('item_id', 84)->rules());

        self::assertSame($splitRules, $legacyRules);
    }

    private function makeUpdateMenuCategoryRequest(string $parameter, int $value): UpdateMenuCategoryRequest
    {
        $request = UpdateMenuCategoryRequest::create('/admin/menu/categories/' . $value, 'PATCH');
        $request->setRouteResolver(fn (): object => $this->makeRouteStub($parameter, $value));

        return $request;
    }

    private function makeUpdateMenuItemRequest(string $parameter, int $value): UpdateMenuItemRequest
    {
        $request = UpdateMenuItemRequest::create('/admin/menu/items/' . $value, 'PATCH');
        $request->setRouteResolver(fn (): object => $this->makeRouteStub($parameter, $value));

        return $request;
    }

    private function makeRouteStub(string $parameter, int $value): object
    {
        return new class($parameter, $value)
        {
            public function __construct(
                private readonly string $parameter,
                private readonly int $value,
            ) {}

            public function parameter(string $name, mixed $default = null): mixed
            {
                return $name === $this->parameter ? $this->value : $default;
            }
        };
    }

    /**
     * @param array<string,mixed> $rules
     * @return array<string,mixed>
     */
    private function normalizeRules(array $rules): array
    {
        foreach ($rules as $field => $ruleSet) {
            $rules[$field] = $this->normalizeRuleValue($ruleSet);
        }

        return $rules;
    }

    private function normalizeRuleValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $rule): mixed => $this->normalizeRuleValue($rule), $value);
        }

        if (is_object($value)) {
            return [
                'class' => $value::class,
                'state' => (array) $value,
            ];
        }

        return $value;
    }
}
