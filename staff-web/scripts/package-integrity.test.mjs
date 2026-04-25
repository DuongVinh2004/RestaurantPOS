import { createHash } from 'node:crypto';
import { mkdtempSync, mkdirSync, readFileSync, statSync, utimesSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { collectPackageIntegrityReport } from '../../scripts/release/check-package-integrity.mjs';

describe('package integrity report', () => {
  it('passes for a minimal canonical staff-web handoff shape', () => {
    const fixtureRoot = createFixtureRoot();

    seedCanonicalFixture(fixtureRoot, { includeBackend: false, includeAdvisory: true });

    const report = collectPackageIntegrityReport({
      rootDir: fixtureRoot,
      staffWebOnly: true,
    });

    expect(report.ok).toBe(true);
    expect(report.decision).toBe('pass');
    expect(report.summary.missing_count).toBe(0);
    expect(report.summary.blocking_missing_count).toBe(0);
    expect(report.summary.advisory_missing_count).toBe(0);
  });

  it('fails fast when the generated SDK artifact is missing', () => {
    const fixtureRoot = createFixtureRoot();

    seedCanonicalFixture(fixtureRoot, { includeBackend: false, includeAdvisory: true, includeGeneratedSdk: false });

    const report = collectPackageIntegrityReport({
      rootDir: fixtureRoot,
      staffWebOnly: true,
    });

    expect(report.ok).toBe(false);
    expect(report.decision).toBe('block');
    expect(report.missing).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          path: 'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts',
          failure: 'missing',
        }),
      ]),
    );
  });

  it('warns without blocking when only handover docs are missing', () => {
    const fixtureRoot = createFixtureRoot();

    seedCanonicalFixture(fixtureRoot, { includeBackend: false, includeAdvisory: false });

    const report = collectPackageIntegrityReport({
      rootDir: fixtureRoot,
      staffWebOnly: true,
    });

    expect(report.ok).toBe(true);
    expect(report.decision).toBe('warn');
    expect(report.summary.blocking_missing_count).toBe(0);
    expect(report.summary.advisory_missing_count).toBeGreaterThan(0);
    expect(report.advisory_missing).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ path: 'README.md', blocking: false }),
        expect.objectContaining({ path: 'staff-web/STAFF_WEB_SETUP.md', blocking: false }),
      ]),
    );
  });

  it('blocks a full snapshot when backend test entrypoints are missing', () => {
    const fixtureRoot = createFixtureRoot();

    seedCanonicalFixture(fixtureRoot, { includeBackend: true, includeAdvisory: true, includePhpUnit: false });

    const report = collectPackageIntegrityReport({
      rootDir: fixtureRoot,
      staffWebOnly: false,
    });

    expect(report.ok).toBe(false);
    expect(report.decision).toBe('block');
    expect(report.blocking_missing).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          path: 'phpunit.xml',
          group: 'required_for_build_test_smoke',
          failure: 'missing',
        }),
      ]),
    );
  });

  it('blocks when generated consumer artifacts drift from the frozen OpenAPI or release manifest contract', () => {
    const fixtureRoot = createFixtureRoot();

    seedCanonicalFixture(fixtureRoot, { includeBackend: false, includeAdvisory: true });

    const sdkPath = path.join(fixtureRoot, 'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts');
    const manifestPath = path.join(fixtureRoot, 'storage/app/booking_release/release_manifest_snapshot.json');
    const openApiPath = path.join(fixtureRoot, 'storage/app/booking_release/openapi-v1.json');
    const now = new Date();
    const staleTime = new Date(now.getTime() - 10_000);
    const freshTime = new Date(now.getTime() + 10_000);

    writeFileSync(openApiPath, '{"openapi":"3.1.0","refreshed":true}', 'utf8');
    utimesSync(sdkPath, staleTime, staleTime);
    utimesSync(openApiPath, freshTime, freshTime);
    utimesSync(manifestPath, staleTime, staleTime);

    const report = collectPackageIntegrityReport({
      rootDir: fixtureRoot,
      staffWebOnly: true,
    });

    expect(report.ok).toBe(false);
    expect(report.decision).toBe('block');
    expect(report.stale).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          path: 'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts',
          failure: expect.stringContaining('release_manifest_snapshot.json'),
        }),
        expect.objectContaining({
          path: 'storage/app/booking_release/release_manifest_snapshot.json',
          failure: expect.stringContaining('no longer matches storage/app/booking_release/openapi-v1.json'),
        }),
      ]),
    );
  });
});

function createFixtureRoot() {
  return mkdtempSync(path.join(os.tmpdir(), 'restaurantpos-package-integrity-'));
}

