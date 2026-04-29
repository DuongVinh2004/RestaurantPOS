import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, utimesSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';

import { collectPackageIntegrityReport } from './check-package-integrity.mjs';

const REQUIRED_DIRECTORIES = [
  'bootstrap',
  'config',
  'customer-web/e2e',
  'customer-web/src/app',
  'database',
  'docs/runbooks',
  'routes',
  'scripts',
  'tests',
  'tools/mysql',
];

const REQUIRED_FILES = {
  '.env.example': '',
  'README.md': '# repo\n',
  'artisan': '<?php\n',
  'composer.json': '{}\n',
  'customer-web/.env.example': '',
  'customer-web/README.md': '# customer\n',
  'customer-web/next.config.ts': 'export default {};\n',
  'customer-web/package.json': '{}\n',
  'customer-web/playwright.config.ts': 'export default {};\n',
  'customer-web/playwright.live.config.ts': 'export default {};\n',
  'customer-web/scripts/check-contract-governance.mjs': 'console.log("ok");\n',
  'customer-web/scripts/verify-live-runtime.mjs': 'console.log("ok");\n',
  'customer-web/src/lib/contracts/generated/restaurantpos-enums.ts': 'export const enums = true;\n',
  'customer-web/src/lib/contracts/generated/restaurantpos-sdk.ts': 'export const sdk = true;\n',
  'customer-web/tsconfig.json': '{}\n',
  'customer-web/vitest.config.ts': 'export default {};\n',
  'docs/runbooks/api-consumer-artifacts.md': '# api\n',
  'docs/runbooks/booking-local-windows-vscode-cmd-runbook.md': '# local\n',
  'docs/runbooks/booking-release-packaging-runbook.md': '# release\n',
  'package.json': '{}\n',
  'phpunit.xml': '<phpunit />\n',
  'public/index.php': '<?php\n',
  'staff-web/.env.example': '',
  'staff-web/README.md': '# staff\n',
  'staff-web/STAFF_WEB_SETUP.md': '# setup\n',
  'staff-web/index.html': '<html></html>\n',
  'staff-web/package.json': '{}\n',
  'staff-web/scripts/live-smoke.mjs': 'console.log("ok");\n',
  'staff-web/src/shared/api/client.ts': [
    'const token = headers.get(\'X-Staff-Key\');',
    'staffClient.staffTablesBoard({});',
    'staffClient.postV1StaffOrdersOrderIdBillSnapshot({}, {}, {});',
    'staffClient.postV1StaffOrdersOrderIdSettlementFinalize({}, {}, {});',
    '',
  ].join('\n'),
  'staff-web/src/shared/api/sdk.ts': 'export const sdk = true;\n',
  'staff-web/src/shared/api/staff-api.ts': [
    'staffClient.staffTablesBoard({});',
    'staffClient.postV1StaffOrdersOrderIdBillSnapshot({}, {}, { idempotencyKey: createIdempotencyKey(\'bill\') });',
    'staffClient.postV1StaffOrdersOrderIdSettlementFinalize({}, {}, { idempotencyKey: createIdempotencyKey(\'settlement\') });',
    'staffClient.postV1StaffOrdersOrderIdKitchenDispatch({}, {}, { idempotencyKey: createIdempotencyKey(\'dispatch\') });',
    'staffClient.postV1StaffKitchenTicketsTicketIdFire({}, {}, { idempotencyKey: createIdempotencyKey(\'fire\') });',
    'staffClient.postV1StaffReservationsReservationIdRefund({}, {}, { idempotencyKey: createIdempotencyKey(\'refund\') });',
    'staffClient.postV1StaffCashierShiftsOpen({}, { idempotencyKey: createIdempotencyKey(\'cashier\') });',
    '',
  ].join('\n'),
  'staff-web/tsconfig.json': '{}\n',
  'staff-web/vite.config.ts': 'export default {};\n',
  'staff-web/vitest.config.ts': 'export default {};\n',
  'tools/bootstrap_booking.php': '<?php\n',
  'vite.config.js': 'export default {};\n',
};

