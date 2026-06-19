import { describe, it, expect, vi, beforeEach } from 'vitest';
import { validateCommand } from './validator';

// Mock the registry so we have stable commands to test against
vi.mock('./registry', () => ({
  COMMAND_REGISTRY: {
    '/mock-cmd': {
      name: '/mock-cmd',
      description: 'A mock command',
      expectedArgs: [
        { name: 'arg_str', type: 'string' },
        { name: 'arg_num', type: 'number' },
        { name: 'arg_bool', type: 'boolean' },
      ],
      isDestructive: false,
    },
    '/mock-empty': {
      name: '/mock-empty',
      description: 'No args',
      expectedArgs: [],
      isDestructive: false,
    }
  }
}));

describe('Command Validator', () => {
  it('returns valid=false for unknown commands', () => {
    const result = validateCommand({ command: '/unknown', args: [], raw: '/unknown' });
    expect(result.valid).toBe(false);
    expect(result.errors).toContain('Lệnh không hợp lệ: /unknown');
  });

  it('reports error when arguments are missing', () => {
    const result = validateCommand({ command: '/mock-cmd', args: ['hello'], raw: '/mock-cmd hello' });
    expect(result.valid).toBe(false);
    expect(result.errors).toContain('Thiếu tham số. Cấu trúc đúng: /mock-cmd <arg_str> <arg_num> <arg_bool>');
  });

  it('validates a correct set of arguments', () => {
    const result = validateCommand({ command: '/mock-cmd', args: ['hello', '42', 'true'], raw: '/mock-cmd hello 42 true' });
    expect(result.valid).toBe(true);
    expect(result.errors).toHaveLength(0);
    expect(result.resolvedArgs).toEqual({
      arg_str: 'hello',
      arg_num: 42,
      arg_bool: true,
    });
  });

  it('validates boolean true variants (true, 1)', () => {
    expect(validateCommand({ command: '/mock-cmd', args: ['h', '1', '1'], raw: '' }).resolvedArgs.arg_bool).toBe(true);
    expect(validateCommand({ command: '/mock-cmd', args: ['h', '1', 'True'], raw: '' }).resolvedArgs.arg_bool).toBe(true);
  });

  it('validates boolean false variants (false, 0)', () => {
    expect(validateCommand({ command: '/mock-cmd', args: ['h', '1', '0'], raw: '' }).resolvedArgs.arg_bool).toBe(false);
    expect(validateCommand({ command: '/mock-cmd', args: ['h', '1', 'False'], raw: '' }).resolvedArgs.arg_bool).toBe(false);
  });

  it('rejects invalid boolean values', () => {
    const result = validateCommand({ command: '/mock-cmd', args: ['h', '1', 'yes'], raw: '' });
    expect(result.valid).toBe(false);
    expect(result.errors).toContain('Tham số <arg_bool> phải là true/false hoặc 1/0');
  });

  it('rejects invalid number values', () => {
    const result = validateCommand({ command: '/mock-cmd', args: ['h', 'abc', '1'], raw: '' });
    expect(result.valid).toBe(false);
    expect(result.errors).toContain('Tham số <arg_num> phải là số nguyên hoặc số thực hợp lệ');
  });

  it('handles empty commands with no args required', () => {
    const result = validateCommand({ command: '/mock-empty', args: [], raw: '/mock-empty' });
    expect(result.valid).toBe(true);
    expect(result.resolvedArgs).toEqual({});
  });
  
  it('ignores extra arguments provided', () => {
    const result = validateCommand({ command: '/mock-empty', args: ['extra'], raw: '/mock-empty extra' });
    expect(result.valid).toBe(true);
    expect(result.resolvedArgs).toEqual({});
  });
});
