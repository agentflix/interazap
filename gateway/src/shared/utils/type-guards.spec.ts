import {
  isRecord,
  asRecord,
  cloneRecord,
  getString,
  getBoolean,
  getNumber,
  getRecord,
  readString,
} from './type-guards';

describe('type-guards', () => {
  describe('isRecord', () => {
    it('returns true for plain objects', () => {
      expect(isRecord({})).toBe(true);
      expect(isRecord({ a: 1 })).toBe(true);
    });

    it('returns false for null', () => {
      expect(isRecord(null)).toBe(false);
    });

    it('returns false for arrays', () => {
      expect(isRecord([])).toBe(false);
    });

    it('returns false for primitives', () => {
      expect(isRecord('string')).toBe(false);
      expect(isRecord(123)).toBe(false);
      expect(isRecord(undefined)).toBe(false);
    });
  });

  describe('asRecord', () => {
    it('returns object when valid', () => {
      const obj = { key: 'value' };
      expect(asRecord(obj)).toBe(obj);
    });

    it('returns undefined for non-objects', () => {
      expect(asRecord(null)).toBeUndefined();
      expect(asRecord('string')).toBeUndefined();
      expect(asRecord([])).toBeUndefined();
    });
  });

  describe('cloneRecord', () => {
    it('clones an object', () => {
      const original = { a: 1, b: 'test' };
      const cloned = cloneRecord(original);
      expect(cloned).toEqual(original);
      expect(cloned).not.toBe(original);
    });

    it('returns empty object for non-records', () => {
      expect(cloneRecord(null)).toEqual({});
      expect(cloneRecord('string')).toEqual({});
    });
  });

  describe('getString', () => {
    it('returns string value', () => {
      expect(getString({ key: 'value' }, 'key')).toBe('value');
    });

    it('returns undefined for non-string', () => {
      expect(getString({ key: 123 }, 'key')).toBeUndefined();
    });

    it('returns undefined for missing key', () => {
      expect(getString({}, 'key')).toBeUndefined();
    });
  });

  describe('getBoolean', () => {
    it('returns boolean value', () => {
      expect(getBoolean({ key: true }, 'key')).toBe(true);
      expect(getBoolean({ key: false }, 'key')).toBe(false);
    });

    it('parses string booleans', () => {
      expect(getBoolean({ key: 'true' }, 'key')).toBe(true);
      expect(getBoolean({ key: 'TRUE' }, 'key')).toBe(true);
      expect(getBoolean({ key: 'false' }, 'key')).toBe(false);
      expect(getBoolean({ key: 'FALSE' }, 'key')).toBe(false);
    });

    it('returns undefined for invalid values', () => {
      expect(getBoolean({ key: 'yes' }, 'key')).toBeUndefined();
      expect(getBoolean({ key: 123 }, 'key')).toBeUndefined();
    });
  });

  describe('getNumber', () => {
    it('returns number value', () => {
      expect(getNumber({ key: 42 }, 'key')).toBe(42);
      expect(getNumber({ key: 3.14 }, 'key')).toBe(3.14);
    });

    it('parses string numbers', () => {
      expect(getNumber({ key: '123' }, 'key')).toBe(123);
      expect(getNumber({ key: '45.67' }, 'key')).toBe(45.67);
    });

    it('returns undefined for invalid values', () => {
      expect(getNumber({ key: 'abc' }, 'key')).toBeUndefined();
      expect(getNumber({ key: true }, 'key')).toBeUndefined();
    });
  });

  describe('getRecord', () => {
    it('returns nested object', () => {
      const nested = { inner: 1 };
      expect(getRecord({ key: nested }, 'key')).toBe(nested);
    });

    it('returns undefined for non-objects', () => {
      expect(getRecord({ key: 'string' }, 'key')).toBeUndefined();
      expect(getRecord({ key: [] }, 'key')).toBeUndefined();
    });
  });

  describe('readString', () => {
    it('returns string value', () => {
      expect(readString('hello')).toBe('hello');
    });

    it('returns undefined for non-strings', () => {
      expect(readString(123)).toBeUndefined();
      expect(readString(null)).toBeUndefined();
    });
  });
});
