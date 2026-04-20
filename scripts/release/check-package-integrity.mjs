import { createHash } from 'node:crypto';
import path from 'node:path';
import process from 'node:process';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const REQUIREMENT_GROUPS = {
  required_to_run: {
    label: 'required to run',
    blocking: true,
  },
  required_for_build_test_smoke: {
    label: 'required for build/test/smoke',
    blocking: true,
  },
  useful_for_handover: {
    label: 'useful for handover',
    blocking: false,
  },
};

const GROUP_ORDER = [
  'required_to_run',
  'required_for_build_test_smoke',
  'useful_for_handover',
];

const RELEASE_MANIFEST_SNAPSHOT_PATH = 'storage/app/booking_release/release_manifest_snapshot.json';

const BACKEND_REQUIREMENTS = [
  { scope: 'backend', group: 'required_to_run', type: 'file', path: 'composer.json', label: 'backend root composer manifest' },
  { scope: 'backend', group: 'required_to_run', type: 'file', path: 'artisan', label: 'Laravel artisan entrypoint' },
  { scope: 'backend', group: 'required_to_run', type: 'file', path: '.env.example', label: 'backend environment template' },
  { scope: 'backend', group: 'required_to_run', type: 'file', path: 'public/index.php', label: 'Laravel HTTP entrypoint' },
  { scope: 'backend', group: 'required_to_run', type: 'directory', path: 'routes', label: 'Laravel route definitions' },
  { scope: 'backend', group: 'required_to_run', type: 'directory', path: 'bootstrap', label: 'Laravel bootstrap directory' },
  { scope: 'backend', group: 'required_to_run', type: 'directory', path: 'config', label: 'Laravel config directory' },
  { scope: 'backend', group: 'required_to_run', type: 'directory', path: 'database', label: 'database contract directory' },
  { scope: 'backend', group: 'required_to_run', type: 'directory', path: 'tools/mysql', label: 'SQL-first MySQL helper directory' },
  { scope: 'backend', group: 'required_to_run', type: 'file', path: 'tools/bootstrap_booking.php', label: 'SQL-first bootstrap entrypoint' },
  { scope: 'backend', group: 'required_for_build_test_smoke', type: 'directory', path: 'tests', label: 'backend tests directory' },
  { scope: 'backend', group: 'required_for_build_test_smoke', type: 'file', path: 'phpunit.xml', label: 'backend PHPUnit config' },
  { scope: 'backend', group: 'required_for_build_test_smoke', type: 'file', path: 'package.json', label: 'root frontend asset package manifest' },
  { scope: 'backend', group: 'required_for_build_test_smoke', type: 'file', path: 'vite.config.js', label: 'root Vite config' },
  { scope: 'backend', group: 'required_for_build_test_smoke', type: 'directory', path: 'scripts', label: 'repository scripts directory' },
];

const STAFF_WEB_REQUIREMENTS = [
  { scope: 'staff-web', group: 'required_to_run', type: 'file', path: 'staff-web/package.json', label: 'staff-web package manifest' },
  { scope: 'staff-web', group: 'required_to_run', type: 'file', path: 'staff-web/vite.config.ts', label: 'staff-web Vite config' },
  { scope: 'staff-web', group: 'required_to_run', type: 'file', path: 'staff-web/index.html', label: 'staff-web HTML entrypoint' },
  { scope: 'staff-web', group: 'required_for_build_test_smoke', type: 'file', path: 'staff-web/tsconfig.json', label: 'staff-web TypeScript config' },
  { scope: 'staff-web', group: 'required_for_build_test_smoke', type: 'file', path: 'staff-web/vitest.config.ts', label: 'staff-web Vitest config' },
  { scope: 'staff-web', group: 'required_for_build_test_smoke', type: 'file', path: 'staff-web/scripts/live-smoke.mjs', label: 'staff-web live smoke harness' },
  { scope: 'staff-web', group: 'required_for_build_test_smoke', type: 'file', path: 'staff-web/src/shared/api/sdk.ts', label: 'staff-web shared SDK adapter' },
];