function scaffoldPackageIntegrityRoot() {
  const rootDir = mkdtempSync(path.join(os.tmpdir(), 'restaurantpos-package-integrity-'));

  for (const relativeDirectory of REQUIRED_DIRECTORIES) {
    mkdirSync(path.join(rootDir, relativeDirectory), { recursive: true });
  }

  for (const [relativePath, contents] of Object.entries(REQUIRED_FILES)) {
    const resolvedPath = path.join(rootDir, relativePath);
    mkdirSync(path.dirname(resolvedPath), { recursive: true });
    writeFileSync(resolvedPath, contents, 'utf8');
  }

  const artifactFiles = {
    'build/api-consumer/enum-state-map.json': '{"enums":{}}\n',
    'build/api-consumer/sdk/typescript/README.md': '# sdk\n',
    'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts': 'export const canonicalSdk = true;\n',
    'build/api-consumer/sdk/typescript/restaurantpos-enums.ts': 'export const canonicalEnums = true;\n',
    'build/api-consumer/mutation-contracts.md': '# mutation\n',
    'storage/app/booking_release/openapi-v1.json': '{"openapi":"3.1.0"}\n',
  };

  for (const [relativePath, contents] of Object.entries(artifactFiles)) {
    const resolvedPath = path.join(rootDir, relativePath);
    mkdirSync(path.dirname(resolvedPath), { recursive: true });
    writeFileSync(resolvedPath, contents, 'utf8');
  }

  const snapshot = {
    ok: true,
    status: 'ok',
    artifacts: {
      openapi_v1_spec: createArtifactRecord(rootDir, 'storage/app/booking_release/openapi-v1.json', 1000),
      api_consumer_sdk_typescript: createArtifactRecord(rootDir, 'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts', 2000),
      api_consumer_sdk_enums_typescript: createArtifactRecord(rootDir, 'build/api-consumer/sdk/typescript/restaurantpos-enums.ts', 2000),
      api_consumer_enum_state_json: createArtifactRecord(rootDir, 'build/api-consumer/enum-state-map.json', 2000),
      api_consumer_mutation_contract: createArtifactRecord(rootDir, 'build/api-consumer/mutation-contracts.md', 2000),
    },
  };

  const snapshotPath = path.join(rootDir, 'storage/app/booking_release/release_manifest_snapshot.json');
  writeFileSync(snapshotPath, `${JSON.stringify(snapshot, null, 2)}\n`, 'utf8');

  return rootDir;
}

function createArtifactRecord(rootDir, relativePath, modifiedEpoch) {
  const resolvedPath = path.join(rootDir, relativePath);

  return {
    path: relativePath.replace(/\\/g, '/'),
    sha256: computeSha256(resolvedPath),
    modified_epoch: modifiedEpoch,
  };
}

function computeSha256(resolvedPath) {
  return createHash('sha256').update(readFileSync(resolvedPath)).digest('hex');
}

test('full package integrity blocks when customer-web runtime files are missing', (t) => {
  const rootDir = scaffoldPackageIntegrityRoot();
  t.after(() => rmSync(rootDir, { force: true, recursive: true }));

  rmSync(path.join(rootDir, 'customer-web/package.json'));

  const report = collectPackageIntegrityReport({ rootDir });

  assert.equal(report.ok, false);
  assert.ok(report.blocking_missing.some((check) => check.path === 'customer-web/package.json'));
});

test('snapshot-backed freshness overrides raw filesystem mtimes when frozen hashes still match', (t) => {
  const rootDir = scaffoldPackageIntegrityRoot();
  t.after(() => rmSync(rootDir, { force: true, recursive: true }));

  const openApiPath = path.join(rootDir, 'storage/app/booking_release/openapi-v1.json');
  const enumsPath = path.join(rootDir, 'build/api-consumer/sdk/typescript/restaurantpos-enums.ts');
  const enumStateMapPath = path.join(rootDir, 'build/api-consumer/enum-state-map.json');

  utimesSync(openApiPath, new Date('2026-04-19T14:00:00Z'), new Date('2026-04-19T14:00:00Z'));
  utimesSync(enumsPath, new Date('2026-04-19T12:00:00Z'), new Date('2026-04-19T12:00:00Z'));
  utimesSync(enumStateMapPath, new Date('2026-04-19T12:00:00Z'), new Date('2026-04-19T12:00:00Z'));

  const report = collectPackageIntegrityReport({ rootDir });

  assert.equal(report.ok, true);
  assert.equal(report.stale.some((check) => check.label === 'generated TypeScript enums freshness'), false);
  assert.equal(report.stale.some((check) => check.label === 'generated enum/state map freshness'), false);
});

test('package integrity blocks when generated artifact content drifts from the frozen snapshot', (t) => {
  const rootDir = scaffoldPackageIntegrityRoot();
  t.after(() => rmSync(rootDir, { force: true, recursive: true }));

  writeFileSync(
    path.join(rootDir, 'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts'),
    'export const canonicalSdk = false;\n',
    'utf8',
  );

  const report = collectPackageIntegrityReport({ rootDir });

  assert.equal(report.ok, false);
  assert.equal(
    report.stale.some((check) => check.label === 'generated TypeScript SDK freshness' && String(check.failure).includes('release_manifest_snapshot.json')),
    true,
  );
});
