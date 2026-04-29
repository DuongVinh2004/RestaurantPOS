import net from 'node:net';
import path from 'node:path';
import process from 'node:process';
import { existsSync, mkdirSync, readFileSync, unlinkSync, writeFileSync, openSync, closeSync } from 'node:fs';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDirectory, '..', '..');
const logsDir = path.join(repoRoot, 'storage', 'logs');
const runtimeDir = path.join(repoRoot, 'storage', 'app', 'local-runtime');
const statePath = path.join(runtimeDir, 'state.json');
const composeFile = path.join(repoRoot, 'docker-compose.testing.yml');
const localRuntimePs1 = path.join(repoRoot, 'scripts', 'ops', 'local-runtime.ps1');
const startLocalMysqlPs1 = path.join(repoRoot, 'scripts', 'ops', 'start-local-mysql.ps1');
const startLocalRedisPs1 = path.join(repoRoot, 'scripts', 'ops', 'start-local-redis.ps1');

const DEFAULT_BACKEND_HOST = '127.0.0.1';
const DEFAULT_BACKEND_PORT = 8000;
const DEFAULT_DB_PORT = 3306;
const DEFAULT_REDIS_PORT = 6379;

function parseEnvFile(contents = '') {
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

function readEnvValues() {
  const envPath = path.join(repoRoot, '.env');
  const examplePath = path.join(repoRoot, '.env.example');

  if (existsSync(envPath)) {
    return {
      envPath,
      values: parseEnvFile(readFileSync(envPath, 'utf8')),
    };
  }

  if (existsSync(examplePath)) {
    return {
      envPath: null,
      values: parseEnvFile(readFileSync(examplePath, 'utf8')),
    };
  }

  return {
    envPath: null,
    values: {},
  };
}

function firstNonEmpty(values) {
  return values.find((value) => typeof value === 'string' && value.trim() !== '') ?? null;
}

function envValue(values, key, fallback = '') {
  return firstNonEmpty([process.env[key], values[key]]) ?? fallback;
}

function readPositiveInteger(value, fallback) {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

function isLoopbackHost(host) {
  return ['127.0.0.1', 'localhost', '::1'].includes(String(host).trim().toLowerCase());
}

function buildServeConfig(values) {
  const appUrl = envValue(values, 'APP_URL', 'http://127.0.0.1:8000');
  let host = DEFAULT_BACKEND_HOST;
  let port = DEFAULT_BACKEND_PORT;

  try {
    const parsed = new URL(appUrl);
    const pinsEndpoint = /:\/\/[^/]+:\d+(?:\/|$)/.test(appUrl)
      || parsed.pathname.replace(/\/+$/, '') !== '';

    if (pinsEndpoint && parsed.hostname) {
      host = parsed.hostname;
    }

    if (/:\/\/[^/]+:\d+(?:\/|$)/.test(appUrl) && parsed.port !== '') {
      port = Number(parsed.port);
    }
  } catch {
    host = DEFAULT_BACKEND_HOST;
    port = DEFAULT_BACKEND_PORT;
  }

  const baseUrl = `http://${host}:${port}`;

  return {
    host,
    port,
    baseUrl,
    healthUrl: `${baseUrl}/api/v1/health`,
  };
}

function buildRuntimeConfig() {
  const env = readEnvValues();
  const values = env.values;

  return {
    envPath: env.envPath,
    dbHost: envValue(values, 'DB_HOST', '127.0.0.1'),
    dbPort: readPositiveInteger(envValue(values, 'DB_PORT', String(DEFAULT_DB_PORT)), DEFAULT_DB_PORT),
    dbDatabase: envValue(values, 'DB_DATABASE', 'restaurantdb'),
    dbPassword: envValue(values, 'DB_PASSWORD', '123456'),
    redisHost: envValue(values, 'REDIS_HOST', '127.0.0.1'),
    redisPort: readPositiveInteger(envValue(values, 'REDIS_PORT', String(DEFAULT_REDIS_PORT)), DEFAULT_REDIS_PORT),
    php: resolvePhpBinary(),
    serve: buildServeConfig(values),
  };
}

function resolvePhpBinary() {
  const configured = firstNonEmpty([process.env.PHP_BIN]);
  if (configured) {
    return configured;
  }

  if (process.platform === 'win32') {
    const windowsCandidates = [
      path.join(process.env.USERPROFILE ?? '', '.config', 'herd-lite', 'bin', 'php.exe'),
      'C:\\xampp\\php\\php.exe',
    ];
    const existing = windowsCandidates.find((candidate) => candidate && existsSync(candidate));
    if (existing) {
      return existing;
    }
  }

  return 'php';
}

function ensureDirectories() {
  mkdirSync(logsDir, { recursive: true });
  mkdirSync(runtimeDir, { recursive: true });
}

function run(command, args = [], options = {}) {
  const result = spawnSync(command, args, {
    cwd: repoRoot,
    encoding: 'utf8',
    windowsHide: true,
    ...options,
  });

  return {
    ok: result.status === 0,
    status: result.status,
    stdout: typeof result.stdout === 'string' ? result.stdout.trim() : '',
    stderr: typeof result.stderr === 'string' ? result.stderr.trim() : '',
    error: result.error instanceof Error ? result.error.message : null,
  };
}

function commandFailureSummary(result) {
  return [result.error, result.stderr, result.stdout]
    .filter((part) => typeof part === 'string' && part.trim() !== '')
    .join('\n')
    .trim();
}

async function probeTcp(host, port, timeoutMs = 1500) {
  return new Promise((resolve) => {
    const socket = new net.Socket();
    let settled = false;

    const done = (ok, message) => {
      if (settled) {
        return;
      }

      settled = true;
      socket.destroy();
      resolve({ ok, message });
    };

    socket.setTimeout(timeoutMs);
    socket.once('connect', () => done(true, `reachable at ${host}:${port}`));
    socket.once('timeout', () => done(false, `timed out after ${timeoutMs}ms`));
    socket.once('error', (error) => done(false, error instanceof Error ? error.message : String(error)));
    socket.connect(port, host);
  });
}

async function waitForTcp(host, port, timeoutMs = 30000) {
  const deadline = Date.now() + timeoutMs;
  let lastMessage = '';

  while (Date.now() < deadline) {
    const result = await probeTcp(host, port, 1000);
    if (result.ok) {
      return true;
    }

    lastMessage = result.message;
    await sleep(500);
  }

  throw new Error(`Timed out waiting for ${host}:${port}. Last error: ${lastMessage}`);
}

async function redisPing(host, port, timeoutMs = 1500) {
  return new Promise((resolve) => {
    const socket = new net.Socket();
    let settled = false;

    const done = (ok) => {
      if (settled) {
        return;
      }

      settled = true;
      socket.destroy();
      resolve(ok);
    };

    socket.setTimeout(timeoutMs);
    socket.once('connect', () => {
      socket.write('*1\r\n$4\r\nPING\r\n');
    });
    socket.once('data', (chunk) => done(chunk.toString('utf8').startsWith('+PONG')));
    socket.once('timeout', () => done(false));
    socket.once('error', () => done(false));
    socket.connect(port, host);
  });
}

async function waitForRedis(host, port, timeoutMs = 30000) {
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    if (await redisPing(host, port, 1000)) {
      return true;
    }

    await sleep(500);
  }

  throw new Error(`Timed out waiting for Redis PING on ${host}:${port}.`);
}

async function waitForHttp(url, timeoutMs = 30000) {
  const deadline = Date.now() + timeoutMs;
  let lastMessage = '';

  while (Date.now() < deadline) {
    try {
      const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        signal: AbortSignal.timeout(3000),
      });
      if (response.ok) {
        return true;
      }

      lastMessage = `HTTP ${response.status}`;
    } catch (error) {
      lastMessage = error instanceof Error ? error.message : String(error);
    }

    await sleep(500);
  }

  throw new Error(`Timed out waiting for ${url}. Last error: ${lastMessage}`);
}