const CUSTOMER_WEB_REQUIREMENTS = [
  { scope: 'customer-web', group: 'required_to_run', type: 'file', path: 'customer-web/package.json', label: 'customer-web package manifest' },
  { scope: 'customer-web', group: 'required_to_run', type: 'file', path: 'customer-web/next.config.ts', label: 'customer-web Next.js config' },
  { scope: 'customer-web', group: 'required_to_run', type: 'directory', path: 'customer-web/src/app', label: 'customer-web App Router entrypoint' },
  { scope: 'customer-web', group: 'required_for_build_test_smoke', type: 'file', path: 'customer-web/tsconfig.json', label: 'customer-web TypeScript config' },
  { scope: 'customer-web', group: 'required_for_build_test_smoke', type: 'file', path: 'customer-web/vitest.config.ts', label: 'customer-web Vitest config' },
  { scope: 'customer-web', group: 'required_for_build_test_smoke', type: 'file', path: 'customer-web/playwright.config.ts', label: 'customer-web Playwright smoke config' },
  { scope: 'customer-web', group: 'required_for_build_test_smoke', type: 'file', path: 'customer-web/playwright.live.config.ts', label: 'customer-web Playwright live config' },
  { scope: 'customer-web', group: 'required_for_build_test_smoke', type: 'directory', path: 'customer-web/e2e', label: 'customer-web Playwright specs' },
  { scope: 'customer-web', group: 'required_for_build_test_smoke', type: 'file', path: 'customer-web/scripts/check-contract-governance.mjs', label: 'customer-web contract governance check' },
  { scope: 'customer-web', group: 'required_for_build_test_smoke', type: 'file', path: 'customer-web/scripts/verify-live-runtime.mjs', label: 'customer-web live runtime preflight' },
  { scope: 'customer-web', group: 'required_for_build_test_smoke', type: 'file', path: 'customer-web/src/lib/contracts/generated/restaurantpos-sdk.ts', label: 'customer-web copied generated SDK' },
  { scope: 'customer-web', group: 'required_for_build_test_smoke', type: 'file', path: 'customer-web/src/lib/contracts/generated/restaurantpos-enums.ts', label: 'customer-web copied generated enums' },
];

const ARTIFACT_REQUIREMENTS = [
  { scope: 'artifacts', group: 'required_for_build_test_smoke', type: 'directory', path: 'build/api-consumer', label: 'generated API consumer artifact root' },
  { scope: 'artifacts', group: 'required_for_build_test_smoke', type: 'file', path: 'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts', label: 'generated TypeScript SDK' },
  { scope: 'artifacts', group: 'required_for_build_test_smoke', type: 'file', path: 'build/api-consumer/sdk/typescript/restaurantpos-enums.ts', label: 'generated TypeScript enums' },
  { scope: 'artifacts', group: 'required_for_build_test_smoke', type: 'file', path: 'build/api-consumer/mutation-contracts.md', label: 'generated mutation contract artifact' },
  { scope: 'artifacts', group: 'required_for_build_test_smoke', type: 'file', path: 'storage/app/booking_release/openapi-v1.json', label: 'frozen OpenAPI release artifact' },
  { scope: 'artifacts', group: 'required_for_build_test_smoke', type: 'file', path: RELEASE_MANIFEST_SNAPSHOT_PATH, label: 'frozen release manifest snapshot' },
];

