import { describe, expect, it } from 'vitest';
import {
  buildHealthUrl,
  buildPreflightConfig,
  derivePreflightRecommendations,
  parseEnvFile,
} from '../../scripts/ops/local-runtime-preflight.mjs';

describe('local runtime preflight helpers', () => {
  it('parses dotenv style files with comments, export prefixes, and quoted values', () => {
    expect(parseEnvFile(`
      # comment
      APP_URL=http://127.0.0.1:8000
      export DB_HOST=127.0.0.1
      DB_PASSWORD="secret value"
      REDIS_HOST=127.0.0.1 # inline comment
    `)).toEqual({
      APP_URL: 'http://127.0.0.1:8000',
      DB_HOST: '127.0.0.1',
      DB_PASSWORD: 'secret value',
      REDIS_HOST: '127.0.0.1',
    });
  });

  it('normalizes API and app URLs into the public health endpoint', () => {
    expect(buildHealthUrl('http://127.0.0.1:8000/api/v1')).toBe('http://127.0.0.1:8000/api/v1/health');
    expect(buildHealthUrl('http://127.0.0.1:8000')).toBe('http://127.0.0.1:8000/api/v1/health');
    expect(buildHealthUrl('http://127.0.0.1:8000/subdir')).toBe('http://127.0.0.1:8000/subdir/api/v1/health');
  });

  it('prefers explicit smoke/runtime env over dotenv values and keeps the local default health target when APP_URL is too weak', () => {
    expect(buildPreflightConfig({
      processEnv: {
        STAFF_WEB_SMOKE_API_URL: 'https://staging.example.test/api/v1',
        DB_HOST: '10.0.0.5',
        REDIS_PORT: '6381',
        REQUIRE_REDIS_FOR_BOOKING_API: '1',
      },
      envFileValues: {
        STAFF_WEB_SMOKE_API_URL: 'http://127.0.0.1:8000/api/v1',
        DB_HOST: '127.0.0.1',
        REDIS_PORT: '6379',
      },
      repoRoot: 'C:/repo',
      envFilePath: 'C:/repo/.env',
    })).toMatchObject({
      repoRoot: 'C:\\repo',
      envFilePath: 'C:/repo/.env',
      healthUrl: 'https://staging.example.test/api/v1/health',
      healthUrlSource: 'api-url',
      dbHost: '10.0.0.5',
      redisPort: 6381,
      requireRedisForBookingApi: true,
    });

    expect(buildPreflightConfig({
      processEnv: {
        APP_URL: 'http://localhost',
      },
      envFileValues: {},
    }).healthUrl).toBe('http://127.0.0.1:8000/api/v1/health');
  });

  it('derives actionable next steps from runtime blockers without duplicate advice', () => {
    expect(derivePreflightRecommendations({
      http: {
        ok: false,
      },
      tcp: {
        db: {
          ok: false,
        },
        redis: {
          ok: false,
        },
      },
      config: {
        dbDatabase: 'restaurantdb',
      },
      doctor: {
        parsed: {
          ok: false,
        },
        runtime: {
          db: {
            ok: false,
            message: 'connection refused',
          },
          redis: {
            ok: false,
            message: 'connection refused',
          },
          scheduler: {
            ok: false,
            message: 'No connection could be made because the target machine actively refused it',
          },
          outbox: {
            ok: false,
            message: 'Outbox pending=0 processing=0 failed=0 stale=0 due_now=0',
          },
        },
        error: null,
      },
    })).toEqual([
      'Start the backend HTTP server with `php artisan serve --host=127.0.0.1 --port=8000`, then rerun the preflight.',
      'Start MySQL with `powershell -ExecutionPolicy Bypass -File scripts\\ops\\start-local-mysql.ps1 -Restart` or bring up your existing MySQL service, then verify `.env` `DB_HOST`, `DB_PORT`, and `DB_DATABASE` for `restaurantdb`. If the schema or seed state drifted, rerun `composer bootstrap:booking`.',
      'Start Redis with `powershell -ExecutionPolicy Bypass -File scripts\\ops\\start-local-redis.ps1 -Restart`, then rerun the preflight.',
      'After Redis is reachable, keep `php artisan schedule:work` running and rerun the preflight once the scheduler heartbeat is fresh.',
      'Verify notification runtime health with `php artisan notifications:outbox-health --json` after MySQL, Redis, and scheduler are healthy.',
    ]);
  });
});
