import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const currentDir = dirname(fileURLToPath(import.meta.url));
const coreErrorsSource = readFileSync(resolve(currentDir, './errors.ts'), 'utf8');
const legacyErrorsSource = readFileSync(resolve(currentDir, '../../lib/api-errors.ts'), 'utf8');

describe('api error bundle guards', () => {
  it('keeps generated SDK error classes out of runtime normalization helpers', () => {
    expect(coreErrorsSource).toContain("import type { RestaurantPosApiError } from './sdk';");
    expect(legacyErrorsSource).toContain("import type { RestaurantPosApiError } from '../api/sdk';");
    expect(coreErrorsSource).not.toContain('instanceof RestaurantPosApiError');
    expect(legacyErrorsSource).not.toContain('instanceof RestaurantPosApiError');
    expect(coreErrorsSource).toContain('hasApiErrorPayload(error)');
    expect(legacyErrorsSource).toContain('hasApiErrorPayload(error)');
  });
});
