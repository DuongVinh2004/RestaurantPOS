<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Modules\Cashiering\Http\Requests\Staff\CheckoutOrderRequest;
use App\Modules\Cashiering\Http\Requests\Staff\FinalizeSettlementRequest;
use Tests\TestCase;

final class FinalizeSettlementRequestContractTest extends TestCase
{
    public function test_finalize_settlement_request_reuses_checkout_contract(): void
    {
        $checkoutRules = $this->normalizeRules((new CheckoutOrderRequest())->rules());
        $finalizeRules = $this->normalizeRules((new FinalizeSettlementRequest())->rules());

        self::assertSame($checkoutRules, $finalizeRules);
        self::assertContains('required', (array) $finalizeRules['payment_method']);
        self::assertContains('required', (array) $finalizeRules['paid_amount']);
        self::assertContains('required', (array) $finalizeRules['row_version']);
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
