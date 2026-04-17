const WINDOWS_1252_CODEPOINTS = new Map<number, number>([
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
  [0x6f],
  [0x6f, 0x0302],
  [0x6f, 0x031b],
  [0x75],
  [0x75, 0x031b],
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
  0x201c,
  0x201d,
  0x2022,
];

type NormalizationResult<T> = {
  replacementCount: number;
  value: T;
};

const warnedPaths = new Set<string>();

function stringFromCodePoints(codePoints: Array<number>): string {
  return String.fromCodePoint(...codePoints);
}

function decodeByteAsWindows1252(byte: number): string {
  return String.fromCodePoint(WINDOWS_1252_CODEPOINTS.get(byte) ?? byte);
}

function collectVietnameseCharacters(): Array<string> {
  const characters = new Set<string>(
    SPECIAL_CHARACTERS.map((codePoint) => String.fromCodePoint(codePoint)),
  );

  for (const base of VIETNAMESE_BASES) {
    for (const tone of TONE_MARKS) {
      const lower = stringFromCodePoints([...base, ...tone]).normalize('NFC');
      if (Array.from(lower).every((character) => character.codePointAt(0)! < 0x80)) {
        continue;
      }

      characters.add(lower);
      characters.add(lower.toUpperCase());
    }
  }

  return Array.from(characters);
}

const MOJIBAKE_REPLACEMENTS = collectVietnameseCharacters()
  .map((character) => {
    const mojibake = Array.from(new TextEncoder().encode(character), (byte) =>
      decodeByteAsWindows1252(byte),
    ).join('');

    return [mojibake, character] as const;
  })
  .filter(([mojibake, character]) => mojibake !== character)
  .sort((left, right) => right[0].length - left[0].length);

export function repairMojibakeText(value: string): NormalizationResult<string> {
  let repaired = value;
  let replacementCount = 0;

  for (const [mojibake, character] of MOJIBAKE_REPLACEMENTS) {
    if (!repaired.includes(mojibake)) {
      continue;
    }

    const segments = repaired.split(mojibake);
    replacementCount += segments.length - 1;
    repaired = segments.join(character);
  }

  return {
    replacementCount,
    value: repaired,
  };
}

export function normalizeMojibakePayload<T>(payload: T): NormalizationResult<T> {
  if (typeof payload === 'string') {
    return repairMojibakeText(payload) as NormalizationResult<T>;
  }

  if (payload === null || payload === undefined) {
    return {
      replacementCount: 0,
      value: payload,
    };
  }

  if (Array.isArray(payload)) {
    let replacementCount = 0;
    let changed = false;

    const normalized = payload.map((item) => {
      const result = normalizeMojibakePayload(item);
      replacementCount += result.replacementCount;
      changed = changed || result.value !== item;
      return result.value;
    });

    return {
      replacementCount,
      value: (changed ? normalized : payload) as T,
    };
  }

  if (typeof payload === 'object') {
    let replacementCount = 0;
    let changed = false;
    const normalized: Record<string, unknown> = {};

    for (const [key, value] of Object.entries(payload as Record<string, unknown>)) {
      const result = normalizeMojibakePayload(value);
      replacementCount += result.replacementCount;
      changed = changed || result.value !== value;
      normalized[key] = result.value;
    }

    return {
      replacementCount,
      value: (changed ? normalized : payload) as T,
    };
  }

  return {
    replacementCount: 0,
    value: payload,
  };
}

export function warnOnRepairedPayload(path: string, replacementCount: number): void {
  if (replacementCount < 1 || warnedPaths.has(path) || !shouldWarnAboutRepairs()) {
    return;
  }

  warnedPaths.add(path);
  console.warn(
    `[api-text-encoding] repaired ${replacementCount} mojibake segment(s) in response payload for ${path}`,
  );
}

export function resetRepairedPayloadWarnings(): void {
  warnedPaths.clear();
}

function shouldWarnAboutRepairs(): boolean {
  return Boolean(import.meta.env.DEV || import.meta.env.MODE === 'test');
}
