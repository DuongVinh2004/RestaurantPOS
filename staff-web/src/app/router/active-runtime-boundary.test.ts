import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const runtimeRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const activeRoots = ['app', 'domains', 'shared', 'workspaces'];
const sourceFilePattern = /\.(ts|tsx)$/;
const deprecatedImportPattern = /\bfrom\s*['"](?:\.\.\/){2,}(?:_legacy|components|core|features|hooks|lib)\/[^'"]*['"]|import\(\s*['"](?:\.\.\/){2,}(?:_legacy|components|core|features|hooks|lib)\/[^'"]*['"]\s*\)/;

describe('active runtime boundary', () => {
  it('keeps mounted source files free of imports from deprecated pre-workspace roots', () => {
    const deprecatedRoots = ['_legacy', 'components', 'core', 'features', 'hooks', 'lib'];
    const offenders = activeRoots
      .flatMap((root) => collectSourceFiles(path.join(runtimeRoot, root)))
      .filter((filePath) => {
        const content = fs.readFileSync(filePath, 'utf8');
        const importRegex = /\b(?:from|import)\s*\(?['"]((?:\.\.\/)+)(_legacy|components|core|features|hooks|lib)\/[^'"]*['"]\)?/g;
        let match;
        while ((match = importRegex.exec(content)) !== null) {
          const importPath = match[1] + match[2];
          const resolved = path.resolve(path.dirname(filePath), importPath);
          const relativeToRoot = path.relative(runtimeRoot, resolved);
          // If the resolved path actually points directly into one of the deprecated roots at the top level
          if (deprecatedRoots.some(r => relativeToRoot.startsWith(r + path.sep) || relativeToRoot === r)) {
            return true;
          }
        }
        return false;
      })
      .map((filePath) => path.relative(runtimeRoot, filePath));

    expect(offenders).toEqual([]);
  });

  it('keeps the top-level source tree aligned with the workspace architecture', () => {
    const directories = fs.readdirSync(runtimeRoot, { withFileTypes: true })
      .filter((entry) => entry.isDirectory())
      .map((entry) => entry.name)
      .sort();

    expect(directories).toEqual(['app', 'domains', 'shared', 'styles', 'test', 'workspaces']);
  });
});

function collectSourceFiles(root: string): Array<string> {
  if (!fs.existsSync(root)) {
    return [];
  }

  return fs.readdirSync(root, { withFileTypes: true }).flatMap((entry) => {
    const entryPath = path.join(root, entry.name);

    if (entry.isDirectory()) {
      if (entry.name === 'test') {
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
