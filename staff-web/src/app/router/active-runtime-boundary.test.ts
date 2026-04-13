import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const runtimeRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const activeRoots = ['app', 'components', 'core', 'features', 'hooks', 'lib'];
const sourceFilePattern = /\.(ts|tsx)$/;
const legacyImportPattern = /\bfrom\s*['"][^'"]*_legacy\/[^'"]*['"]|import\(\s*['"][^'"]*_legacy\/[^'"]*['"]\s*\)/;

describe('active runtime boundary', () => {
  it('keeps mounted source files free of imports from src/_legacy', () => {
    const offenders = activeRoots
      .flatMap((root) => collectSourceFiles(path.join(runtimeRoot, root)))
      .filter((filePath) => legacyImportPattern.test(fs.readFileSync(filePath, 'utf8')))
      .map((filePath) => path.relative(runtimeRoot, filePath));

    expect(offenders).toEqual([]);
  });
});

function collectSourceFiles(root: string): Array<string> {
  if (!fs.existsSync(root)) {
    return [];
  }

  return fs.readdirSync(root, { withFileTypes: true }).flatMap((entry) => {
    const entryPath = path.join(root, entry.name);

    if (entry.isDirectory()) {
      if (entry.name === '_legacy' || entry.name === 'test') {
        return [];
      }

      return collectSourceFiles(entryPath);
    }

    if (!sourceFilePattern.test(entry.name) || entry.name.includes('.test.')) {
      return [];
    }

    return [entryPath];
  });
}