const HANDOVER_REQUIREMENTS = [
  { scope: 'handover', group: 'useful_for_handover', type: 'file', path: 'README.md', label: 'repo bootstrap overview' },
  { scope: 'handover', group: 'useful_for_handover', type: 'directory', path: 'docs/runbooks', label: 'operator runbooks directory' },
  { scope: 'handover', group: 'useful_for_handover', type: 'file', path: 'docs/runbooks/api-consumer-artifacts.md', label: 'API consumer artifact runbook' },
  { scope: 'handover', group: 'useful_for_handover', type: 'file', path: 'docs/runbooks/booking-local-windows-vscode-cmd-runbook.md', label: 'Windows local daily-use runbook' },
  { scope: 'handover', group: 'useful_for_handover', type: 'file', path: 'docs/runbooks/booking-release-packaging-runbook.md', label: 'release packaging runbook' },
  { scope: 'handover', group: 'useful_for_handover', type: 'file', path: 'customer-web/.env.example', label: 'customer-web environment template' },
  { scope: 'handover', group: 'useful_for_handover', type: 'file', path: 'customer-web/README.md', label: 'customer-web setup runbook' },
  { scope: 'handover', group: 'useful_for_handover', type: 'file', path: 'staff-web/.env.example', label: 'staff-web environment template' },
  { scope: 'handover', group: 'useful_for_handover', type: 'file', path: 'staff-web/STAFF_WEB_SETUP.md', label: 'staff-web setup runbook' },
  { scope: 'handover', group: 'useful_for_handover', type: 'file', path: 'staff-web/README.md', label: 'staff-web setup runbook' },
  { scope: 'handover', group: 'useful_for_handover', type: 'file', path: 'build/api-consumer/sdk/typescript/README.md', label: 'generated SDK scope note' },
];

const FRESHNESS_RULES = [
  {
    scope: 'artifacts',
    group: 'required_for_build_test_smoke',
    path: 'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts',
    depends_on: 'storage/app/booking_release/openapi-v1.json',
    label: 'generated TypeScript SDK freshness',
  },
  {
    scope: 'artifacts',
    group: 'required_for_build_test_smoke',
    path: 'build/api-consumer/sdk/typescript/restaurantpos-enums.ts',
    depends_on: 'storage/app/booking_release/openapi-v1.json',
    label: 'generated TypeScript enums freshness',
  },
  {
    scope: 'artifacts',
    group: 'required_for_build_test_smoke',
    path: 'build/api-consumer/mutation-contracts.md',
    depends_on: 'storage/app/booking_release/openapi-v1.json',
    label: 'generated mutation contract freshness',
  },
  {
    scope: 'artifacts',
    group: 'required_for_build_test_smoke',
    path: 'storage/app/booking_release/release_manifest_snapshot.json',
    depends_on: 'storage/app/booking_release/openapi-v1.json',
    label: 'frozen release manifest freshness (OpenAPI)',
  },
  {
    scope: 'artifacts',
    group: 'required_for_build_test_smoke',
    path: 'storage/app/booking_release/release_manifest_snapshot.json',
    depends_on: 'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts',
    label: 'frozen release manifest freshness (SDK)',
  },
  {
    scope: 'artifacts',
    group: 'required_for_build_test_smoke',
    path: 'storage/app/booking_release/release_manifest_snapshot.json',
    depends_on: 'build/api-consumer/sdk/typescript/restaurantpos-enums.ts',
    label: 'frozen release manifest freshness (enums)',
  },
  {
    scope: 'artifacts',
    group: 'required_for_build_test_smoke',
    path: 'storage/app/booking_release/release_manifest_snapshot.json',
    depends_on: 'build/api-consumer/mutation-contracts.md',
    label: 'frozen release manifest freshness (mutation contract)',
  },
];

const FRESHNESS_REMEDIATION = 'rerun php artisan booking:api-contract --write && php artisan booking:api-artifacts:generate && php artisan booking:release-manifest --write';

