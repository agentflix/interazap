import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { ChatStore } from './chat.store';
import { CalledService } from '@core/services/called.service';
import { CalledMessageService } from '@core/services/called-message.service';

describe('ChatStore streaming state', () => {
  let store: ChatStore;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        ChatStore,
        {
          provide: CalledService,
          useValue: {
            get: vi.fn().mockReturnValue(of({ data: { id: 'ticket-1', status: 'open' } })),
          },
        },
        {
          provide: CalledMessageService,
          useValue: {
            list: vi.fn().mockReturnValue(of({ data: { messages: [] } })),
          },
        },
      ],
    });

    store = TestBed.inject(ChatStore);
  });

  it('tracks and clears ai.run.streaming payloads by ticket', () => {
    store.applyBatch([
      {
        type: 'ai.run.streaming',
        timestamp: Date.now(),
        data: {
          ticket_id: 'ticket-1',
          run_id: 'run-1',
          chunk: 'Olá ',
          is_final: false,
        },
      },
      {
        type: 'ai.run.streaming',
        timestamp: Date.now() + 1,
        data: {
          ticket_id: 'ticket-1',
          run_id: 'run-1',
          chunk: 'mundo',
          is_final: true,
        },
      },
    ]);

    expect(store.streamingMessages().has('ticket-1')).toBe(false);
  });

  it('stores explicit ai processing lifecycle status by ticket', () => {
    store.selectTicket('ticket-1');

    store.applyBatch([
      {
        type: 'ai.processing.started',
        timestamp: Date.now(),
        data: {
          ticket_id: 'ticket-1',
          run_id: 'run-9',
          message_id: 'msg-9',
          status: 'queued',
        },
      },
      {
        type: 'ai.processing.completed',
        timestamp: Date.now() + 10,
        data: {
          ticket_id: 'ticket-1',
          run_id: 'run-9',
          message_id: 'msg-9',
          status: 'completed',
        },
      },
    ]);

    expect(store.selectedTicketAiProcessing()).toEqual(
      expect.objectContaining({
        runId: 'run-9',
        messageId: 'msg-9',
        status: 'completed',
      }),
    );
  });

  it('keeps failure context for explicit ai processing failures', () => {
    store.applyBatch([
      {
        type: 'ai.processing.failed',
        timestamp: Date.now(),
        data: {
          ticket_id: 'ticket-1',
          run_id: 'run-10',
          status: 'failed',
          error: 'provider timeout',
        },
      },
    ]);

    expect(store.aiProcessingByTicket().get('ticket-1')).toEqual(
      expect.objectContaining({
        runId: 'run-10',
        status: 'failed',
        error: 'provider timeout',
      }),
    );
  });
});
