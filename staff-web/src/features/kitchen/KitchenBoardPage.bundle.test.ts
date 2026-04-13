import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const currentDir = dirname(fileURLToPath(import.meta.url));
const kitchenSource = readFileSync(resolve(currentDir, './KitchenBoardPage.tsx'), 'utf8');

describe('KitchenBoardPage bundle guards', () => {
  it('keeps heavy table and select widgets out of the route shell', () => {
    expect(kitchenSource).toContain('className="staff-toolbar-select"');
    expect(kitchenSource).toContain('staff-kitchen-station-list');
    expect(kitchenSource).not.toContain('  Select,');
    expect(kitchenSource).not.toContain('  Table,');
    expect(kitchenSource).not.toContain('<Select');
    expect(kitchenSource).not.toContain('<Table');
  });
});