export function collectPackageIntegrityReport({ rootDir = process.cwd(), staffWebOnly = false } = {}) {
  const resolvedRootDir = path.resolve(rootDir);
  const requirements = staffWebOnly
    ? [...STAFF_WEB_REQUIREMENTS, ...ARTIFACT_REQUIREMENTS, ...HANDOVER_REQUIREMENTS]
    : [...BACKEND_REQUIREMENTS, ...STAFF_WEB_REQUIREMENTS, ...CUSTOMER_WEB_REQUIREMENTS, ...ARTIFACT_REQUIREMENTS, ...HANDOVER_REQUIREMENTS];

  const checks = requirements.map((requirement) => evaluateRequirement(resolvedRootDir, requirement));
  const missing = checks.filter((check) => !check.exists);
  const blockingMissing = missing.filter((check) => check.blocking);
  const advisoryMissing = missing.filter((check) => !check.blocking);
  const releaseManifestSnapshot = loadReleaseManifestSnapshot(resolvedRootDir);
  const hashCache = new Map();
  const freshnessChecks = FRESHNESS_RULES.map((rule) => evaluateFreshnessRule(resolvedRootDir, rule, releaseManifestSnapshot, hashCache))
    .filter((check) => !check.skipped);
  const stale = freshnessChecks.filter((check) => !check.ok);
  const blockingStale = stale.filter((check) => check.blocking);
  const advisoryStale = stale.filter((check) => !check.blocking);

  return {
    ok: blockingMissing.length === 0 && blockingStale.length === 0,
    decision: blockingMissing.length > 0 || blockingStale.length > 0 ? 'block' : advisoryMissing.length > 0 || advisoryStale.length > 0 ? 'warn' : 'pass',
    mode: staffWebOnly ? 'staff-web-only' : 'full',
    checked_at_utc: new Date().toISOString(),
    root_dir: resolvedRootDir,
    summary: {
      checked_count: checks.length,
      missing_count: missing.length,
      blocking_missing_count: blockingMissing.length,
      advisory_missing_count: advisoryMissing.length,
      freshness_check_count: freshnessChecks.length,
      stale_count: stale.length,
      blocking_stale_count: blockingStale.length,
      advisory_stale_count: advisoryStale.length,
    },
    groups: summarizeGroups(checks),
    missing,
    blocking_missing: blockingMissing,
    advisory_missing: advisoryMissing,
    stale,
    blocking_stale: blockingStale,
    advisory_stale: advisoryStale,
    checks,
    freshness_checks: freshnessChecks,
  };
}

function evaluateRequirement(rootDir, requirement) {
  const resolvedPath = path.resolve(rootDir, requirement.path);
  const exists = existsSync(resolvedPath);
  const kind = exists ? resolveEntryType(resolvedPath) : null;
  const kindMatches = exists && kind === requirement.type;
  const group = REQUIREMENT_GROUPS[requirement.group];

  return {
    scope: requirement.scope,
    group: requirement.group,
    group_label: group.label,
    blocking: group.blocking,
    label: requirement.label,
    path: requirement.path,
    resolved_path: resolvedPath,
    expected_type: requirement.type,
    actual_type: kind,
    exists: kindMatches,
    failure: kindMatches ? null : describeFailure(exists, requirement.type, kind),
  };
}

function evaluateFreshnessRule(rootDir, rule, releaseManifestSnapshot, hashCache) {
  const resolvedPath = path.resolve(rootDir, rule.path);
  const resolvedDependencyPath = path.resolve(rootDir, rule.depends_on);
  const targetExists = existsSync(resolvedPath);
  const dependencyExists = existsSync(resolvedDependencyPath);
  const targetType = targetExists ? resolveEntryType(resolvedPath) : null;
  const dependencyType = dependencyExists ? resolveEntryType(resolvedDependencyPath) : null;
  const group = REQUIREMENT_GROUPS[rule.group];

  if (!targetExists || !dependencyExists || targetType !== 'file' || dependencyType !== 'file') {
    return {
      scope: rule.scope,
      group: rule.group,
      group_label: group.label,
      blocking: group.blocking,
      label: rule.label,
      path: rule.path,
      resolved_path: resolvedPath,
      depends_on: rule.depends_on,
      dependency_resolved_path: resolvedDependencyPath,
      ok: true,
      skipped: true,
      failure: null,
    };
  }

  const snapshotFreshness = evaluateFreshnessFromSnapshot(rule, resolvedPath, resolvedDependencyPath, releaseManifestSnapshot, hashCache);
  if (snapshotFreshness) {
    return {
      scope: rule.scope,
      group: rule.group,
      group_label: group.label,
      blocking: group.blocking,
      label: rule.label,
      path: rule.path,
      resolved_path: resolvedPath,
      depends_on: rule.depends_on,
      dependency_resolved_path: resolvedDependencyPath,
      ok: snapshotFreshness.ok,
      skipped: false,
      failure: snapshotFreshness.failure,
    };
  }

  const targetStat = statSync(resolvedPath);
  const dependencyStat = statSync(resolvedDependencyPath);
  const ok = targetStat.mtimeMs >= dependencyStat.mtimeMs;

  return {
    scope: rule.scope,
    group: rule.group,
    group_label: group.label,
    blocking: group.blocking,
    label: rule.label,
    path: rule.path,
    resolved_path: resolvedPath,
    depends_on: rule.depends_on,
    dependency_resolved_path: resolvedDependencyPath,
    ok,
    skipped: false,
    failure: ok
      ? null
      : `stale generated artifact: ${rule.path} is older than ${rule.depends_on}; ${FRESHNESS_REMEDIATION}`,
  };
}

