import { isRecent } from './snapshot.util';

describe('snapshot.util', () => {
  describe('isRecent', () => {
    it('returns false when hydratedAt is missing or invalid', () => {
      expect(isRecent()).toBe(false);
      expect(isRecent('not-a-date')).toBe(false);
    });

    it('returns true when timestamp is within default 90_000ms threshold', () => {
      const nowIso = new Date().toISOString();
      expect(isRecent(nowIso)).toBe(true);
    });

    it('returns false when timestamp is older than default 90_000ms threshold', () => {
      const oldIso = new Date(Date.now() - 90_001).toISOString();
      expect(isRecent(oldIso)).toBe(false);
    });
  });
});
