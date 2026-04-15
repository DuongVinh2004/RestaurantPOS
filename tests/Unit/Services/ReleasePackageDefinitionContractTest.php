<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\Release\Services\ReleasePackageService;
use Tests\TestCase;

class ReleasePackageDefinitionContractTest extends TestCase
{
    public function test_release_package_definition_includes_required_release_roots(): void
    {
        $definition = app(ReleasePackageService::class)->definition();
        $includePaths = collect((array) ($definition['include_paths'] ?? []));

        $requiredPaths = $includePaths
            ->filter(static fn (array $item): bool => (bool) ($item['required'] ?? false))
            ->pluck('path')
            ->values()
            ->all();

        $this->assertSame('build/booking-release', (string) ($definition['output_root'] ?? ''));
        $this->assertSame('restaurantpos-backend-release', (string) ($definition['package_prefix'] ?? ''));
        $this->assertContains('artisan', $requiredPaths);
        $this->assertContains('composer.json', $requiredPaths);
        $this->assertContains('.env.example', $requiredPaths);
        $this->assertContains('app', $requiredPaths);
        $this->assertContains('bootstrap', $requiredPaths);
        $this->assertContains('build/api-consumer', $requiredPaths);
        $this->assertContains('config', $requiredPaths);
        $this->assertContains('database', $requiredPaths);
        $this->assertContains('package.json', $requiredPaths);
        $this->assertContains('phpunit.xml', $requiredPaths);
        $this->assertContains('public/index.php', $requiredPaths);
        $this->assertContains('routes', $requiredPaths);
        $this->assertContains('scripts', $requiredPaths);
        $this->assertContains('staff-web', $requiredPaths);
        $this->assertContains('storage/app/booking_release', $requiredPaths);
        $this->assertContains('tests', $requiredPaths);
        $this->assertContains('tools/bootstrap_booking.php', $requiredPaths);
        $this->assertContains('tools/mysql', $requiredPaths);
        $this->assertContains('vite.config.js', $requiredPaths);
        $this->assertContains('db_all.sql', $requiredPaths);
    }

    public function test_release_package_definition_keeps_optional_release_roots_explicit(): void
    {
        $definition = app(ReleasePackageService::class)->definition();
        $optionalPaths = collect((array) ($definition['include_paths'] ?? []))
            ->reject(static fn (array $item): bool => (bool) ($item['required'] ?? false))
            ->pluck('path')
            ->values()
            ->all();

        $this->assertContains('docs/runbooks', $optionalPaths);
        $this->assertContains('README.md', $optionalPaths);
        $this->assertNotContains('tools/mysql', $optionalPaths);
        $this->assertNotContains('staff-web', $optionalPaths);

        $excludedPaths = collect((array) ($definition['exclude_paths'] ?? []))
            ->values()
            ->all();

        $this->assertContains('staff-web/node_modules', $excludedPaths);
        $this->assertContains('staff-web/dist', $excludedPaths);
    }
}
