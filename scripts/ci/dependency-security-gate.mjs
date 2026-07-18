import { spawnSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const REPOSITORY_ROOT = path.resolve(SCRIPT_DIR, '../..');
const EVIDENCE_ROOT = path.join(REPOSITORY_ROOT, 'build/booking-ci/dependency-security');
const NPM_COMMAND = 'npm';

const WORKSPACES = {
  root: REPOSITORY_ROOT,
  'customer-web': path.join(REPOSITORY_ROOT, 'customer-web'),
  'staff-web': path.join(REPOSITORY_ROOT, 'staff-web'),
};

const AUDIT_ARGUMENTS = ['audit', '--omit=dev', '--audit-level=high', '--json'];
const SBOM_ARGUMENTS = ['sbom', '--omit=dev', '--sbom-format=cyclonedx'];

export function evaluateAuditPolicy(audit, workspace) {
  const vulnerabilities = audit?.metadata?.vulnerabilities;
  if (!vulnerabilities || typeof vulnerabilities !== 'object') {
    throw new Error(`npm audit did not return vulnerability metadata for ${workspace}.`);
  }

  const counts = Object.fromEntries(
    ['info', 'low', 'moderate', 'high', 'critical', 'total'].map((severity) => {
      const value = Number(vulnerabilities[severity] ?? 0);
      if (!Number.isInteger(value) || value < 0) {
        throw new Error(`npm audit returned an invalid ${severity} count for ${workspace}.`);
      }

      return [severity, value];
    }),
  );

  const directVulnerabilities = Object.entries(audit.vulnerabilities ?? {})
    .filter(([, vulnerability]) => vulnerability?.isDirect === true)
    .map(([name, vulnerability]) => ({
      name,
      severity: String(vulnerability.severity ?? 'unknown'),
      range: String(vulnerability.range ?? ''),
      fix_available: vulnerability.fixAvailable ?? false,
    }))
    .sort((left, right) => left.name.localeCompare(right.name));
  const blockingCount = counts.high + counts.critical;

  return {
    schema_version: 1,
    workspace,
    policy: {
      scope: 'production_dependencies',
      rule: 'high_and_critical_must_be_zero',
      blocked_severities: ['high', 'critical'],
      audit_command: 'npm audit --omit=dev --audit-level=high --json',
      sbom_format: 'CycloneDX JSON',
    },
    counts,
    direct_vulnerabilities: directVulnerabilities,
    blocking_count: blockingCount,
    decision: blockingCount === 0 ? 'pass' : 'block',
  };
}

function runNpm(workspaceDirectory, arguments_) {
  return spawnSync(NPM_COMMAND, arguments_, {
    cwd: workspaceDirectory,
    encoding: 'utf8',
    maxBuffer: 64 * 1024 * 1024,
    shell: process.platform === 'win32',
    windowsHide: true,
  });
}

function parseJsonOutput(result, label, workspace) {
  if (result.error) {
    throw new Error(`${label} could not start for ${workspace}: ${result.error.message}`);
  }

  try {
    return JSON.parse(result.stdout);
  } catch {
    throw new Error(`${label} did not return valid JSON for ${workspace} (exit ${result.status ?? 'unknown'}).`);
  }
}

function writeJson(filePath, payload) {
  writeFileSync(filePath, `${JSON.stringify(payload, null, 2)}\n`, 'utf8');
}

function collectWorkspaceEvidence(workspace) {
  const workspaceDirectory = WORKSPACES[workspace];
  const evidenceDirectory = path.join(EVIDENCE_ROOT, workspace);
  mkdirSync(evidenceDirectory, { recursive: true });

  const auditResult = runNpm(workspaceDirectory, AUDIT_ARGUMENTS);
  const audit = parseJsonOutput(auditResult, 'npm audit', workspace);
  const summary = {
    ...evaluateAuditPolicy(audit, workspace),
    generated_at_utc: new Date().toISOString(),
  };
  writeJson(path.join(evidenceDirectory, 'npm-audit-production.json'), audit);

  const sbomResult = runNpm(workspaceDirectory, SBOM_ARGUMENTS);
  if (sbomResult.status !== 0) {
    throw new Error(`npm sbom failed for ${workspace} (exit ${sbomResult.status ?? 'unknown'}).`);
  }

  const sbom = parseJsonOutput(sbomResult, 'npm sbom', workspace);
  writeJson(path.join(evidenceDirectory, 'sbom.cyclonedx.json'), sbom);
  writeJson(path.join(evidenceDirectory, 'policy-summary.json'), summary);

  return summary;
}

function selectedWorkspaces(arguments_) {
  if (arguments_.includes('--all')) {
    return Object.keys(WORKSPACES);
  }

  const workspaceArgument = arguments_.find((argument) => argument.startsWith('--workspace='));
  const workspace = workspaceArgument?.slice('--workspace='.length);
  if (!workspace || !Object.hasOwn(WORKSPACES, workspace)) {
    throw new Error(`Use --all or --workspace=${Object.keys(WORKSPACES).join('|')}.`);
  }

  return [workspace];
}

function runCli() {
  mkdirSync(EVIDENCE_ROOT, { recursive: true });

  let workspaces;
  try {
    workspaces = selectedWorkspaces(process.argv.slice(2));
  } catch (error) {
    process.stderr.write(`[dependency-security] ${error.message}\n`);
    process.exit(2);
  }

  const summaries = [];
  const failures = [];
  for (const workspace of workspaces) {
    try {
      const summary = collectWorkspaceEvidence(workspace);
      summaries.push(summary);
      if (summary.decision === 'block') {
        failures.push(`${workspace}: ${summary.blocking_count} unresolved High/Critical production advisories`);
      }
    } catch (error) {
      failures.push(`${workspace}: ${error.message}`);
    }
  }

  const aggregate = {
    schema_version: 1,
    generated_at_utc: new Date().toISOString(),
    decision: failures.length === 0 ? 'pass' : 'block',
    workspace_summaries: summaries,
    failures,
  };
  writeJson(path.join(EVIDENCE_ROOT, 'summary.json'), aggregate);
  process.stdout.write(`${JSON.stringify(aggregate, null, 2)}\n`);
  process.exit(failures.length === 0 ? 0 : 1);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  runCli();
}
