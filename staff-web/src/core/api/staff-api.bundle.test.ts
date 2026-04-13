import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const currentDir = dirname(fileURLToPath(import.meta.url));
const barrelSource = readFileSync(resolve(currentDir, './staff-api.ts'), 'utf8');
const authApiSource = readFileSync(resolve(currentDir, './staff-auth-api.ts'), 'utf8');
const branchApiSource = readFileSync(resolve(currentDir, './staff-branch-api.ts'), 'utf8');
const authStoreSource = readFileSync(resolve(currentDir, '../../app/store/auth-store.ts'), 'utf8');
const shellContextSource = readFileSync(resolve(currentDir, '../../app/layout/useStaffShellContext.ts'), 'utf8');

describe('staff api bundle guards', () => {
  it('moves shared shell and auth consumers onto focused api modules', () => {
    expect(authStoreSource).toContain("from '../../core/api/staff-auth-api'");
    expect(shellContextSource).toContain("from '../../core/api/staff-branch-api'");
    expect(authApiSource).toContain("'/auth/staff/login'");
    expect(branchApiSource).toContain("'/staff/branches'");
    expect(barrelSource).toContain("export { getCurrentStaffSession, loginStaff, logoutStaff, refreshStaffSession } from './staff-auth-api';");
    expect(barrelSource).toContain("export { listBranches } from './staff-branch-api';");
    expect(barrelSource).not.toContain("export async function loginStaff(payload");
    expect(barrelSource).not.toContain('export async function listBranches()');
  });
});
