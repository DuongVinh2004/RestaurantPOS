import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const DEFAULT_ARTIFACT_ROOT = 'build/booking-go-live';
const DEFAULT_ENV_FILE = '.env';

const REQUIRED_RUNBOOKS = [
  'docs/runbooks/booking-backup-runbook.md',
  'docs/runbooks/booking-backup-restore-runbook.md',
  'docs/runbooks/booking-disaster-recovery-drill.md',
  'docs/runbooks/booking-deploy-runbook.md',
  'docs/runbooks/go-live-candidate-checklist.md',
];

const COMMAND_STEPS = [
  {
    key: 'booking_doctor',
    group: 'runtime',
    label: 'MySQL/Redis/scheduler/outbox runtime gate',
    command: ['php', 'artisan', 'booking:doctor', '--strict', '--json'],
    noGo: 'booking:doctor fail',
    runtimeSensitive: true,
  },
  {
    key: 'outbox_health',
    group: 'runtime',
    label: 'Notification outbox health',
    command: ['php', 'artisan', 'notifications:outbox-health', '--json'],
    noGo: 'outbox health fail',
    runtimeSensitive: true,
  },
  {
    key: 'route_gate',
    group: 'release_contract',
    label: 'Locked route inventory gate',
    command: ['php', 'artisan', 'booking:route-gate', '--json'],
    noGo: 'route-gate fail',
  },
  {
    key: 'release_manifest',
    group: 'release_contract',
    label: 'Release manifest',
    command: ['php', 'artisan', 'booking:release-manifest', '--json'],
    noGo: 'release-manifest fail',
  },
  {
    key: 'deploy_check_preflight',
    group: 'runtime',
    label: 'Deploy preflight guardrail',
    command: ['php', 'artisan', 'booking:deploy-check', '--mode=preflight', '--strict', '--json'],
    noGo: 'deploy-check fail',
    runtimeSensitive: true,
  },
  {
    key: 'package_verify',
    group: 'release_contract',
    label: 'Package integrity verifier',
    command: ['npm', 'run', 'verify:package'],
    noGo: 'package verify fail',
  },
  {
    key: 'test_security',
    group: 'targeted_ladders',
    label: 'Security/RBAC/branch isolation ladder',
    command: ['composer', 'test:security'],
    noGo: 'cross-branch data leak',
  },
  {
    key: 'test_orders',
    group: 'targeted_ladders',
    label: 'Order lifecycle ladder',
    command: ['composer', 'test:orders'],
    noGo: 'idempotency gaps on production mutation routes',
  },
  {
    key: 'test_kitchen',
    group: 'targeted_ladders',
    label: 'Kitchen/KDS ladder',
    command: ['composer', 'test:kitchen'],
    noGo: 'KDS/order handoff tests fail',
  },
  {
    key: 'test_money',
    group: 'targeted_ladders',
    label: 'Money/cashier/refund ladder',
    command: ['composer', 'test:money'],
    noGo: 'money flow tests not run',
  },
  {
    key: 'test_inventory',
    group: 'targeted_ladders',
    label: 'Inventory/purchasing ladder',
    command: ['composer', 'test:inventory'],
    noGo: 'inventory ladder fail',
  },
  {
    key: 'test_release_contract',
    group: 'release_contract',
    label: 'Release contract ladder',
    command: ['composer', 'test:release-contract'],
    noGo: 'missing cashier-shift FK verifier',
  },
  {
    key: 'staff_web_build',
    group: 'staff_web',
    label: 'Staff-web production build',
    command: ['npm', 'run', 'build'],
    cwd: 'staff-web',
    noGo: 'staff-web build fail',
  },
  {
    key: 'staff_web_smoke',
    group: 'staff_web',
    label: 'Staff-web day-1 live smoke',
    command: ['npm', 'run', 'smoke:live'],
    cwd: 'staff-web',
    noGo: 'staff-web day-1 smoke not run',
    runtimeSensitive: true,
  },
];

