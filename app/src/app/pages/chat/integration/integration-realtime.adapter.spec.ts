import { signal, type WritableSignal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import {
  ChatRealtimeService,
  type IntegrationConnectionEvent,
} from '@core/services/chat-realtime.service';
import { IntegrationRealtimeAdapter } from './integration-realtime.adapter';

describe('IntegrationRealtimeAdapter', () => {
  let incomingSignal: WritableSignal<{
    event: IntegrationConnectionEvent | null;
    version: number;
  }>;
  let publishBufferedIntegrationConnection: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    vi.useFakeTimers();
    incomingSignal = signal({ event: null as IntegrationConnectionEvent | null, version: 0 });
    publishBufferedIntegrationConnection = vi.fn();

    TestBed.configureTestingModule({
      providers: [
        IntegrationRealtimeAdapter,
        {
          provide: ChatRealtimeService,
          useValue: {
            integrationConnectionIncoming: incomingSignal,
            publishBufferedIntegrationConnection,
          },
        },
      ],
    });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('buffers transitional integration events and flushes the latest after 120ms', () => {
    TestBed.inject(IntegrationRealtimeAdapter);

    incomingSignal.set({
      event: { status: 'connecting', token: 'instance-1' },
      version: 1,
    });
    TestBed.flushEffects();

    incomingSignal.set({
      event: { status: 'pending', token: 'instance-1' },
      version: 2,
    });
    TestBed.flushEffects();

    expect(publishBufferedIntegrationConnection).not.toHaveBeenCalled();

    vi.advanceTimersByTime(120);

    expect(publishBufferedIntegrationConnection).toHaveBeenCalledTimes(1);
    const lastCallArg = publishBufferedIntegrationConnection.mock.calls.at(-1)?.[0] as
      | IntegrationConnectionEvent
      | undefined;
    expect(lastCallArg).toEqual(
      expect.objectContaining({ status: 'pending', token: 'instance-1' }),
    );
  });

  it('flushes terminal statuses immediately', () => {
    TestBed.inject(IntegrationRealtimeAdapter);

    incomingSignal.set({
      event: { status: 'connected', token: 'instance-2' },
      version: 1,
    });
    TestBed.flushEffects();

    expect(publishBufferedIntegrationConnection).toHaveBeenCalledTimes(1);
    expect(publishBufferedIntegrationConnection).toHaveBeenCalledWith(
      expect.objectContaining({ status: 'connected' }),
    );
  });

  it.each(['authorized', 'ready', 'open'] as const)(
    'flushes connected alias %s immediately through the adapter path',
    (status) => {
      TestBed.inject(IntegrationRealtimeAdapter);

      incomingSignal.set({
        event: { status, token: 'instance-3', connected: false },
        version: 1,
      });
      TestBed.flushEffects();

      expect(publishBufferedIntegrationConnection).toHaveBeenCalledTimes(1);
      expect(publishBufferedIntegrationConnection).toHaveBeenCalledWith(
        expect.objectContaining({ status, token: 'instance-3', connected: false }),
      );
    },
  );
});
