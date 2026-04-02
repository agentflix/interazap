import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthStoreService } from './auth-store.service';
import { RealtimeService } from './realtime.service';

describe('RealtimeService', () => {
  let service: RealtimeService;
  let eventHandlers: Map<string, (data: { notification: { id: string } }) => void>;

  beforeEach(() => {
    eventHandlers = new Map<string, (data: { notification: { id: string } }) => void>();

    const authStoreMock = {
      token: vi.fn(() => 'token-123'),
      user: vi.fn(() => ({ tenant_id: 'tenant-1' })),
    };

    TestBed.configureTestingModule({
      providers: [
        RealtimeService,
        {
          provide: AuthStoreService,
          useValue: authStoreMock,
        },
      ],
    });

    service = TestBed.inject(RealtimeService);
  });

  it('registers websocket listener when socket exists before handshake', () => {
    const socketMock = {
      connected: false,
      on: vi.fn((event: string, listener: (data: { notification: { id: string } }) => void) => {
        eventHandlers.set(event, listener);
      }),
      disconnect: vi.fn(),
    };

    Object.defineProperty(service, 'socket', {
      value: socketMock,
      writable: true,
      configurable: true,
    });

    service.on<{ notification: { id: string } }>('notification.new');

    expect(eventHandlers.has('notification.new')).toBe(true);
  });

  it('forwards socket payloads to subscribers', () => {
    const socketMock = {
      connected: false,
      on: vi.fn((event: string, listener: (data: { notification: { id: string } }) => void) => {
        eventHandlers.set(event, listener);
      }),
      disconnect: vi.fn(),
    };

    Object.defineProperty(service, 'socket', {
      value: socketMock,
      writable: true,
      configurable: true,
    });

    const receivedIds: string[] = [];
    service.on<{ notification: { id: string } }>('notification.new').subscribe((payload) => {
      receivedIds.push(payload.notification.id);
    });

    const listener = eventHandlers.get('notification.new');
    listener?.({ notification: { id: 'n-1' } });

    expect(receivedIds).toEqual(['n-1']);
  });
});