export function parseArgs(argv) {
  const options = {
    target: 'staging',
    artifactRoot: DEFAULT_ARTIFACT_ROOT,
    envFile: DEFAULT_ENV_FILE,
    json: false,
    allowDirty: '',
    p0p1Evidence: '',
    sqlBootstrapEvidence: '',
    runSqlBootstrap: false,
    backupRestoreEvidence: '',
    rollbackPlan: '',
  };

  for (const rawArg of argv) {
    const arg = String(rawArg);
    if (arg === '--json') {
      options.json = true;
    } else if (arg === '--run-sql-bootstrap') {
      options.runSqlBootstrap = true;
    } else if (arg.startsWith('--target=')) {
      options.target = arg.slice('--target='.length).trim() || options.target;
    } else if (arg.startsWith('--artifact-root=')) {
      options.artifactRoot = arg.slice('--artifact-root='.length).trim() || options.artifactRoot;
    } else if (arg.startsWith('--env-file=')) {
      options.envFile = arg.slice('--env-file='.length).trim() || options.envFile;
    } else if (arg.startsWith('--allow-dirty=')) {
      options.allowDirty = arg.slice('--allow-dirty='.length).trim();
    } else if (arg.startsWith('--p0p1-evidence=')) {
      options.p0p1Evidence = arg.slice('--p0p1-evidence='.length).trim();
    } else if (arg.startsWith('--sql-bootstrap-evidence=')) {
      options.sqlBootstrapEvidence = arg.slice('--sql-bootstrap-evidence='.length).trim();
    } else if (arg.startsWith('--backup-restore-evidence=')) {
      options.backupRestoreEvidence = arg.slice('--backup-restore-evidence='.length).trim();
    } else if (arg.startsWith('--rollback-plan=')) {
      options.rollbackPlan = arg.slice('--rollback-plan='.length).trim();
    } else if (arg === '--help' || arg === '-h') {
      options.help = true;
    } else if (arg.trim() !== '') {
      options.unknownArgs = [...(options.unknownArgs ?? []), arg];
    }
  }

  return options;
}

export function usage() {
  return [
    'Usage: composer release:go-live-check -- [options]',
    '',
    'Required candidate evidence options:',
    '  --p0p1-evidence=<path-or-ticket>          P0/P1 fixed or explicitly mitigated evidence.',
    '  --backup-restore-evidence=<path-or-ticket> Backup/restore drill evidence.',
    '  --rollback-plan=<path-or-ticket>          Rollback package and procedure evidence.',
    '',
    'SQL-first verifier options:',
    '  --run-sql-bootstrap                       Run tools/mysql/bootstrap_release.php against the configured DB.',
    '  --sql-bootstrap-evidence=<path-or-ticket> Use existing SQL bootstrap/verifier evidence instead.',
    '',
    'Other options:',
    '  --target=staging|limited-production|production',
    '  --allow-dirty=<reason>                    Allow dirty git artifacts only with a release-ticket reason.',
    '  --env-file=.env',
    '  --artifact-root=build/booking-go-live',
    '  --json',
  ].join('\n');
}

export function buildReport({ rootDir = process.cwd(), options, runner = runCommand } = {}) {
  const resolvedRoot = path.resolve(rootDir);
  const artifactRoot = path.resolve(resolvedRoot, options.artifactRoot);
  mkdirSync(artifactRoot, { recursive: true });

  const envFilePath = resolvePath(resolvedRoot, options.envFile);
  const envValues = loadEnvFile(envFilePath);
  const context = {
    rootDir: resolvedRoot,
    artifactRoot,
    envFilePath,
    envValues,
    options,
    runner,
  };

  const results = [];
  results.push(evaluateGitStatus(context));
  results.push(evaluateEnvironmentConfig(context));
  results.push(evaluateEvidence('p0p1_evidence', 'P0/P1 fixed or mitigated evidence', options.p0p1Evidence, 'P0/P1 fixed or explicitly mitigated', context));
  results.push(evaluateSqlBootstrap(context));
  results.push(evaluateRunbookInventory(context));

  for (const step of COMMAND_STEPS) {
    results.push(executeCommandStep(step, context));
  }

  results.push(evaluateEvidence('backup_restore_evidence', 'Backup/restore drill evidence', options.backupRestoreEvidence, 'backup/restore drill not run', context));
  results.push(evaluateEvidence('rollback_plan', 'Rollback plan evidence', options.rollbackPlan, 'rollback plan missing', context));

  const summary = summarizeResults(results);
  const report = {
    ok: summary.no_go_count === 0,
    decision: summary.no_go_count === 0 ? 'pass' : 'no_go',
    target: options.target,
    checked_at_utc: new Date().toISOString(),
    root_dir: resolvedRoot,
    artifact_root: artifactRoot,
    env_file: envFilePath,
    summary,
    no_go_conditions: results.filter((result) => result.no_go),
    results,
  };

  writeFileSync(path.join(artifactRoot, 'go-live-check-latest.json'), `${JSON.stringify(report, null, 2)}\n`);
  writeFileSync(path.join(artifactRoot, 'go-live-check-latest.md'), renderMarkdown(report));

  return report;
}

