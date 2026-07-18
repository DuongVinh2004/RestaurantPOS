<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

final class DependencySecurityCiContractTest extends TestCase
{
    public function test_pull_request_and_main_ci_run_the_dependency_security_gate_and_archive_evidence(): void
    {
        $workflow = $this->readRepositoryFile('.github/workflows/booking-ci.yml');

        self::assertStringContainsString("pull_request:\n", $workflow);
        self::assertStringContainsString("      - main\n", $workflow);
        self::assertStringContainsString('dependency-security:', $workflow);
        self::assertStringContainsString('bash scripts/ci/booking-dependency-security-gate.sh', $workflow);
        self::assertStringContainsString('name: dependency-security-evidence-ci-', $workflow);
        self::assertStringContainsString('build/booking-ci/dependency-security/**', $workflow);
    }

    public function test_production_deployment_cannot_bypass_the_dependency_security_gate(): void
    {
        $workflow = $this->readRepositoryFile('.github/workflows/booking-cd.yml');

        self::assertStringContainsString('dependency-security:', $workflow);
        self::assertStringContainsString('bash scripts/ci/booking-dependency-security-gate.sh', $workflow);
        self::assertStringContainsString('needs: dependency-security', $workflow);
        self::assertStringContainsString('name: dependency-security-evidence-production-', $workflow);
        self::assertStringContainsString('build/booking-ci/dependency-security/**', $workflow);
    }

    public function test_gate_installs_tests_builds_audits_and_generates_cyclonedx_sboms_for_every_workspace(): void
    {
        $gate = $this->readRepositoryFile('scripts/ci/booking-dependency-security-gate.sh');
        $policy = $this->readRepositoryFile('scripts/ci/dependency-security-gate.mjs');

        foreach ([
            'npm ci --no-fund',
            'npm --prefix customer-web ci --no-fund',
            'npm --prefix staff-web ci --no-fund',
            'node scripts/ci/dependency-security-gate.mjs --all',
            'npm --prefix customer-web run test',
            'npm --prefix customer-web run build',
            'npm --prefix staff-web run test',
            'npm --prefix staff-web run build',
        ] as $fragment) {
            self::assertStringContainsString($fragment, $gate, $fragment);
        }

        foreach ([
            "'audit', '--omit=dev', '--audit-level=high', '--json'",
            "'sbom', '--omit=dev', '--sbom-format=cyclonedx'",
            'npm-audit-production.json',
            'sbom.cyclonedx.json',
            'high_and_critical_must_be_zero',
        ] as $fragment) {
            self::assertStringContainsString($fragment, $policy, $fragment);
        }
    }

    public function test_manifests_use_the_minimum_fixed_direct_versions_and_keep_shadcn_out_of_production(): void
    {
        $root = json_decode($this->readRepositoryFile('package.json'), true, 512, JSON_THROW_ON_ERROR);
        $customer = json_decode($this->readRepositoryFile('customer-web/package.json'), true, 512, JSON_THROW_ON_ERROR);
        $staff = json_decode($this->readRepositoryFile('staff-web/package.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('restaurantpos-root-assets', $root['name'] ?? null);
        self::assertSame('0.0.0', $root['version'] ?? null);
        self::assertSame('16.2.10', $customer['dependencies']['next'] ?? null);
        self::assertSame('16.2.10', $customer['devDependencies']['eslint-config-next'] ?? null);
        self::assertArrayNotHasKey('shadcn', $customer['dependencies'] ?? []);
        self::assertSame('^4.3.0', $customer['devDependencies']['shadcn'] ?? null);
        self::assertSame('^6.30.4', $staff['dependencies']['react-router-dom'] ?? null);
    }

    private function readRepositoryFile(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 3).'/'.$relativePath);

        self::assertNotFalse($contents, sprintf('Missing repository file: %s', $relativePath));

        return $contents;
    }
}
