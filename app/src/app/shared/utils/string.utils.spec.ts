import { describe, it, expect } from 'vitest';
import { formatDate, getInitials } from './string.utils';

describe('getInitials()', () => {
  it('should return two initials from a full name', () => {
    expect(getInitials('João Silva')).toBe('JS');
  });

  it('should return the single initial for a single-word name', () => {
    expect(getInitials('Ana')).toBe('A');
  });

  it('should only use the first two words when there are three or more', () => {
    expect(getInitials('First Middle Last')).toBe('FM');
  });

  it('should ignore extra whitespace', () => {
    expect(getInitials('  João   Silva  ')).toBe('JS');
  });

  it('should uppercase the initials', () => {
    expect(getInitials('john doe')).toBe('JD');
  });

  it('should return the default fallback (empty string) for an empty name', () => {
    expect(getInitials('')).toBe('');
  });

  it('should return a custom fallback for an empty name', () => {
    expect(getInitials('', 'US')).toBe('US');
  });

  it('should return a custom fallback for a whitespace-only string', () => {
    expect(getInitials('   ', '??')).toBe('??');
  });
});

describe('formatDate()', () => {
  it('should format a valid ISO date string in pt-BR locale', () => {
    expect(formatDate('2024-01-15')).toBe('15/01/2024');
  });

  it('should return the default fallback for null', () => {
    expect(formatDate(null)).toBe('-');
  });

  it('should return the default fallback for undefined', () => {
    expect(formatDate(undefined)).toBe('-');
  });

  it('should return the default fallback for an empty string', () => {
    expect(formatDate('')).toBe('-');
  });

  it('should return the default fallback for an invalid date string', () => {
    expect(formatDate('not-a-date')).toBe('-');
  });

  it('should accept and use a custom fallback', () => {
    expect(formatDate(null, 'N/A')).toBe('N/A');
    expect(formatDate('bad', 'N/A')).toBe('N/A');
  });
});