function evaluateGitStatus(context) {
  const step = {
    key: 'git_status',
    group: 'repository',
    label: 'Git status clean or documented dirty artifacts',
    noGo: 'dirty git status without release-ticket note',
  };
  const outcome = context.runner(['git', 'status', '--porcelain'], context.rootDir);
  writeStepLogs(step.key, context.artifactRoot, outcome);

  const dirtyLines = outcome.stdout.split(/\r?\n/).filter((line) => line.trim() !== '');
  if (outcome.exitCode !== 0) {
    return failedResult(step, 'command_failed', 'Unable to read git status.', outcome);
  }

  if (dirtyLines.length === 0) {
    return passedResult(step, 'Git status is clean.', { dirty_count: 0 });
  }

  if (context.options.allowDirty !== '') {
    return {
      ...baseResult(step),
      status: 'pass_with_warning',
      ok: true,
      no_go: false,
      classification: 'documented_dirty_artifacts',
      message: 'Git status is dirty, but a release-ticket reason was supplied.',
      meta: {
        dirty_count: dirtyLines.length,
        dirty_paths: dirtyLines,
        reason: context.options.allowDirty,
      },
    };
  }

  return {
    ...baseResult(step),
    status: 'fail',
    ok: false,
    no_go: step.noGo,
    classification: 'repository_dirty',
    message: 'Git status is dirty and --allow-dirty=<reason> was not supplied.',
    meta: {
      dirty_count: dirtyLines.length,
      dirty_paths: dirtyLines,
    },
  };
}

function evaluateEnvironmentConfig(context) {
  const step = {
    key: 'environment_config',
    group: 'environment',
    label: 'APP_ENV/APP_DEBUG/auth environment checks',
    noGo: 'go-live environment config unsafe',
  };
  const getEnv = (key, fallback = '') => {
    if (process.env[key] !== undefined && process.env[key] !== '') {
      return String(process.env[key]);
    }

    return context.envValues[key] ?? fallback;
  };

  const appEnv = getEnv('APP_ENV', 'production').trim();
  const appDebug = getEnv('APP_DEBUG', '').trim();
  const customerJwtSecret = getEnv('CUSTOMER_AUTH_JWT_SECRET', '').trim();
  const staffDatabaseStoreEnabled = getEnv('STAFF_AUTH_DATABASE_STORE_ENABLED', 'true').trim();
  const staffEnvFallback = getEnv('STAFF_AUTH_ALLOW_ENV_FALLBACK', 'false').trim();
  const staffUnavailableFallback = getEnv('STAFF_AUTH_ALLOW_ENV_FALLBACK_WHEN_DATABASE_STORE_UNAVAILABLE', 'false').trim();
  const staffRoleNameFallback = getEnv('STAFF_AUTH_ALLOW_ROLE_NAME_FALLBACK', 'false').trim();
  const staffApiKey = getEnv('STAFF_API_KEY', '').trim();
  const staffApiKeysJson = getEnv('STAFF_API_KEYS_JSON', '{}').trim();

  const errors = [];
  const warnings = [];
  const productionLike = ['staging', 'production', 'limited-production'];

  if (!productionLike.includes(appEnv)) {
    errors.push(`APP_ENV must be production-like for go-live evidence; got ${appEnv || '(empty)'}.`);
  }

  if (!isFalseLike(appDebug)) {
    errors.push('APP_DEBUG must be false for go-live evidence.');
  }

  if (customerJwtSecret.length < 32 || /^change[-_]?me$/i.test(customerJwtSecret)) {
    errors.push('CUSTOMER_AUTH_JWT_SECRET must be a non-placeholder secret of at least 32 characters.');
  }

  if (isFalseLike(staffDatabaseStoreEnabled)) {
    errors.push('STAFF_AUTH_DATABASE_STORE_ENABLED must stay true so staff auth uses DB-backed hashed keys.');
  }

  if (!isFalseLike(staffEnvFallback) || !isFalseLike(staffUnavailableFallback) || !isFalseLike(staffRoleNameFallback)) {
    errors.push('Staff auth env/unavailable/role-name fallbacks must be disabled for production-like go-live evidence.');
  }

  if (staffApiKey !== '' || (staffApiKeysJson !== '' && staffApiKeysJson !== '{}')) {
    warnings.push('Env-backed staff API key values are present; booking:doctor/deploy-check must reject this in production-like environments.');
  }

  if (errors.length > 0) {
    return {
      ...baseResult(step),
      status: 'fail',
      ok: false,
      no_go: step.noGo,
      classification: 'unsafe_environment_config',
      message: errors.join(' '),
      meta: {
        app_env: appEnv,
        app_debug: redactBoolean(appDebug),
        customer_auth_jwt_secret_present: customerJwtSecret !== '',
        staff_auth_database_store_enabled: redactBoolean(staffDatabaseStoreEnabled),
        warnings,
      },
    };
  }

  return {
    ...baseResult(step),
    status: warnings.length > 0 ? 'pass_with_warning' : 'pass',
    ok: true,
    no_go: false,
    classification: warnings.length > 0 ? 'environment_warning' : 'environment_safe',
    message: warnings.length > 0 ? warnings.join(' ') : 'Production-like environment config checks passed.',
    meta: {
      app_env: appEnv,
      app_debug: redactBoolean(appDebug),
      customer_auth_jwt_secret_present: customerJwtSecret !== '',
      staff_auth_database_store_enabled: redactBoolean(staffDatabaseStoreEnabled),
      warnings,
    },
  };
}

