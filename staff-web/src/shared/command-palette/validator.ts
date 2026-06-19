import { COMMAND_REGISTRY } from './registry';
import type { ParsedCommand } from './parser';

export type ValidationResult = {
  valid: boolean;
  errors: string[];
  resolvedArgs: Record<string, string | number | boolean>;
};

export function validateCommand(parsed: ParsedCommand): ValidationResult {
  const definition = COMMAND_REGISTRY[parsed.command];
  if (!definition) {
    return { valid: false, errors: [`Lệnh không hợp lệ: ${parsed.command}`], resolvedArgs: {} };
  }

  const errors: string[] = [];
  const resolvedArgs: Record<string, string | number | boolean> = {};

  if (parsed.args.length < definition.expectedArgs.length) {
    errors.push(`Thiếu tham số. Cấu trúc đúng: ${parsed.command} ${definition.expectedArgs.map(a => `<${a.name}>`).join(' ')}`);
  }

  for (let i = 0; i < definition.expectedArgs.length; i++) {
    const expected = definition.expectedArgs[i];
    const actual = parsed.args[i];

    if (actual === undefined) continue;

    if (expected.type === 'number') {
      const num = Number(actual);
      if (Number.isNaN(num)) {
        errors.push(`Tham số <${expected.name}> phải là số nguyên hoặc số thực hợp lệ`);
      } else {
        resolvedArgs[expected.name] = num;
      }
    } else if (expected.type === 'boolean') {
      if (actual.toLowerCase() === 'true' || actual === '1') {
        resolvedArgs[expected.name] = true;
      } else if (actual.toLowerCase() === 'false' || actual === '0') {
        resolvedArgs[expected.name] = false;
      } else {
        errors.push(`Tham số <${expected.name}> phải là true/false hoặc 1/0`);
      }
    } else {
      resolvedArgs[expected.name] = actual;
    }
  }

  return {
    valid: errors.length === 0,
    errors,
    resolvedArgs,
  };
}
