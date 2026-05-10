import { describe, expect, it } from 'vitest';
import {
  type StablyOrderedMessage,
  compareMessagesAsc,
  compareMessagesDesc,
  mergeAndSortMessagesAsc,
  mergeAndSortMessagesDesc,
  resolveMessageOrderTimestamp,
} from './message-comparator.util';

/** Helper para criar mensagens de teste com campos mínimos. */
function makeMessage(
  id: string,
  created_at: string | null,
  overrides?: Partial<StablyOrderedMessage>,
): StablyOrderedMessage {
  return {
    id,
    created_at,
    ...overrides,
  };
}

describe('message-comparator.util', () => {
  describe('resolveMessageOrderTimestamp', () => {
    it('deve retornar o timestamp de created_at quando disponível', () => {
      const msg = makeMessage('1', '2025-05-10T10:00:00.000Z');
      expect(resolveMessageOrderTimestamp(msg)).toBe(Date.parse('2025-05-10T10:00:00.000Z'));
    });

    it('deve fallback para sent_at quando created_at é nulo', () => {
      const msg: StablyOrderedMessage = {
        id: '2',
        created_at: null,
        sent_at: '2025-05-10T10:01:00.000Z',
      };
      expect(resolveMessageOrderTimestamp(msg)).toBe(Date.parse('2025-05-10T10:01:00.000Z'));
    });

    it('deve fallback para delivered_at quando created_at e sent_at são nulos', () => {
      const msg: StablyOrderedMessage = {
        id: '3',
        created_at: null,
        sent_at: null,
        delivered_at: '2025-05-10T10:02:00.000Z',
      };
      expect(resolveMessageOrderTimestamp(msg)).toBe(Date.parse('2025-05-10T10:02:00.000Z'));
    });

    it('deve retornar 0 quando nenhum timestamp é válido', () => {
      const msg = makeMessage('4', null);
      expect(resolveMessageOrderTimestamp(msg)).toBe(0);
    });
  });

  describe('compareMessagesAsc', () => {
    it('deve ordenar por created_at ASC', () => {
      const a = makeMessage('1', '2025-05-10T10:00:00.000Z');
      const b = makeMessage('2', '2025-05-10T10:01:00.000Z');
      expect(compareMessagesAsc(a, b)).toBeLessThan(0);
      expect(compareMessagesAsc(b, a)).toBeGreaterThan(0);
    });

    it('deve desempatar por id ASC quando created_at é igual', () => {
      const sameTimestamp = '2025-05-10T10:00:00.000Z';
      const a = makeMessage('aaa-111', sameTimestamp);
      const b = makeMessage('bbb-222', sameTimestamp);

      expect(compareMessagesAsc(a, b)).toBeLessThan(0);
      expect(compareMessagesAsc(b, a)).toBeGreaterThan(0);
    });

    it('deve retornar 0 quando timestamps e ids são iguais', () => {
      const sameTimestamp = '2025-05-10T10:00:00.000Z';
      const a = makeMessage('same-id', sameTimestamp);
      const b = makeMessage('same-id', sameTimestamp);
      expect(compareMessagesAsc(a, b)).toBe(0);
    });

    it('deve ordenar mensagens sem timestamp (0) no início', () => {
      const a = makeMessage('1', null);
      const b = makeMessage('2', '2025-05-10T10:00:00.000Z');
      expect(compareMessagesAsc(a, b)).toBeLessThan(0);
    });
  });

  describe('compareMessagesDesc', () => {
    it('deve ordenar por created_at DESC', () => {
      const a = makeMessage('1', '2025-05-10T10:01:00.000Z');
      const b = makeMessage('2', '2025-05-10T10:00:00.000Z');
      expect(compareMessagesDesc(a, b)).toBeLessThan(0);
      expect(compareMessagesDesc(b, a)).toBeGreaterThan(0);
    });

    it('deve desempatar por id DESC quando created_at é igual', () => {
      const sameTimestamp = '2025-05-10T10:00:00.000Z';
      const a = makeMessage('aaa-111', sameTimestamp);
      const b = makeMessage('bbb-222', sameTimestamp);

      // DESC: id maior (bbb) vem primeiro
      expect(compareMessagesDesc(b, a)).toBeLessThan(0);
      expect(compareMessagesDesc(a, b)).toBeGreaterThan(0);
    });
  });

  describe('mergeAndSortMessagesAsc', () => {
    it('deve mesclar arrays sem duplicatas', () => {
      const current = [makeMessage('1', '2025-05-10T10:00:00.000Z')];
      const incoming = [makeMessage('2', '2025-05-10T10:01:00.000Z')];
      const result = mergeAndSortMessagesAsc(current, incoming);

      expect(result).toHaveLength(2);
      expect(result[0].id).toBe('1');
      expect(result[1].id).toBe('2');
    });

    it('deve deduplicar por id mantendo incoming', () => {
      const current = [makeMessage('1', '2025-05-10T10:00:00.000Z', { sent_at: '2025-05-10T10:00:00.000Z' })];
      const incoming = [makeMessage('1', '2025-05-10T10:00:00.000Z', { sent_at: '2025-05-10T10:01:00.000Z' })];
      const result = mergeAndSortMessagesAsc(current, incoming);

      expect(result).toHaveLength(1);
      // Incoming sobrescreve current
      expect(result[0].sent_at).toBe('2025-05-10T10:01:00.000Z');
    });

    it('deve manter ordem estável com timestamps iguais', () => {
      const sameTimestamp = '2025-05-10T10:00:00.000Z';
      const messages = [
        makeMessage('zzz-333', sameTimestamp),
        makeMessage('aaa-111', sameTimestamp),
        makeMessage('mmm-222', sameTimestamp),
      ];
      const result = mergeAndSortMessagesAsc([], messages);

      expect(result).toHaveLength(3);
      // Ordenação ASC estável: id menor primeiro
      expect(result[0].id).toBe('aaa-111');
      expect(result[1].id).toBe('mmm-222');
      expect(result[2].id).toBe('zzz-333');
    });

    it('deve intercalar corretamente timestamps diferentes e iguais', () => {
      const current = [
        makeMessage('M1', '2025-05-10T10:00:00.000Z'),
        makeMessage('M3', '2025-05-10T10:02:00.000Z'),
      ];
      const incoming = [
        makeMessage('M2', '2025-05-10T10:01:00.000Z'),
        makeMessage('M4', '2025-05-10T10:03:00.000Z'),
      ];
      const result = mergeAndSortMessagesAsc(current, incoming);

      expect(result.map((m) => m.id)).toEqual(['M1', 'M2', 'M3', 'M4']);
    });
  });

  describe('mergeAndSortMessagesDesc', () => {
    it('deve mesclar em ordem DESC', () => {
      const current = [makeMessage('1', '2025-05-10T10:00:00.000Z')];
      const incoming = [makeMessage('2', '2025-05-10T10:01:00.000Z')];
      const result = mergeAndSortMessagesDesc(current, incoming);

      expect(result).toHaveLength(2);
      expect(result[0].id).toBe('2');
      expect(result[1].id).toBe('1');
    });

    it('deve manter ordem estável DESC com timestamps iguais', () => {
      const sameTimestamp = '2025-05-10T10:00:00.000Z';
      const messages = [
        makeMessage('aaa-111', sameTimestamp),
        makeMessage('zzz-333', sameTimestamp),
        makeMessage('mmm-222', sameTimestamp),
      ];
      const result = mergeAndSortMessagesDesc([], messages);

      expect(result).toHaveLength(3);
      // DESC estável: id maior primeiro
      expect(result[0].id).toBe('zzz-333');
      expect(result[1].id).toBe('mmm-222');
      expect(result[2].id).toBe('aaa-111');
    });
  });
});