function evaluateEvidence(key, label, reference, noGo, context, group = 'manual_evidence') {
  const step = { key, group, label, noGo };
  const normalized = String(reference ?? '').trim();
  if (normalized === '') {
    return {
      ...baseResult(step),
      status: 'fail',
      ok: false,
      no_go: noGo,
      classification: 'manual_evidence_missing',
      message: `${label} is required for a go-live candidate.`,
    };
  }

  const resolved = evidenceReference(normalized, context.rootDir);
  if (resolved.kind === 'file' && !existsSync(resolved.path)) {
    return {
      ...baseResult(step),
      status: 'fail',
      ok: false,
      no_go: noGo,
      classification: 'manual_evidence_file_missing',
      message: `${label} file does not exist: ${normalized}`,
      meta: { reference: normalized, resolved_path: resolved.path },
    };
  }

  return passedResult(step, `${label} supplied.`, { reference: normalized, reference_kind: resolved.kind, resolved_path: resolved.path ?? null });
}

function evaluateSqlBootstrap(context) {
  const step = {
    key: 'sql_bootstrap_verifier',
    group: 'sql_first',
    label: 'SQL-first bootstrap and verifier',
    noGo: 'missing cashier-shift FK verifier',
  };

  if (context.options.sqlBootstrapEvidence !== '') {
    return evaluateEvidence(step.key, step.label, context.options.sqlBootstrapEvidence, step.noGo, context, step.group);
  }

  if (!context.options.runSqlBootstrap) {
    return {
      ...baseResult(step),
      status: 'fail',
      ok: false,
      no_go: step.noGo,
      classification: 'sql_bootstrap_not_run',
      message: 'SQL bootstrap/verifier proof is required. Re-run with --run-sql-bootstrap against a scratch DB or supply --sql-bootstrap-evidence=<path-or-ticket>.',
    };
  }

  const outcome = context.runner(['php', 'tools/mysql/bootstrap_release.php', `--env-file=${context.options.envFile}`, '--json', '--skip-create-db'], context.rootDir);
  writeStepLogs(step.key, context.artifactRoot, outcome);

  if (outcome.exitCode === 0) {
    return passedResult(step, 'SQL-first bootstrap completed and tools/mysql/verify_release_contract.sql passed.', {
      command: 'php tools/mysql/bootstrap_release.php --json --skip-create-db',
      stdout_log: `${step.key}.stdout.log`,
      stderr_log: `${step.key}.stderr.log`,
    });
  }

  return failedResult(step, classifyFailure(step, outcome), 'SQL-first bootstrap/verifier failed.', outcome);
}

function evaluateRunbookInventory(context) {
  const step = {
    key: 'runbook_inventory',
    group: 'runbooks',
    label: 'Backup/restore/rollback runbooks exist',
    noGo: 'backup/restore/rollback runbooks missing',
  };
  const missing = REQUIRED_RUNBOOKS.filter((runbook) => !existsSync(path.join(context.rootDir, runbook)));

  if (missing.length > 0) {
    return {
      ...baseResult(step),
      status: 'fail',
      ok: false,
      no_go: step.noGo,
      classification: 'runbook_missing',
      message: 'One or more required go-live runbooks are missing.',
      meta: { missing },
    };
  }

  return passedResult(step, 'Required go-live runbooks exist.', { runbooks: REQUIRED_RUNBOOKS });
}

