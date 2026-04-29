import { describe, it, expect } from 'vitest';
import { templateStatusLabel, templateStatusVariant } from './template-status';

describe('templateStatusVariant', () => {
  it('mapeia approved para success', () => {
    expect(templateStatusVariant('approved')).toBe('success');
  });
  it('mapeia pending e paused para warning', () => {
    expect(templateStatusVariant('pending')).toBe('warning');
    expect(templateStatusVariant('paused')).toBe('warning');
  });
  it('mapeia rejected para danger', () => {
    expect(templateStatusVariant('rejected')).toBe('danger');
  });
  it('mapeia disabled e desconhecido para default', () => {
    expect(templateStatusVariant('disabled')).toBe('default');
    expect(templateStatusVariant(null)).toBe('default');
    expect(templateStatusVariant('whatever')).toBe('default');
  });
});

describe('templateStatusLabel', () => {
  it('traduz status conhecidos para PT-BR', () => {
    expect(templateStatusLabel('approved')).toBe('Aprovado');
    expect(templateStatusLabel('pending')).toBe('Em aprovação');
    expect(templateStatusLabel('rejected')).toBe('Rejeitado');
    expect(templateStatusLabel('paused')).toBe('Pausado pela Meta');
    expect(templateStatusLabel('disabled')).toBe('Desativado');
  });
  it('retorna fallback para desconhecido', () => {
    expect(templateStatusLabel(null)).toBe('Desconhecido');
  });
});
