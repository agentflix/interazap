import { describe, expect, it } from 'vitest';
import { sentimentColor, sentimentLabel } from './sentiment';

describe('sentiment helpers', () => {
  it('resolves color by score', () => {
    expect(sentimentColor(null)).toBe('gray');
    expect(sentimentColor(10)).toBe('green');
    expect(sentimentColor(45)).toBe('yellow');
    expect(sentimentColor(70)).toBe('orange');
    expect(sentimentColor(95)).toBe('red');
  });

  it('resolves label by score', () => {
    expect(sentimentLabel(null)).toBe('');
    expect(sentimentLabel(10)).toBe('Positivo');
    expect(sentimentLabel(45)).toBe('Neutro');
    expect(sentimentLabel(70)).toBe('Atenção');
    expect(sentimentLabel(95)).toBe('Crítico');
  });
});