function evaluateFreshnessFromSnapshot(rule, resolvedPath, resolvedDependencyPath, releaseManifestSnapshot, hashCache) {
  if (!releaseManifestSnapshot) {
    return null;
  }

  const normalizedTargetPath = normalizeManifestPath(rule.path);
  const normalizedDependencyPath = normalizeManifestPath(rule.depends_on);
  const dependencyArtifact = releaseManifestSnapshot.artifactsByPath.get(normalizedDependencyPath);

  if (normalizedTargetPath === RELEASE_MANIFEST_SNAPSHOT_PATH) {
    if (!dependencyArtifact || typeof dependencyArtifact.sha256 !== 'string') {
      return {
        ok: false,
        failure: `stale generated artifact: ${rule.path} does not record ${rule.depends_on}; ${FRESHNESS_REMEDIATION}`,
      };
    }

    const dependencyHash = computeSha256(resolvedDependencyPath, hashCache);
    return dependencyArtifact.sha256 === dependencyHash
      ? { ok: true, failure: null }
      : {
          ok: false,
          failure: `stale generated artifact: ${rule.path} no longer matches ${rule.depends_on}; ${FRESHNESS_REMEDIATION}`,
        };
  }

  const targetArtifact = releaseManifestSnapshot.artifactsByPath.get(normalizedTargetPath);
  if (!targetArtifact || !dependencyArtifact || typeof targetArtifact.sha256 !== 'string' || typeof dependencyArtifact.sha256 !== 'string') {
    return null;
  }

  const targetHash = computeSha256(resolvedPath, hashCache);
  const dependencyHash = computeSha256(resolvedDependencyPath, hashCache);
  if (targetArtifact.sha256 !== targetHash || dependencyArtifact.sha256 !== dependencyHash) {
    return null;
  }

  const targetEpoch = Number(targetArtifact.modified_epoch);
  const dependencyEpoch = Number(dependencyArtifact.modified_epoch);
  if (!Number.isFinite(targetEpoch) || !Number.isFinite(dependencyEpoch)) {
    return null;
  }

  return targetEpoch >= dependencyEpoch
    ? { ok: true, failure: null }
    : {
        ok: false,
        failure: `stale generated artifact: ${rule.path} is older than ${rule.depends_on} in ${RELEASE_MANIFEST_SNAPSHOT_PATH}; ${FRESHNESS_REMEDIATION}`,
      };
}

function loadReleaseManifestSnapshot(rootDir) {
  const resolvedSnapshotPath = path.resolve(rootDir, RELEASE_MANIFEST_SNAPSHOT_PATH);
  if (!existsSync(resolvedSnapshotPath)) {
    return null;
  }

  try {
    const payload = JSON.parse(readFileSync(resolvedSnapshotPath, 'utf8'));
    const artifactsByPath = new Map();
    for (const artifact of Object.values(payload?.artifacts ?? {})) {
      if (!artifact || typeof artifact !== 'object' || typeof artifact.path !== 'string') {
        continue;
      }

      artifactsByPath.set(normalizeManifestPath(artifact.path), artifact);
    }

    return {
      artifactsByPath,
    };
  } catch {
    return null;
  }
}

