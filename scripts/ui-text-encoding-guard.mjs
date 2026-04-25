import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const repoRoot = path.resolve(__dirname, '..');

const WINDOWS_1252_CODEPOINTS = new Map([
  [0x80, 0x20AC],
  [0x82, 0x201A],
  [0x83, 0x0192],
  [0x84, 0x201E],
  [0x85, 0x2026],
  [0x86, 0x2020],
  [0x87, 0x2021],
  [0x88, 0x02C6],
  [0x89, 0x2030],
  [0x8A, 0x0160],
  [0x8B, 0x2039],
  [0x8C, 0x0152],
  [0x8E, 0x017D],
  [0x91, 0x2018],
  [0x92, 0x2019],
  [0x93, 0x201C],
  [0x94, 0x201D],
  [0x95, 0x2022],
  [0x96, 0x2013],
  [0x97, 0x2014],
  [0x98, 0x02DC],
  [0x99, 0x2122],
  [0x9A, 0x0161],
  [0x9B, 0x203A],
  [0x9C, 0x0153],
  [0x9E, 0x017E],
  [0x9F, 0x0178],
]);

const VIETNAMESE_BASES = [
  [0x61],
  [0x61, 0x0306],
  [0x61, 0x0302],
  [0x65],
  [0x65, 0x0302],
  [0x69],
  [0x6F],
  [0x6F, 0x0302],
  [0x6F, 0x031B],
  [0x75],
  [0x75, 0x031B],
  [0x79],
];

const TONE_MARKS = [
  [],
  [0x0300],
  [0x0301],
  [0x0309],
  [0x0303],
  [0x0323],
];

const SPECIAL_CHARACTERS = [
  0x0111,
  0x0110,
  0x2013,
  0x2014,
  0x2018,
  0x2019,
  0x201C,
  0x201D,
  0x2022,
  0x2026,
];

function stringFromCodePoints(codePoints) {
  return String.fromCodePoint(...codePoints);
}

function decodeByteAsWindows1252(byte) {
  return String.fromCodePoint(WINDOWS_1252_CODEPOINTS.get(byte) ?? byte);
}

function collectVietnameseCharacters() {
  const characters = new Set(
    SPECIAL_CHARACTERS.map((codePoint) => String.fromCodePoint(codePoint)),
  );

  for (const base of VIETNAMESE_BASES) {
    for (const tone of TONE_MARKS) {
      const lower = stringFromCodePoints([...base, ...tone]).normalize('NFC');
      if (Array.from(lower).every((character) => character.codePointAt(0) < 0x80)) {
        continue;
      }

      characters.add(lower);
      characters.add(lower.toUpperCase());
    }
  }

  return Array.from(characters);
}

const REPLACEMENTS = collectVietnameseCharacters()
  .map((character) => {
    const mojibake = Array.from(Buffer.from(character, 'utf8'), (byte) =>
      decodeByteAsWindows1252(byte),
    ).join('');

    return [mojibake, character];
  })
  .filter(([mojibake, character]) => mojibake !== character)
  .sort((left, right) => right[0].length - left[0].length);

function shouldScanFile(filePath) {
  const normalizedPath = filePath.replaceAll('\\', '/');

  if (normalizedPath.endsWith('/staff-web/index.html')) {
    return true;
  }

  if (normalizedPath.includes('/staff-web/src/')) {
    return ['.css', '.html', '.ts', '.tsx'].includes(path.extname(filePath));
  }

  if (!normalizedPath.includes('/resources/')) {
    return false;
  }

  return (
    normalizedPath.endsWith('.blade.php') ||
    ['.css', '.html', '.js', '.jsx', '.php', '.ts', '.tsx'].includes(path.extname(filePath))
  );
}

function walkDirectory(directoryPath) {
  const entries = readdirSync(directoryPath, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const absolutePath = path.join(directoryPath, entry.name);

    if (entry.isDirectory()) {
      files.push(...walkDirectory(absolutePath));
      continue;
    }

    files.push(absolutePath);
  }

  return files;
}

function collectBrowserTextFiles() {
  const roots = [
    path.join(repoRoot, 'staff-web', 'src'),
    path.join(repoRoot, 'staff-web'),
    path.join(repoRoot, 'resources'),
  ];

  const files = new Set();

  for (const directoryPath of roots) {
    for (const filePath of walkDirectory(directoryPath)) {
      if (shouldScanFile(filePath)) {
        files.add(filePath);
      }
    }
  }

  return Array.from(files).sort();
}

function repairText(text) {
  let repaired = text;
  let replacementCount = 0;

  for (const [mojibake, character] of REPLACEMENTS) {
    if (!repaired.includes(mojibake)) {
      continue;
    }

    const segments = repaired.split(mojibake);
    replacementCount += segments.length - 1;
    repaired = segments.join(character);
  }

  return {
    repaired,
    replacementCount,
  };
}

function findEncodingIssues(filePaths = collectBrowserTextFiles()) {
  const issues = [];

  for (const filePath of filePaths) {
    const original = readFileSync(filePath, 'utf8');
    const { repaired, replacementCount } = repairText(original);

    if (replacementCount > 0) {
      issues.push({
        filePath,
        original,
        repaired,
        replacementCount,
      });
    }
  }

  return issues;
}

function formatIssue(issue) {
  const relativePath = path.relative(repoRoot, issue.filePath).replaceAll('\\', '/');
  const suffix = issue.replacementCount === 1 ? '' : 's';
  return `${relativePath} (${issue.replacementCount} replacement${suffix})`;
}

function main() {
  const writeMode = process.argv.includes('--write');
  const issues = findEncodingIssues();

  if (issues.length === 0) {
    console.log('UI text encoding guard passed.');
    return;
  }

  if (writeMode) {
    for (const issue of issues) {
      writeFileSync(issue.filePath, issue.repaired, 'utf8');
    }

    console.log(`Repaired ${issues.length} browser-facing file(s):`);
    for (const issue of issues) {
      console.log(`- ${formatIssue(issue)}`);
    }
    return;
  }

  console.error('Detected mojibake in browser-facing source files:');
  for (const issue of issues) {
    console.error(`- ${formatIssue(issue)}`);
  }
  console.error('Run `node scripts/ui-text-encoding-guard.mjs --write` to repair them.');
  process.exitCode = 1;
}

if (path.resolve(process.argv[1] ?? '') === __filename) {
  main();
}

export {
  collectBrowserTextFiles,
  findEncodingIssues,
  repairText,
  repoRoot,
};
