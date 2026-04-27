import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';

import { buildReport, parseArgs } from './go-live-check.mjs';

const ENV_KEYS = [
  'APP_ENV',
  'APP_DEBUG',
  'CUSTOMER_AUTH_JWT_SECRET',
  'STAFF_AUTH_DATABASE_STORE_ENABLED',
  'STAFF_AUTH_ALLOW_ENV_FALLBACK',
  'STAFF_AUTH_ALLOW_ENV_FALLBACK_WHEN_DATABASE_STORE_UNAVAILABLE',
  'STAFF_AUTH_ALLOW_ROLE_NAME_FALLBACK',
  'STAFF_API_KEY',
  'STAFF_API_KEYS_JSON',
];

test('parseArgs captures go-live evidence options', () => {
  const options = parseArgs([
    '--target=production',
    '--allow-dirty=release ticket notes generated artifact',
    '--p0p1-evidence=REL-13',
    '--sql-bootstrap-evidence=build/sql-bootstrap.json',
    '--backup-restore-evidence=DR-9',
    '--rollback-plan=ROLLBACK-2',
    '--json',
  ]);

  assert.equal(options.target, 'production');
  assert.equal(options.allowDirty, 'release ticket notes generated artifact');
  assert.equal(options.p0p1Evidence, 'REL-13');
  assert.equal(options.sqlBootstrapEvidence, 'build/sql-bootstrap.json');
  assert.equal(options.backupRestoreEvidence, 'DR-9');
  assert.equal(options.rollbackPlan, 'ROLLBACK-2');
  assert.equal(options.json, true);
});

test('buildReport passes when every command and evidence source passes', () => {
  withCleanEnv(() => {
    const root = makeFixtureRoot();
    const evidence = path.join(root, 'evidence.json');
    writeFileSync(evidence, '{"ok":true}\n');

    const report = buildReport({
      rootDir: root,
      options: parseArgs([
        `--artifact-root=${path.join(root, 'artifacts')}`,
        `--p0p1-evidence=${evidence}`,
        `--sql-bootstrap-evidence=${evidence}`,
        `--backup-restore-evidence=${evidence}`,
        `--rollback-plan=${evidence}`,
      ]),
      runner: fakePassingRunner,
    });

    assert.equal(report.decision, 'pass');
    assert.equal(report.summary.no_go_count, 0);
    assert.ok(report.results.find((result) => result.key === 'staff_web_smoke'));
    assert.ok(report.results.find((result) => result.key === 'test_money'));

    rmSync(root, { recursive: true, force: true });
  });
});

test('unsafe production-like environment is a no-go', () => {
  withCleanEnv(() => {
    const root = makeFixtureRoot({
      env: [
        'APP_ENV=local',
        'APP_DEBUG=true',
        'CUSTOMER_AUTH_JWT_SECRET=short',
        'STAFF_AUTH_DATABASE_STORE_ENABLED=false',
      ].join('\n'),
    });
    const evidence = path.join(root, 'evidence.json');
    writeFileSync(evidence, '{"ok":true}\n');

    const report = buildReport({
      rootDir: root,
      options: parseArgs([
        `--artifact-root=${path.join(root, 'artifacts')}`,
        `--p0p1-evidence=${evidence}`,
        `--sql-bootstrap-evidence=${evidence}`,
        `--backup-restore-evidence=${evidence}`,
        `--rollback-plan=${evidence}`,
      ]),
      runner: fakePassingRunner,
    });

    const envResult = report.results.find((result) => result.key === 'environment_config');
    assert.equal(report.decision, 'no_go');
    assert.equal(envResult.status, 'fail');
    assert.equal(envResult.classification, 'unsafe_environment_config');

    rmSync(root, { recursive: true, force: true });
  });
});