function executeCommandStep(step, context) {
  const cwd = step.cwd ? path.join(context.rootDir, step.cwd) : context.rootDir;
  const outcome = context.runner(step.command, cwd);
  writeStepLogs(step.key, context.artifactRoot, outcome);

  if (outcome.exitCode === 0) {
    return passedResult(step, `${step.label} passed.`, {
      command: commandToString(step.command),
      cwd: path.relative(context.rootDir, cwd) || '.',
      stdout_log: `${step.key}.stdout.log`,
      stderr_log: `${step.key}.stderr.log`,
    });
  }

  return failedResult(step, classifyFailure(step, outcome), `${step.label} failed.`, outcome);
}

function failedResult(step, classification, message, outcome) {
  return {
    ...baseResult(step),
    status: 'fail',
    ok: false,
    no_go: step.noGo,
    classification,
    message,
    meta: {
      command: step.command ? commandToString(step.command) : undefined,
      exit_code: outcome.exitCode,
      stdout_log: `${step.key}.stdout.log`,
      stderr_log: `${step.key}.stderr.log`,
      stdout_tail: tail(outcome.stdout),
      stderr_tail: tail(outcome.stderr),
    },
  };
}

function passedResult(step, message, meta = {}) {
  return {
    ...baseResult(step),
    status: 'pass',
    ok: true,
    no_go: false,
    classification: 'passed',
    message,
    meta,
  };
}

function baseResult(step) {
  return {
    key: step.key,
    group: step.group,
    label: step.label,
  };
}

function classifyFailure(step, outcome) {
  const output = `${outcome.stdout}\n${outcome.stderr}`;
  if (step.runtimeSensitive) {
    const hasDbSignal = /sqlstate|mysql|mariadb|database runtime|db_select_1|database ping|connection refused|2002/i.test(output);
    const hasRedisSignal = /redis|phpredis|6379|runtime\.redis|cache_store_redis/i.test(output);
    if (step.key === 'booking_doctor' && hasDbSignal && hasRedisSignal) {
      return 'runtime.db_redis';
    }
    if (hasDbSignal) {
      return 'runtime.db';
    }
    if (hasRedisSignal) {
      return 'runtime.redis';
    }
    if (/scheduler|heartbeat|runtime\.scheduler/i.test(output)) {
      return 'runtime.scheduler';
    }
    if (/outbox|notification_outbox|runtime\.outbox/i.test(output)) {
      return 'runtime.outbox';
    }
    return 'runtime.failure';
  }

  if (step.key === 'deploy_check_preflight') {
    return 'deploy_check_failed';
  }
  if (step.key === 'test_money') {
    return 'money_flow_tests_failed';
  }
  if (step.key === 'test_orders' || step.key === 'test_release_contract') {
    return 'idempotency_or_contract_gap';
  }
  if (step.key === 'test_security') {
    return 'security_or_branch_scope_gap';
  }

  return 'command_failed';
}

export function summarizeResults(results) {
  const groups = {};
  for (const result of results) {
    const group = result.group ?? 'other';
    groups[group] ??= { total: 0, pass: 0, warning: 0, fail: 0 };
    groups[group].total += 1;
    if (result.status === 'pass') {
      groups[group].pass += 1;
    } else if (result.status === 'pass_with_warning') {
      groups[group].warning += 1;
    } else {
      groups[group].fail += 1;
    }
  }

  return {
    check_count: results.length,
    pass_count: results.filter((result) => result.status === 'pass').length,
    warning_count: results.filter((result) => result.status === 'pass_with_warning').length,
    fail_count: results.filter((result) => result.status === 'fail').length,
    no_go_count: results.filter((result) => result.no_go).length,
    groups,
  };
}

function renderMarkdown(report) {
  const lines = [
    '# Go-Live Candidate Gate',
    '',
    `- Decision: \`${report.decision}\``,
    `- Target: \`${report.target}\``,
    `- Checked at UTC: \`${report.checked_at_utc}\``,
    `- Artifact root: \`${report.artifact_root}\``,
    '',
    '## Summary',
    '',
    `- Checks: \`${report.summary.check_count}\``,
    `- Pass: \`${report.summary.pass_count}\``,
    `- Warnings: \`${report.summary.warning_count}\``,
    `- Fail: \`${report.summary.fail_count}\``,
    `- No-go: \`${report.summary.no_go_count}\``,
    '',
    '## Results',
    '',
    '| Group | Check | Status | Classification | No-go |',
    '| --- | --- | --- | --- | --- |',
  ];

  for (const result of report.results) {
    lines.push(`| ${escapePipe(result.group)} | ${escapePipe(result.label)} | \`${result.status}\` | \`${result.classification}\` | ${escapePipe(result.no_go || '')} |`);
  }

  if (report.no_go_conditions.length > 0) {
    lines.push('', '## No-Go Conditions', '');
    for (const result of report.no_go_conditions) {
      lines.push(`- \`${result.key}\`: ${result.no_go} - ${result.message}`);
    }
  }

  return `${lines.join('\n')}\n`;
}