function sleep(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

function dockerAvailable() {
  const result = run('docker', ['info', '--format', '{{.ServerVersion}}']);
  return result.ok && result.stdout.trim() !== '';
}

function composeArgs(...args) {
  return ['compose', '-f', composeFile, ...args];
}

function ensureComposeFile() {
  if (!existsSync(composeFile)) {
    throw new Error(`Docker Compose runtime file is missing: ${composeFile}`);
  }
}

async function startDockerService(service, config) {
  ensureComposeFile();

  if (!dockerAvailable()) {
    throw new Error('Docker is installed but the Docker daemon is not reachable. Start Docker Desktop or your CI Docker service and retry.');
  }

  const result = run('docker', composeArgs('up', '-d', service));
  if (!result.ok) {
    throw new Error(commandFailureSummary(result) || `docker compose up -d ${service} failed.`);
  }

  if (service === 'mysql') {
    await waitForTcp(config.dbHost, config.dbPort, 45000);
    await waitForDockerMysql(config);
  } else if (service === 'redis') {
    await waitForTcp(config.redisHost, config.redisPort, 30000);
    await waitForRedis(config.redisHost, config.redisPort, 30000);
  }
}

async function waitForDockerMysql(config, timeoutMs = 60000) {
  const deadline = Date.now() + timeoutMs;
  let lastMessage = '';

  while (Date.now() < deadline) {
    const ping = run('docker', composeArgs(
      'exec',
      '-T',
      'mysql',
      'mysqladmin',
      'ping',
      '-h',
      '127.0.0.1',
      '-P',
      '3306',
      '-u',
      'root',
      `--password=${config.dbPassword}`,
    ));

    if (ping.ok) {
      return true;
    }

    lastMessage = commandFailureSummary(ping);
    await sleep(1000);
  }

  throw new Error(`Timed out waiting for Docker MySQL readiness. Last error: ${lastMessage}`);
}

function runWindowsDependencyScript(scriptPath) {
  if (process.platform !== 'win32' || !existsSync(scriptPath)) {
    return {
      ok: false,
      skipped: true,
      stdout: '',
      stderr: '',
      error: 'Windows dependency script unavailable on this platform.',
    };
  }

  return run('powershell.exe', [
    '-ExecutionPolicy',
    'Bypass',
    '-File',
    scriptPath,
  ]);
}

async function ensureDependency({ label, host, port, service, windowsScript, config }) {
  if (!isLoopbackHost(host)) {
    const result = await probeTcp(host, port, 2000);
    if (result.ok) {
      console.log(`${label}: external service reachable at ${host}:${port}.`);
      return { label, mode: 'external' };
    }

    throw new Error(`${label} is configured for non-local host ${host}:${port} and is not reachable. Start that external service before using the repo-local runtime lane.`);
  }

  const existing = await probeTcp(host, port, 1000);
  if (existing.ok) {
    console.log(`${label}: service already reachable at ${host}:${port}.`);
    return { label, mode: 'existing' };
  }

  const failures = [];
  const windowsResult = runWindowsDependencyScript(windowsScript);
  if (windowsResult.ok) {
    await waitForTcp(host, port, 30000);
    if (service === 'redis') {
      await waitForRedis(host, port, 30000);
    }
    console.log(`${label}: started via Windows local helper.`);
    return { label, mode: 'windows-helper' };
  }

  if (!windowsResult.skipped) {
    failures.push(`Windows helper failed: ${commandFailureSummary(windowsResult)}`);
  }

  try {
    await startDockerService(service, config);
    console.log(`${label}: started via Docker Compose service [${service}].`);
    return { label, mode: 'docker-compose' };
  } catch (error) {
    failures.push(error instanceof Error ? error.message : String(error));
  }

  throw new Error([
    `${label} could not be started for ${host}:${port}.`,
    ...failures.filter(Boolean),
  ].join('\n'));
}

function openLog(pathname) {
  return openSync(pathname, 'a');
}

function spawnDetached(command, args, logPrefix) {
  const stdout = openLog(path.join(logsDir, `${logPrefix}.log`));
  const stderr = openLog(path.join(logsDir, `${logPrefix}.err.log`));
  const child = spawn(command, args, {
    cwd: repoRoot,
    detached: true,
    stdio: ['ignore', stdout, stderr],
    windowsHide: true,
  });

  child.unref();
  closeSync(stdout);
  closeSync(stderr);

  return child.pid;
}

async function startBackend(config) {
  const existing = await probeTcp(config.serve.host, config.serve.port, 1000);
  if (existing.ok) {
    await waitForHttp(config.serve.healthUrl, 5000);
    console.log(`Backend: reusing healthy server at ${config.serve.baseUrl}.`);
    return { pid: null, mode: 'existing' };
  }

  const pid = spawnDetached(config.php, [
    'artisan',
    'serve',
    `--host=${config.serve.host}`,
    `--port=${config.serve.port}`,
  ], 'local-runtime-backend');

  await waitForTcp(config.serve.host, config.serve.port, 30000);
  await waitForHttp(config.serve.healthUrl, 30000);
  console.log(`Backend: started ${config.serve.baseUrl} with PID ${pid}.`);

  return { pid, mode: 'managed' };
}

async function startScheduler(config) {
  const pid = spawnDetached(config.php, ['artisan', 'schedule:work'], 'local-runtime-scheduler');

  await sleep(1500);
  assertProcessAlive(pid, 'Scheduler worker');
  touchSchedulerHeartbeat(config);
  console.log(`Scheduler: started schedule:work with PID ${pid}.`);

  return { pid, mode: 'managed' };
}

function touchSchedulerHeartbeat(config) {
  const result = run(config.php, ['artisan', 'booking:ops-heartbeat:touch', 'scheduler', '--json']);
  if (!result.ok) {
    throw new Error(`Scheduler heartbeat could not be primed. ${commandFailureSummary(result)}`);
  }
}

function assertProcessAlive(pid, label) {
  if (!pid) {
    return;
  }

  try {
    process.kill(pid, 0);
  } catch {
    throw new Error(`${label} exited immediately. Inspect storage/logs/local-runtime-*.err.log.`);
  }
}

function readState() {
  if (!existsSync(statePath)) {
    return null;
  }

  try {
    return JSON.parse(readFileSync(statePath, 'utf8'));
  } catch {
    return null;
  }
}

function writeState(state) {
  writeFileSync(statePath, `${JSON.stringify(state, null, 2)}\n`, 'utf8');
}

function removeState() {
  if (existsSync(statePath)) {
    unlinkSync(statePath);
  }
}

function stopPid(pid, label) {
  if (!pid) {
    return false;
  }

  if (process.platform === 'win32') {
    const result = run('taskkill.exe', ['/PID', String(pid), '/T', '/F']);
    if (result.ok) {
      console.log(`${label}: stopped PID ${pid}.`);
      return true;
    }

    console.log(`${label}: PID ${pid} was not stopped by taskkill (${commandFailureSummary(result)}).`);
    return false;
  }

  try {
    process.kill(-pid, 'SIGTERM');
  } catch {
    try {
      process.kill(pid, 'SIGTERM');
    } catch {
      return false;
    }
  }

  console.log(`${label}: stopped PID ${pid}.`);
  return true;
}

function stopDockerServices(services = []) {
  const uniqueServices = Array.from(new Set(services.filter(Boolean)));
  if (uniqueServices.length === 0) {
    return;
  }

  const result = run('docker', composeArgs('stop', ...uniqueServices));
  if (!result.ok) {
    console.log(`Docker Compose services were not stopped cleanly: ${commandFailureSummary(result)}`);
    return;
  }

  console.log(`Docker Compose: stopped ${uniqueServices.join(', ')}.`);
}

async function up() {
  ensureDirectories();

  const previousState = readState();
  if (previousState) {
    stopPid(previousState.schedulerPid, 'Previous scheduler');
    stopPid(previousState.backendPid, 'Previous backend');
    removeState();
  }

  const config = buildRuntimeConfig();
  const dependencyResults = [];

  dependencyResults.push(await ensureDependency({
    label: 'MySQL',
    host: config.dbHost,
    port: config.dbPort,
    service: 'mysql',
    windowsScript: startLocalMysqlPs1,
    config,
  }));
  dependencyResults.push(await ensureDependency({
    label: 'Redis',
    host: config.redisHost,
    port: config.redisPort,
    service: 'redis',
    windowsScript: startLocalRedisPs1,
    config,
  }));

  const backend = await startBackend(config);
  const scheduler = await startScheduler(config);
  const dockerServices = dependencyResults
    .filter((result) => result.mode === 'docker-compose')
    .map((result) => result.label.toLowerCase());

  writeState({
    createdAtUtc: new Date().toISOString(),
    backendPid: backend.mode === 'managed' ? backend.pid : null,
    schedulerPid: scheduler.pid,
    dockerServices,
    config: {
      dbHost: config.dbHost,
      dbPort: config.dbPort,
      redisHost: config.redisHost,
      redisPort: config.redisPort,
      backendBaseUrl: config.serve.baseUrl,
    },
  });

  console.log('Local runtime is up.');
  console.log(`MySQL: reachable at ${config.dbHost}:${config.dbPort}.`);
  console.log(`Redis: reachable at ${config.redisHost}:${config.redisPort}.`);
  console.log(`Backend: ${config.serve.baseUrl}.`);
  console.log('Scheduler: heartbeat primed; keep schedule:work running for sustained runtime health.');
  console.log('Next: run `composer bootstrap:booking`, then `npm run runtime:preflight`.');
}

function down() {
  ensureDirectories();

  const state = readState();
  if (!state) {
    console.log('No local runtime state file was found. Nothing managed by scripts/ops/local-runtime.mjs was stopped.');
    return;
  }

  stopPid(state.schedulerPid, 'Scheduler');
  stopPid(state.backendPid, 'Backend');
  stopDockerServices(state.dockerServices ?? []);
  removeState();
  console.log('Local runtime is down.');
}

async function restart() {
  down();
  await up();
}

async function main() {
  const action = process.argv[2] ?? 'up';

  if (!['up', 'down', 'restart'].includes(action)) {
    throw new Error(`Unsupported action [${action}]. Use up, down, or restart.`);
  }

  if (action === 'up') {
    await up();
  } else if (action === 'down') {
    down();
  } else {
    await restart();
  }
}

main().catch((error) => {
  const message = error instanceof Error ? error.message : String(error);
  process.stderr.write(`${message}\n`);
  process.exit(1);
});