function normalizeManifestPath(value) {
  return String(value).replace(/\\/g, '/');
}

function computeSha256(resolvedPath, hashCache) {
  if (hashCache.has(resolvedPath)) {
    return hashCache.get(resolvedPath);
  }

  const sha256 = createHash('sha256').update(readFileSync(resolvedPath)).digest('hex');
  hashCache.set(resolvedPath, sha256);
  return sha256;
}

function resolveEntryType(resolvedPath) {
  const stats = statSync(resolvedPath);
  return stats.isDirectory() ? 'directory' : 'file';
}

function describeFailure(exists, expectedType, actualType) {
  if (!exists) {
    return 'missing';
  }

  return `expected ${expectedType}, found ${actualType}`;
}

function summarizeGroups(checks) {
  return GROUP_ORDER.reduce((summary, groupKey) => {
    const groupChecks = checks.filter((check) => check.group === groupKey);
    if (groupChecks.length === 0) {
      return summary;
    }

    summary[groupKey] = {
      label: REQUIREMENT_GROUPS[groupKey].label,
      blocking: REQUIREMENT_GROUPS[groupKey].blocking,
      checked_count: groupChecks.length,
      missing_count: groupChecks.filter((check) => !check.exists).length,
    };

    return summary;
  }, {});
}

function renderHumanReport(report) {
  const lines = [
    `Package integrity (${report.mode})`,
    `root=${report.root_dir}`,
    '',
  ];

  for (const groupKey of GROUP_ORDER) {
    const groupChecks = report.checks.filter((check) => check.group === groupKey);
    if (groupChecks.length === 0) {
      continue;
    }

    const group = REQUIREMENT_GROUPS[groupKey];
    lines.push(`${group.label} (${group.blocking ? 'blocking' : 'advisory'})`);

    for (const check of groupChecks) {
      const status = check.exists ? 'OK' : 'MISSING';
      lines.push(`${status} [${check.scope}] ${check.path} - ${check.label}${check.failure ? ` (${check.failure})` : ''}`);
    }

    lines.push('');
  }

  if (report.freshness_checks.length > 0) {
    lines.push('freshness (blocking)');

    for (const check of report.freshness_checks) {
      const status = check.ok ? 'OK' : 'STALE';
      lines.push(`${status} [${check.scope}] ${check.path} - ${check.label}${check.failure ? ` (${check.failure})` : ''}`);
    }

    lines.push('');
  }

  lines.push(
    `decision=${report.decision} checked=${report.summary.checked_count} missing=${report.summary.missing_count} blocking_missing=${report.summary.blocking_missing_count} advisory_missing=${report.summary.advisory_missing_count} stale=${report.summary.stale_count} blocking_stale=${report.summary.blocking_stale_count} advisory_stale=${report.summary.advisory_stale_count}`
  );

  return `${lines.join('\n')}\n`;
}

function parseArguments(argv) {
  const rootDirArgument = argv.find((argument) => argument.startsWith('--root-dir='));

  return {
    json: argv.includes('--json'),
    staffWebOnly: argv.includes('--staff-web-only'),
    rootDir: rootDirArgument ? rootDirArgument.slice('--root-dir='.length) : null,
  };
}

function runCli() {
  const options = parseArguments(process.argv.slice(2));
  const report = collectPackageIntegrityReport({
    rootDir: options.rootDir ? path.resolve(process.cwd(), options.rootDir) : process.cwd(),
    staffWebOnly: options.staffWebOnly,
  });

  if (options.json) {
    process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
  } else {
    process.stdout.write(renderHumanReport(report));
  }

  process.exit(report.ok ? 0 : 1);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  runCli();
}
