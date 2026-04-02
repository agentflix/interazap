import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { TestBed } from '@angular/core/testing';
import { OptimisticUpdateService } from './optimistic-update.service';

describe('OptimisticUpdateService', () => {
  let service: OptimisticUpdateService;

  beforeEach(() => {
    vi.useFakeTimers();
    TestBed.configureTestingModule({
      providers: [OptimisticUpdateService],
    });
    service = TestBed.inject(OptimisticUpdateService);
  });

  afterEach(() => {
    service.clear();
    vi.useRealTimers();
  });

  describe('apply', () => {
    it('should apply optimistic state and return an id', () => {
      let appliedState: unknown = null;

      const id = service.apply({
        type: 'contact',
        entityId: '123',
        previousState: { name: 'João' },
        optimisticState: { name: 'João Silva' },
        onApply: (state) => (appliedState = state),
        onRollback: () => {},
      });

      expect(id).toBeTruthy();
      expect(id).toMatch(/^opt_/);
      expect(appliedState).toEqual({ name: 'João Silva' });
    });

    it('should track pending updates', () => {
      service.apply({
        type: 'contact',
        entityId: '123',
        previousState: { name: 'A' },
        optimisticState: { name: 'B' },
        onApply: () => {},
        onRollback: () => {},
      });

      expect(service.pendingCount()).toBe(1);
      expect(service.hasPending()).toBe(true);
    });

    it('should track multiple pending updates', () => {
      service.apply({
        type: 'contact',
        entityId: '1',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => {},
      });

      service.apply({
        type: 'deal',
        entityId: '2',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => {},
      });

      expect(service.pendingCount()).toBe(2);
    });
  });

  describe('confirm', () => {
    it('should remove pending update on confirm', () => {
      const id = service.apply({
        type: 'contact',
        entityId: '123',
        previousState: { name: 'A' },
        optimisticState: { name: 'B' },
        onApply: () => {},
        onRollback: () => {},
      });

      expect(service.pendingCount()).toBe(1);

      service.confirm(id);

      expect(service.pendingCount()).toBe(0);
      expect(service.hasPending()).toBe(false);
    });

    it('should not throw on invalid id', () => {
      expect(() => service.confirm('invalid_id')).not.toThrow();
    });

    it('should not confirm already confirmed update', () => {
      const id = service.apply({
        type: 'contact',
        entityId: '123',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => {},
      });

      service.confirm(id);
      expect(() => service.confirm(id)).not.toThrow();
    });
  });

  describe('rollback', () => {
    it('should call rollback callback with previous state', () => {
      let rolledBackState: unknown = null;

      const id = service.apply({
        type: 'contact',
        entityId: '123',
        previousState: { name: 'Original' },
        optimisticState: { name: 'Updated' },
        onApply: () => {},
        onRollback: (prev) => (rolledBackState = prev),
      });

      service.rollback(id);

      expect(rolledBackState).toEqual({ name: 'Original' });
      expect(service.pendingCount()).toBe(0);
    });

    it('should not throw on invalid id', () => {
      expect(() => service.rollback('invalid_id')).not.toThrow();
    });

    it('should handle rollback with reason', () => {
      let rolledBack = false;

      const id = service.apply({
        type: 'contact',
        entityId: '123',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => {
          rolledBack = true;
        },
      });

      service.rollback(id, 'Network error');

      expect(rolledBack).toBe(true);
    });
  });

  describe('timeout auto-rollback', () => {
    it('should auto-rollback after timeout', async () => {
      let rolledBack = false;

      service.apply({
        type: 'contact',
        entityId: '123',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => (rolledBack = true),
        timeout: 1000,
      });

      expect(service.pendingCount()).toBe(1);
      expect(rolledBack).toBe(false);

      await vi.advanceTimersByTimeAsync(1001);

      expect(rolledBack).toBe(true);
      expect(service.pendingCount()).toBe(0);
    });

    it('should not auto-rollback if confirmed before timeout', async () => {
      let rolledBack = false;

      const id = service.apply({
        type: 'contact',
        entityId: '123',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => (rolledBack = true),
        timeout: 1000,
      });

      await vi.advanceTimersByTimeAsync(500);
      service.confirm(id);
      await vi.advanceTimersByTimeAsync(600);

      expect(rolledBack).toBe(false);
    });
  });

  describe('getPendingByType', () => {
    it('should return pending updates of specified type', () => {
      service.apply({
        type: 'contact',
        entityId: '1',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => {},
      });

      service.apply({
        type: 'deal',
        entityId: '2',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => {},
      });

      service.apply({
        type: 'contact',
        entityId: '3',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => {},
      });

      const contacts = service.getPendingByType('contact');
      expect(contacts.length).toBe(2);
      expect(contacts.every((u) => u.type === 'contact')).toBe(true);
    });
  });

  describe('hasPendingForEntity', () => {
    it('should return true if entity has pending update', () => {
      service.apply({
        type: 'contact',
        entityId: '123',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => {},
      });

      expect(service.hasPendingForEntity('contact', '123')).toBe(true);
      expect(service.hasPendingForEntity('contact', '456')).toBe(false);
      expect(service.hasPendingForEntity('deal', '123')).toBe(false);
    });
  });

  describe('rollbackByType', () => {
    it('should rollback all updates of specified type', () => {
      let contactRollbacks = 0;
      let dealRollbacks = 0;

      service.apply({
        type: 'contact',
        entityId: '1',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => contactRollbacks++,
      });

      service.apply({
        type: 'deal',
        entityId: '2',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => dealRollbacks++,
      });

      service.apply({
        type: 'contact',
        entityId: '3',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => contactRollbacks++,
      });

      service.rollbackByType('contact');

      expect(contactRollbacks).toBe(2);
      expect(dealRollbacks).toBe(0);
      expect(service.pendingCount()).toBe(1);
    });
  });

  describe('rollbackByEntity', () => {
    it('should rollback updates for specific entity', () => {
      let rollbackCount = 0;

      service.apply({
        type: 'contact',
        entityId: '123',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => rollbackCount++,
      });

      service.apply({
        type: 'contact',
        entityId: '456',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => rollbackCount++,
      });

      service.rollbackByEntity('contact', '123');

      expect(rollbackCount).toBe(1);
      expect(service.pendingCount()).toBe(1);
    });
  });

  describe('clear', () => {
    it('should clear all pending updates', () => {
      service.apply({
        type: 'contact',
        entityId: '1',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => {},
      });

      service.apply({
        type: 'deal',
        entityId: '2',
        previousState: {},
        optimisticState: {},
        onApply: () => {},
        onRollback: () => {},
      });

      expect(service.pendingCount()).toBe(2);

      service.clear();

      expect(service.pendingCount()).toBe(0);
      expect(service.hasPending()).toBe(false);
    });
  });
});
