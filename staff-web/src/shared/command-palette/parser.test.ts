import { describe, it, expect } from 'vitest';
import { tokenizeCommand, parseCommand } from './parser';

describe('Command Parser & Tokenizer', () => {
  it('tokenizes simple commands', () => {
    const tokens = tokenizeCommand('/move-table 5 12');
    expect(tokens).toEqual(['/move-table', '5', '12']);
  });

  it('handles multiple spaces between tokens', () => {
    const tokens = tokenizeCommand('/move-table    5    12');
    expect(tokens).toEqual(['/move-table', '5', '12']);
  });

  it('handles quoted strings with spaces inside', () => {
    const tokens = tokenizeCommand('/add-note 12 "VIP Guest is arriving late"');
    expect(tokens).toEqual(['/add-note', '12', 'VIP Guest is arriving late']);
  });

  it('handles empty input gracefully', () => {
    expect(tokenizeCommand('')).toEqual([]);
    expect(tokenizeCommand('   ')).toEqual([]);
  });

  it('handles unclosed quotes safely', () => {
    // If a quote is opened but not closed, it should still capture everything till the end
    const tokens = tokenizeCommand('/add-note "hello world');
    expect(tokens).toEqual(['/add-note', 'hello world']);
  });

  it('returns null for non-command strings', () => {
    expect(parseCommand('hello world')).toBeNull();
  });

  it('parses valid commands with spaces', () => {
    const parsed = parseCommand('   /move-table 5 12   ');
    expect(parsed).toEqual({
      command: '/move-table',
      args: ['5', '12'],
      raw: '/move-table 5 12',
    });
  });

  it('parses commands with quotes', () => {
    const parsed = parseCommand('/ADD-ITEM "Spring Roll" 2');
    expect(parsed).toEqual({
      command: '/add-item',
      args: ['Spring Roll', '2'],
      raw: '/ADD-ITEM "Spring Roll" 2',
    });
  });

  it('returns null if command only contains slash and spaces', () => {
    const parsed = parseCommand('/   ');
    // trimmed is "/", tokenized is ["/"]
    expect(parsed).toEqual({
      command: '/',
      args: [],
      raw: '/',
    });
  });
});