function writeStepLogs(key, artifactRoot, outcome) {
  writeFileSync(path.join(artifactRoot, `${key}.stdout.log`), outcome.stdout ?? '');
  writeFileSync(path.join(artifactRoot, `${key}.stderr.log`), outcome.stderr ?? '');
}

export function runCommand(command, cwd) {
  const [program, ...args] = command;
  if (process.platform === 'win32') {
    const result = spawnSync(command.map(quoteShellArg).join(' '), {
      cwd,
      encoding: 'utf8',
      shell: true,
      maxBuffer: 1024 * 1024 * 20,
    });

    return {
      exitCode: typeof result.status === 'number' ? result.status : 1,
      stdout: result.stdout ?? '',
      stderr: result.stderr ?? (result.error ? String(result.error.message ?? result.error) : ''),
    };
  }

  const result = spawnSync(program, args, {
    cwd,
    encoding: 'utf8',
    maxBuffer: 1024 * 1024 * 20,
  });

  return {
    exitCode: typeof result.status === 'number' ? result.status : 1,
    stdout: result.stdout ?? '',
    stderr: result.stderr ?? (result.error ? String(result.error.message ?? result.error) : ''),
  };
}

function loadEnvFile(envFilePath) {
  if (!existsSync(envFilePath)) {
    return {};
  }

  const values = {};
  for (const line of readFileSync(envFilePath, 'utf8').split(/\r?\n/)) {
    const trimmed = line.trim();
    if (trimmed === '' || trimmed.startsWith('#') || !trimmed.includes('=')) {
      continue;
    }
    const index = trimmed.indexOf('=');
    const key = trimmed.slice(0, index).trim();
    let value = trimmed.slice(index + 1).trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    values[key] = value;
  }

  return values;
}

function evidenceReference(reference, rootDir) {
  if (/^https?:\/\//i.test(reference) || /^[A-Z]+-\d+$/i.test(reference)) {
    return { kind: 'external' };
  }

  if (/[\\/.]/.test(reference)) {
    return { kind: 'file', path: resolvePath(rootDir, reference) };
  }

  return { kind: 'external' };
}

function resolvePath(rootDir, maybeRelativePath) {
  if (path.isAbsolute(maybeRelativePath)) {
    return maybeRelativePath;
  }

  return path.resolve(rootDir, maybeRelativePath);
}

function isFalseLike(value) {
  return ['false', '0', 'off', 'no'].includes(String(value).trim().toLowerCase());
}

function redactBoolean(value) {
  const normalized = String(value ?? '').trim();
  if (normalized === '') {
    return '(empty)';
  }

  return isFalseLike(normalized) ? 'false' : 'true';
}

function tail(value) {
  return String(value ?? '').split(/\r?\n/).filter(Boolean).slice(-20).join('\n');
}

function commandToString(command) {
  return command.map((part) => (/\s/.test(part) ? JSON.stringify(part) : part)).join(' ');
}

function quoteShellArg(value) {
  const normalized = String(value);
  if (/^[A-Za-z0-9_./:=@+-]+$/.test(normalized)) {
    return normalized;
  }

  return `"${normalized.replace(/"/g, '\\"')}"`;
}

function escapePipe(value) {
  return String(value).replace(/\|/g, '\\|');
}

function main() {
  const options = parseArgs(process.argv.slice(2));
  if (options.help) {
    process.stdout.write(`${usage()}\n`);
    return 0;
  }

  if ((options.unknownArgs ?? []).length > 0) {
    process.stderr.write(`Unknown argument(s): ${options.unknownArgs.join(', ')}\n\n${usage()}\n`);
    return 1;
  }

  const report = buildReport({ options });
  if (options.json) {
    process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
  } else {
    process.stdout.write(renderMarkdown(report));
  }

  return report.ok ? 0 : 1;
}

const isCli = process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isCli) {
  process.exitCode = main();
}
