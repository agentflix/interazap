import { parsePositiveTimeout } from './timeout.util';

describe('timeout.util', () => {
  describe('parsePositiveTimeout', () => {
    it('returns parsed number when value is positive and finite', () => {
      expect(parsePositiveTimeout(5000, 1000)).toBe(5000);
      expect(parsePositiveTimeout('2500', 1000)).toBe(2500);
    });

    it('returns fallback when value is invalid', () => {
      expect(parsePositiveTimeout(undefined, 1000)).toBe(1000);
      expect(parsePositiveTimeout('abc', 1000)).toBe(1000);
      expect(parsePositiveTimeout(0, 1000)).toBe(1000);
      expect(parsePositiveTimeout(-1, 1000)).toBe(1000);
    });
  });
});