test('sql bootstrap opt-in runs the existing mysql bootstrap wrapper', () => {
  withCleanEnv(() => {
    const root = makeFixtureRoot();
    const evidence = path.join(root, 'evidence.json');
    writeFileSync(evidence, '{"ok":true}\n');
    const commands = [];

    const report = buildReport({
      rootDir: root,
      options: parseArgs([
        `--artifact-root=${path.join(root, 'artifacts')}`,
        '--run-sql-bootstrap',
        `--p0p1-evidence=${evidence}`,
        `--backup-restore-evidence=${evidence}`,
        `--rollback-plan=${evidence}`,
      ]),
      runner: (command, cwd) => {
        commands.push(command.join(' '));
        return fakePassingRunner(command, cwd);
      },
    });

    assert.equal(report.results.find((result) => result.key === 'sql_bootstrap_verifier').status, 'pass');
    assert.ok(commands.some((command) => command.includes('tools/mysql/bootstrap_release.php')));

    rmSync(root, { recursive: true, force: true });
  });
});

test('booking doctor failure classifies combined database and redis blockers', () => {
  withCleanEnv(() => {
    const root = makeFixtureRoot();
    const evidence = path.join(root, 'evidence.json');
    writeFileSync(evidence, '{"ok":true}\n');

    const report = buildReport({
      rootDir: root,
      options: parseArgs([
        `--artifact-root=${path.join(root, 'artifacts')}`,
        `--p0p1-evidence=${evidence}`,
        `--sql-bootstrap-evidence=${evidence}`,
        `--backup-restore-evidence=${evidence}`,
        `--rollback-plan=${evidence}`,
      ]),
      runner: (command) => {
        if (command.includes('booking:doctor')) {
          return {
            exitCode: 1,
            stdout: 'SQLSTATE[HY000] [2002] No connection could be made; runtime.redis cache_store_redis failed',
            stderr: '',
          };
        }

        return fakePassingRunner(command);
      },
    });

    const doctor = report.results.find((result) => result.key === 'booking_doctor');
    assert.equal(doctor.status, 'fail');
    assert.equal(doctor.classification, 'runtime.db_redis');

    rmSync(root, { recursive: true, force: true });
  });
});

function makeFixtureRoot({ env = null } = {}) {
  const root = mkdtempSync(path.join(os.tmpdir(), 'go-live-check-'));
  for (const runbook of [
    'docs/runbooks/booking-backup-runbook.md',
    'docs/runbooks/booking-backup-restore-runbook.md',
    'docs/runbooks/booking-disaster-recovery-drill.md',
    'docs/runbooks/booking-deploy-runbook.md',
    'docs/runbooks/go-live-candidate-checklist.md',
  ]) {
    const target = path.join(root, runbook);
    mkdirSync(path.dirname(target), { recursive: true });
    writeFileSync(target, '# runbook\n');
  }

  mkdirSync(path.join(root, 'staff-web'), { recursive: true });
  writeFileSync(path.join(root, '.env'), env ?? [
    'APP_ENV=staging',
    'APP_DEBUG=false',
    'CUSTOMER_AUTH_JWT_SECRET=abcdefghijklmnopqrstuvwxyz1234567890',
    'STAFF_AUTH_DATABASE_STORE_ENABLED=true',
    'STAFF_AUTH_ALLOW_ENV_FALLBACK=false',
    'STAFF_AUTH_ALLOW_ENV_FALLBACK_WHEN_DATABASE_STORE_UNAVAILABLE=false',
    'STAFF_AUTH_ALLOW_ROLE_NAME_FALLBACK=false',
    'STAFF_API_KEY=',
    'STAFF_API_KEYS_JSON={}',
  ].join('\n'));

  return root;
}

function fakePassingRunner(command) {
  if (command.join(' ') === 'git status --porcelain') {
    return { exitCode: 0, stdout: '', stderr: '' };
  }

  return { exitCode: 0, stdout: '{"ok":true}\n', stderr: '' };
}

function withCleanEnv(callback) {
  const oldEnv = {};
  for (const key of ENV_KEYS) {
    oldEnv[key] = process.env[key];
    delete process.env[key];
  }

  try {
    callback();
  } finally {
    for (const key of ENV_KEYS) {
      if (oldEnv[key] === undefined) {
        delete process.env[key];
      } else {
        process.env[key] = oldEnv[key];
      }
    }
  }
}
