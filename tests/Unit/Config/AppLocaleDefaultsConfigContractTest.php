<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class AppLocaleDefaultsConfigContractTest extends TestCase
{
    public function test_runtime_timezone_stays_utc_while_default_locale_targets_vietnam(): void
    {
        $this->assertSame('UTC', (string) config('app.timezone'));
        $this->assertSame('vi', (string) config('app.locale'));
        $this->assertSame('vi', (string) config('app.fallback_locale'));
        $this->assertSame('vi_VN', (string) config('app.faker_locale'));
    }
}