function seedCanonicalFixture(
  rootDir,
  {
    includeBackend = false,
    includeAdvisory = true,
    includeGeneratedSdk = true,
    includePhpUnit = true,
  } = {},
) {
  if (includeBackend) {
    createFile(rootDir, 'composer.json', '{}');
    createFile(rootDir, 'artisan', '<?php');
    createFile(rootDir, '.env.example', 'APP_KEY=');
    createFile(rootDir, 'public/index.php', '<?php');
    createDirectory(rootDir, 'routes');
    createDirectory(rootDir, 'bootstrap');
    createDirectory(rootDir, 'config');
    createDirectory(rootDir, 'database');
    createDirectory(rootDir, 'tools/mysql');
    createFile(rootDir, 'tools/bootstrap_booking.php', '<?php');
    createDirectory(rootDir, 'tests');
    createDirectory(rootDir, 'scripts');
    createFile(rootDir, 'package.json', '{}');
    createFile(rootDir, 'vite.config.js', 'export default {};');

    if (includePhpUnit) {
      createFile(rootDir, 'phpunit.xml', '<phpunit />');
    }
  }

  createFile(rootDir, 'staff-web/package.json', '{}');
  createFile(rootDir, 'staff-web/vite.config.ts', 'export default {};');
  createFile(rootDir, 'staff-web/index.html', '<!doctype html>');
  createFile(rootDir, 'staff-web/tsconfig.json', '{}');
  createFile(rootDir, 'staff-web/vitest.config.ts', 'export default {};');
  createFile(rootDir, 'staff-web/scripts/live-smoke.mjs', 'export default true;');
  createFile(rootDir, 'staff-web/src/shared/api/sdk.ts', 'export * from "../../../../build/api-consumer/sdk/typescript/restaurantpos-sdk.ts";');
  createDirectory(rootDir, 'build/api-consumer');

  createFile(rootDir, 'storage/app/booking_release/openapi-v1.json', '{}');
  if (includeGeneratedSdk) {
    createFile(rootDir, 'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts', 'export const sdk = true;');
  }
  createFile(rootDir, 'build/api-consumer/sdk/typescript/restaurantpos-enums.ts', 'export const enums = true;');
  createFile(rootDir, 'build/api-consumer/enum-state-map.json', '{"enums":{}}');
  createFile(rootDir, 'build/api-consumer/mutation-contracts.md', '# contracts');
  createFile(rootDir, 'storage/app/booking_release/release_manifest_snapshot.json', '{}');

  if (includeAdvisory) {
    createFile(rootDir, 'README.md', '# repo');
    createDirectory(rootDir, 'docs/runbooks');
    createFile(rootDir, 'docs/runbooks/api-consumer-artifacts.md', '# api artifacts');
    createFile(rootDir, 'docs/runbooks/booking-local-windows-vscode-cmd-runbook.md', '# local runbook');
    createFile(rootDir, 'docs/runbooks/booking-release-packaging-runbook.md', '# packaging');
    createFile(rootDir, 'customer-web/.env.example', 'NEXT_PUBLIC_API_BASE_URL=http://localhost:8000');
    createFile(rootDir, 'customer-web/README.md', '# customer-web');
    createFile(rootDir, 'staff-web/.env.example', 'VITE_API_URL=http://localhost:8000/api/v1');
    createFile(rootDir, 'staff-web/STAFF_WEB_SETUP.md', '# setup');
    createFile(rootDir, 'staff-web/README.md', '# staff-web');
    createFile(rootDir, 'build/api-consumer/sdk/typescript/README.md', '# sdk');
  }

  writeReleaseManifestSnapshot(rootDir);
}

function createDirectory(rootDir, relativePath) {
  mkdirSync(path.join(rootDir, relativePath), { recursive: true });
}

function createFile(rootDir, relativePath, contents) {
  const resolvedPath = path.join(rootDir, relativePath);
  mkdirSync(path.dirname(resolvedPath), { recursive: true });
  writeFileSync(resolvedPath, contents, 'utf8');
}

function writeReleaseManifestSnapshot(rootDir) {
  const artifactPaths = [
    'storage/app/booking_release/openapi-v1.json',
    'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts',
    'build/api-consumer/sdk/typescript/restaurantpos-enums.ts',
    'build/api-consumer/enum-state-map.json',
    'build/api-consumer/mutation-contracts.md',
  ];

  const artifacts = {};

  for (const artifactPath of artifactPaths) {
    const resolvedPath = path.join(rootDir, artifactPath);

    try {
      const stats = statSync(resolvedPath);
      const sha256 = createHash('sha256').update(readFileSync(resolvedPath)).digest('hex');
      const artifactKey = artifactPath
        .replace(/^build\/api-consumer\/sdk\/typescript\//, '')
        .replace(/^build\/api-consumer\//, '')
        .replace(/^storage\/app\/booking_release\//, '')
        .replace(/[/.\\-]+/g, '_');

      artifacts[artifactKey] = {
        path: artifactPath,
        sha256,
        modified_epoch: Math.trunc(stats.mtimeMs / 1000),
      };
    } catch {
      // Skip artifacts intentionally omitted by the fixture variant.
    }
  }

  createFile(
    rootDir,
    'storage/app/booking_release/release_manifest_snapshot.json',
    JSON.stringify({ artifacts }, null, 2),
  );
}
