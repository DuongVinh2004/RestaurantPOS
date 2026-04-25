import net from 'node:net';
import path from 'node:path';
import process from 'node:process';
import { readFileSync, existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const DEFAULT_HEALTH_URL = 'http://127.0.0.1:8000/api/v1/health';
const DEFAULT_TCP_TIMEOUT_MS = 1500;
const DEFAULT_HTTP_TIMEOUT_MS = 4000;
const DEFAULT_DOCTOR_TIMEOUT_MS = 25000;

export function parseEnvFile(contents = '') {
  return String(contents)
    .replace(/^\uFEFF/, '')
    .split(/\r?\n/)
    .reduce((values, rawLine) => {
      const line = rawLine.trim();

      if (line === '' || line.startsWith('#')) {
        return values;
      }

      const normalizedLine = line.startsWith('export ') ? line.slice('export '.length) : line;
      const separatorIndex = normalizedLine.indexOf('=');

      if (separatorIndex <= 0) {
        return values;
      }

      const key = normalizedLine.slice(0, separatorIndex).trim();
      let value = normalizedLine.slice(separatorIndex + 1).trim();

      if (value.startsWith('"') && value.endsWith('"')) {
        value = value.slice(1, -1).replaceAll('\\n', '\n');
      } else if (value.startsWith('\'') && value.endsWith('\'')) {
        value = value.slice(1, -1);
      } else if (value.includes(' #')) {
        value = value.split(' #')[0].trimEnd();
      }

      if (key !== '') {
        values[key] = value;
      }

      return values;
    }, {});
}

export function buildHealthUrl(baseUrl) {
  const url = new URL(String(baseUrl).trim());
  const trimmedPath = url.pathname.replace(/\/+$/, '');

  if (/\/api\/v1\/health$/i.test(trimmedPath)) {
    url.pathname = trimmedPath;
  } else if (/\/api\/v1$/i.test(trimmedPath)) {
    url.pathname = `${trimmedPath}/health`;
  } else if (/\/api$/i.test(trimmedPath)) {
    url.pathname = `${trimmedPath}/v1/health`;
  } else if (trimmedPath === '') {
    url.pathname = '/api/v1/health';
  } else {
    url.pathname = `${trimmedPath}/api/v1/health`;
  }

  url.search = '';
  url.hash = '';

  return url.toString();
}

export function buildPreflightConfig({
  processEnv = process.env,
  envFileValues = {},
  repoRoot = process.cwd(),
  envFilePath = null,
} = {}) {
  const explicitHealthUrl = firstNonEmpty([
    processEnv.RUNTIME_PREFLIGHT_HEALTH_URL,
    envFileValues.RUNTIME_PREFLIGHT_HEALTH_URL,
  ]);
  const apiBaseUrl = firstNonEmpty([
    processEnv.RUNTIME_PREFLIGHT_API_URL,
    processEnv.STAFF_WEB_SMOKE_API_URL,
    processEnv.VITE_API_URL,
    envFileValues.RUNTIME_PREFLIGHT_API_URL,
    envFileValues.STAFF_WEB_SMOKE_API_URL,
    envFileValues.VITE_API_URL,
  ]);
  const appUrl = firstNonEmpty([
    processEnv.APP_URL,
    envFileValues.APP_URL,
  ]);
  const resolvedAppUrl = canUseAppUrlForHealth(appUrl) ? appUrl : null;
  const healthUrl = explicitHealthUrl
    ? normalizeAbsoluteUrl(explicitHealthUrl)
    : buildHealthUrl(apiBaseUrl ?? resolvedAppUrl ?? DEFAULT_HEALTH_URL);

  return {
    repoRoot: path.resolve(repoRoot),
    envFilePath,
    healthUrl,
    healthUrlSource: explicitHealthUrl
      ? 'explicit-health-url'
      : apiBaseUrl
        ? 'api-url'
        : resolvedAppUrl
          ? 'app-url'
          : 'default-local',
    dbHost: firstNonEmpty([processEnv.DB_HOST, envFileValues.DB_HOST]) ?? '127.0.0.1',
    dbPort: readPositiveInteger(firstNonEmpty([processEnv.DB_PORT, envFileValues.DB_PORT]), 3306),
    dbDatabase: firstNonEmpty([processEnv.DB_DATABASE, envFileValues.DB_DATABASE]) ?? 'restaurantdb',
    redisHost: firstNonEmpty([processEnv.REDIS_HOST, envFileValues.REDIS_HOST]) ?? '127.0.0.1',
    redisPort: readPositiveInteger(firstNonEmpty([processEnv.REDIS_PORT, envFileValues.REDIS_PORT]), 6379),
    requireRedisForBookingApi: readBooleanEnv(firstNonEmpty([
      processEnv.REQUIRE_REDIS_FOR_BOOKING_API,
      envFileValues.REQUIRE_REDIS_FOR_BOOKING_API,
    ])),
    tcpTimeoutMs: readPositiveInteger(firstNonEmpty([
      processEnv.RUNTIME_PREFLIGHT_TCP_TIMEOUT_MS,
      envFileValues.RUNTIME_PREFLIGHT_TCP_TIMEOUT_MS,
    ]), DEFAULT_TCP_TIMEOUT_MS),
    httpTimeoutMs: readPositiveInteger(firstNonEmpty([
      processEnv.RUNTIME_PREFLIGHT_HTTP_TIMEOUT_MS,
      envFileValues.RUNTIME_PREFLIGHT_HTTP_TIMEOUT_MS,
    ]), DEFAULT_HTTP_TIMEOUT_MS),
    doctorTimeoutMs: readPositiveInteger(firstNonEmpty([
      processEnv.RUNTIME_PREFLIGHT_DOCTOR_TIMEOUT_MS,
      envFileValues.RUNTIME_PREFLIGHT_DOCTOR_TIMEOUT_MS,
    ]), DEFAULT_DOCTOR_TIMEOUT_MS),
    doctorCommand: [resolvePhpBinary(processEnv), 'artisan', 'booking:doctor', '--json'],
  };
}

export function derivePreflightRecommendations(report) {
  const recommendations = [];
  const doctorRuntime = report.doctor?.runtime ?? {};
  const schedulerMessage = normalizeText(doctorRuntime.scheduler?.message);
  const doctorError = normalizeText(report.doctor?.error);

  if (!report.http?.ok) {
    recommendations.push('Start the backend HTTP server with `php artisan serve --host=127.0.0.1 --port=8000`, then rerun the preflight.');
  }

  if (!report.tcp?.db?.ok || doctorRuntime.db?.ok === false) {
    recommendations.push(`Start MySQL with \`powershell -ExecutionPolicy Bypass -File scripts\\ops\\start-local-mysql.ps1 -Restart\` or bring up your existing MySQL service, then verify \`.env\` \`DB_HOST\`, \`DB_PORT\`, and \`DB_DATABASE\` for \`${report.config?.dbDatabase ?? 'restaurantdb'}\`. If the schema or seed state drifted, rerun \`composer bootstrap:booking\`.`);
  }

  if (!report.tcp?.redis?.ok || doctorRuntime.redis?.ok === false) {
    recommendations.push('Start Redis with `powershell -ExecutionPolicy Bypass -File scripts\\ops\\start-local-redis.ps1 -Restart`, then rerun the preflight.');
  }

  if (doctorRuntime.scheduler?.ok === false) {
    if (schedulerMessage.includes('refused')) {
      recommendations.push('After Redis is reachable, keep `php artisan schedule:work` running and rerun the preflight once the scheduler heartbeat is fresh.');
    } else {
      recommendations.push('Keep `php artisan schedule:work` running for about a minute so the scheduler heartbeat turns green, then rerun the preflight.');
    }
  }

  if (doctorRuntime.outbox?.ok === false) {
    recommendations.push('Verify notification runtime health with `php artisan notifications:outbox-health --json` after MySQL, Redis, and scheduler are healthy.');
  }

  if (doctorError.includes('enoent') || doctorError.includes('not found')) {
    recommendations.push('Ensure the PHP CLI is installed and available in `PATH`, then rerun `php artisan booking:doctor --json`.');
  } else if (report.doctor?.hasParsedReport === false) {
    recommendations.push('Run `php artisan booking:doctor --json` directly and inspect stderr because the preflight could not parse the doctor report.');
  }

  return Array.from(new Set(recommendations));
}

export async function collectRuntimePreflightReport({
  config,
  probeHealth = probeHealthEndpoint,
  probeTcp = probeTcpPort,
  runDoctor = runBookingDoctor,
} = {}) {
  if (!config) {
    throw new Error('collectRuntimePreflightReport requires a config object.');
  }

  const [http, db, redis] = await Promise.all([
    probeHealth({ url: config.healthUrl, timeoutMs: config.httpTimeoutMs }),
    probeTcp({ label: 'mysql', host: config.dbHost, port: config.dbPort, timeoutMs: config.tcpTimeoutMs }),
    probeTcp({ label: 'redis', host: config.redisHost, port: config.redisPort, timeoutMs: config.tcpTimeoutMs }),
  ]);
  const doctor = runDoctor(config);
  const runtimeChecks = normalizeDoctorRuntimeChecks(doctor.parsed?.runtime);
  const blockingFailures = [
    !http.ok,
    !db.ok,
    !redis.ok,
    !doctor.parsed,
    doctor.parsed?.validation?.ok !== true,
    runtimeChecks.db.ok !== true,
    runtimeChecks.redis.ok !== true,
    runtimeChecks.scheduler.ok !== true,
    runtimeChecks.outbox.ok !== true,
  ];

  const report = {
    ok: blockingFailures.every((value) => value === false),
    decision: blockingFailures.every((value) => value === false) ? 'pass' : 'block',
    checked_at_utc: new Date().toISOString(),
    config: {
      repoRoot: config.repoRoot,
      envFilePath: config.envFilePath,
      healthUrl: config.healthUrl,
      healthUrlSource: config.healthUrlSource,
      dbHost: config.dbHost,
      dbPort: config.dbPort,
      dbDatabase: config.dbDatabase,
      redisHost: config.redisHost,
      redisPort: config.redisPort,
      requireRedisForBookingApi: config.requireRedisForBookingApi,
      doctorCommand: config.doctorCommand.join(' '),
    },
    http,
    tcp: {
      db,
      redis,
    },
    doctor: {
      ok: doctor.parsed?.ok === true,
      hasParsedReport: Boolean(doctor.parsed),
      exitCode: doctor.exitCode,
      signal: doctor.signal,
      error: doctor.error,
      validation: summarizeDoctorValidation(doctor.parsed?.validation),
      runtime: runtimeChecks,
      artifacts: doctor.parsed?.artifacts ?? null,
      stderr: doctor.stderr || null,
    },
  };

  report.recommendations = derivePreflightRecommendations(report);

  return report;
}

function canUseAppUrlForHealth(appUrl) {
  if (!appUrl) {
    return false;
  }

  try {
    const parsed = new URL(String(appUrl).trim());
    const trimmedPath = parsed.pathname.replace(/\/+$/, '');
    return parsed.port !== '' || trimmedPath !== '' && trimmedPath !== '/';
  } catch {
    return false;
  }
}

function normalizeAbsoluteUrl(value) {
  return new URL(String(value).trim()).toString();
}

function firstNonEmpty(values) {
  return values.find((value) => typeof value === 'string' && value.trim() !== '') ?? null;
}

function resolvePhpBinary(processEnv = process.env) {
  const configured = firstNonEmpty([processEnv.PHP_BIN]);
  if (configured) {
    return configured;
  }

  if (process.platform === 'win32') {
    const windowsCandidates = [
      path.join(processEnv.USERPROFILE ?? '', '.config', 'herd-lite', 'bin', 'php.exe'),
      'C:\\xampp\\php\\php.exe',
    ];

    const existingCandidate = windowsCandidates.find((candidate) => candidate && existsSync(candidate));
    if (existingCandidate) {
      return existingCandidate;
    }
  }

  return 'php';
}

function readPositiveInteger(value, fallback) {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

function readBooleanEnv(value) {
  if (typeof value !== 'string') {
    return false;
  }

  const normalized = value.trim().toLowerCase();
  return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on';
}

async function probeHealthEndpoint({ url, timeoutMs }) {
  const startedAt = Date.now();
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(url, {
      headers: {
        Accept: 'application/json',
      },
      signal: controller.signal,
    });
    const text = await response.text();
    const payload = tryParseJson(text);
    const summary = describeHealthPayload(payload);

    return {
      ok: response.ok,
      kind: response.ok ? 'success' : 'http-error',
      url,
      status: response.status,
      durationMs: Date.now() - startedAt,
      message: response.ok
        ? summary ?? 'backend health responded with HTTP 200.'
        : summary ?? `${response.status} ${response.statusText}`.trim(),
    };
  } catch (error) {
    const causeMessage = error?.cause instanceof Error
      ? error.cause.message
      : typeof error?.cause === 'string'
        ? error.cause
        : '';
    const errorMessage = error instanceof Error ? error.message : String(error);
    const joinedMessage = [errorMessage, causeMessage]
      .filter((value, index, values) => value && values.indexOf(value) === index)
      .join(': ');

    return {
      ok: false,
      kind: 'network-error',
      url,
      status: null,
      durationMs: Date.now() - startedAt,
      message: error?.name === 'AbortError'
        ? `timed out after ${timeoutMs}ms`
        : joinedMessage,
    };
  } finally {
    clearTimeout(timer);
  }
}

async function probeTcpPort({ label, host, port, timeoutMs }) {
  const startedAt = Date.now();

  return new Promise((resolve) => {
    const socket = new net.Socket();
    let settled = false;

    const finalize = (result) => {
      if (settled) {
        return;
      }

      settled = true;
      socket.destroy();
      resolve({
        label,
        host,
        port,
        durationMs: Date.now() - startedAt,
        ...result,
      });
    };

    socket.setTimeout(timeoutMs);
    socket.once('connect', () => finalize({
      ok: true,
      message: `reachable at ${host}:${port}`,
    }));
    socket.once('timeout', () => finalize({
      ok: false,
      message: `timed out after ${timeoutMs}ms`,
    }));
    socket.once('error', (error) => finalize({
      ok: false,
      message: error instanceof Error ? error.message : String(error),
    }));
    socket.connect(port, host);
  });
}

function runBookingDoctor(config) {
  const startedAt = Date.now();
  const result = spawnSync(config.doctorCommand[0], config.doctorCommand.slice(1), {
    cwd: config.repoRoot,
    encoding: 'utf8',
    timeout: config.doctorTimeoutMs,
  });

  return {
    exitCode: typeof result.status === 'number' ? result.status : null,
    signal: result.signal ?? null,
    durationMs: Date.now() - startedAt,
    error: result.error instanceof Error ? result.error.message : null,
    stdout: typeof result.stdout === 'string' ? result.stdout.trim() : '',
    stderr: typeof result.stderr === 'string' ? result.stderr.trim() : '',
    parsed: tryParseJsonDocument(result.stdout),
  };
}

function tryParseJsonDocument(value) {
  const text = typeof value === 'string' ? value.trim() : '';

  if (text === '') {
    return null;
  }

  const direct = tryParseJson(text);
  if (direct) {
    return direct;
  }

  const firstBrace = text.indexOf('{');
  return firstBrace >= 0 ? tryParseJson(text.slice(firstBrace)) : null;
}

function tryParseJson(value) {
  try {
    return JSON.parse(value);
  } catch {
    return null;
  }
}

function normalizeDoctorRuntimeChecks(runtime = null) {
  return {
    db: normalizeDoctorRuntimeCheck(runtime?.db),
    redis: normalizeDoctorRuntimeCheck(runtime?.redis),
    scheduler: normalizeDoctorRuntimeCheck(runtime?.scheduler),
    outbox: normalizeDoctorRuntimeCheck(runtime?.outbox),
  };
}

function normalizeDoctorRuntimeCheck(check) {
  return {
    ok: typeof check?.ok === 'boolean' ? check.ok : null,
    message: typeof check?.message === 'string' ? check.message : null,
  };
}

function summarizeDoctorValidation(validation) {
  if (!validation || typeof validation !== 'object') {
    return null;
  }

  const checks = validation.checks && typeof validation.checks === 'object'
    ? Object.values(validation.checks)
    : [];

  return {
    ok: validation.ok === true,
    errorCount: Array.isArray(validation.errors) ? validation.errors.length : 0,
    warningCount: Array.isArray(validation.warnings) ? validation.warnings.length : 0,
    checkCount: checks.length,
  };
}

function describeHealthPayload(payload) {
  if (!payload || typeof payload !== 'object') {
    return null;
  }

  const checks = payload.checks && typeof payload.checks === 'object' ? payload.checks : null;
  if (!checks) {
    return typeof payload.message === 'string' ? payload.message : null;
  }

  const failingChecks = [];

  if (checks.db?.ok === false) {
    failingChecks.push(`db=${normalizeText(checks.db.reason) || 'db_unavailable'}`);
  }

  if (checks.redis?.ok === false) {
    const reason = normalizeText(checks.redis.reason) || 'redis_unavailable';
    const error = normalizeText(checks.redis.error);
    failingChecks.push(`redis=${reason}${error ? ` (${error})` : ''}`);
  }

  if (checks.scheduler?.ok === false) {
    const reason = normalizeText(checks.scheduler.reason) || 'scheduler_unavailable';
    failingChecks.push(`scheduler=${reason}`);
  }

  if (checks.disk?.ok === false) {
    failingChecks.push(`disk=${normalizeText(checks.disk.reason) || 'disk_probe_failed'}`);
  }

  if (failingChecks.length === 0) {
    return normalizeText(payload.status) ? `health=${normalizeText(payload.status)}` : null;
  }

  return `health=${normalizeText(payload.status) || 'fail'} ${failingChecks.join('; ')}`;
}

function normalizeText(value) {
  return typeof value === 'string' ? value.trim().toLowerCase() : '';
}

function renderHumanReport(report) {
  const lines = [
    'Runtime preflight',
    `repo=${report.config.repoRoot}`,
    `env=${report.config.envFilePath ?? '(not found)'}`,
    `health_url=${report.config.healthUrl} (${report.config.healthUrlSource})`,
    '',
    `backend HTTP: ${report.http.ok ? 'PASS' : 'FAIL'} ${report.http.message}`,
    `MySQL TCP: ${report.tcp.db.ok ? 'PASS' : 'FAIL'} ${report.tcp.db.host}:${report.tcp.db.port} ${report.tcp.db.message}`,
    `Redis TCP: ${report.tcp.redis.ok ? 'PASS' : 'FAIL'} ${report.tcp.redis.host}:${report.tcp.redis.port} ${report.tcp.redis.message}`,
    `booking:doctor: ${report.doctor.ok ? 'PASS' : 'FAIL'} exit=${report.doctor.exitCode ?? 'n/a'} db=${renderDoctorCheck(report.doctor.runtime.db)} redis=${renderDoctorCheck(report.doctor.runtime.redis)} scheduler=${renderDoctorCheck(report.doctor.runtime.scheduler)} outbox=${renderDoctorCheck(report.doctor.runtime.outbox)}`,
  ];

  if (report.doctor.error) {
    lines.push(`doctor_error=${report.doctor.error}`);
  }

  if (report.recommendations.length > 0) {
    lines.push('');
    lines.push('Recommended next steps');
    for (const recommendation of report.recommendations) {
      lines.push(`- ${recommendation}`);
    }
  }

  lines.push('');
  lines.push(`decision=${report.decision}`);

  return `${lines.join('\n')}\n`;
}

function renderDoctorCheck(check) {
  if (check.ok === true) {
    return 'ok';
  }

  if (check.ok === false) {
    return check.message ? `fail(${check.message})` : 'fail';
  }

  return 'unknown';
}

function parseArguments(argv) {
  const envFileArgument = argv.find((argument) => argument.startsWith('--env-file='));

  return {
    json: argv.includes('--json'),
    envFilePath: envFileArgument ? envFileArgument.slice('--env-file='.length) : '.env',
  };
}

async function runCli() {
  const options = parseArguments(process.argv.slice(2));
  const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
  const repoRoot = path.resolve(scriptDirectory, '..', '..');
  const envFilePath = path.resolve(repoRoot, options.envFilePath);
  const envFileValues = existsSync(envFilePath)
    ? parseEnvFile(readFileSync(envFilePath, 'utf8'))
    : {};
  const config = buildPreflightConfig({
    processEnv: process.env,
    envFileValues,
    repoRoot,
    envFilePath: existsSync(envFilePath) ? envFilePath : null,
  });
  const report = await collectRuntimePreflightReport({ config });

  if (options.json) {
    process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
  } else {
    process.stdout.write(renderHumanReport(report));
  }

  process.exit(report.ok ? 0 : 1);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  runCli().catch((error) => {
    const message = error instanceof Error ? error.stack ?? error.message : String(error);
    process.stderr.write(`${message}\n`);
    process.exit(1);
  });
}
