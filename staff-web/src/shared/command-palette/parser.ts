export type ParsedCommand = {
  command: string;
  args: string[];
  raw: string;
};

export function tokenizeCommand(input: string): string[] {
  const tokens: string[] = [];
  let currentToken = '';
  let inQuotes = false;

  for (let i = 0; i < input.length; i++) {
    const char = input[i];

    if (char === '"') {
      inQuotes = !inQuotes;
      continue;
    }

    if (char === ' ' && !inQuotes) {
      if (currentToken.length > 0) {
        tokens.push(currentToken);
        currentToken = '';
      }
    } else {
      currentToken += char;
    }
  }

  if (currentToken.length > 0) {
    tokens.push(currentToken);
  }

  return tokens;
}

export function parseCommand(input: string): ParsedCommand | null {
  const trimmed = input.trim();
  if (!trimmed.startsWith('/')) {
    return null;
  }

  const tokens = tokenizeCommand(trimmed);
  if (tokens.length === 0) return null; // should not happen if it starts with /

  return {
    command: tokens[0].toLowerCase(),
    args: tokens.slice(1),
    raw: trimmed,
  };
}
