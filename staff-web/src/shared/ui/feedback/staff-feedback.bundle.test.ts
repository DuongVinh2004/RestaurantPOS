import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const currentDir = dirname(fileURLToPath(import.meta.url));
const toastSource = readFileSync(resolve(currentDir, './toast.ts'), 'utf8');
const confirmSource = readFileSync(resolve(currentDir, '../../hooks/useConfirmAction.ts'), 'utf8');
const providersSource = readFileSync(resolve(currentDir, '../../../app/providers/AppProviders.tsx'), 'utf8');

describe('staff feedback bundle guards', () => {
  it('keeps Antd App out of root providers and lazy-loads feedback APIs', () => {
    expect(providersSource).not.toContain('AntdApp');
    expect(providersSource).not.toContain('<AntdApp>');
    expect(providersSource).toMatch(/ConfigProvider\.config\(\{[\s\S]*holderRender:/s);
    expect(toastSource).toMatch(/import\('antd\/es\/message'\)/);
    expect(confirmSource).toMatch(/import\('antd\/es\/modal'\)/);
    expect(confirmSource).not.toContain('App.useApp');
  });
});
