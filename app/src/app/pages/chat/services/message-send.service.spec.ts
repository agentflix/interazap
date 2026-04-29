import { computed, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { HttpErrorResponse } from '@angular/common/http';
import { Subject, firstValueFrom, of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { type CalledMessage } from 'src/app/core/services/called-message.service';
import { CalledMessageService } from 'src/app/core/services/called-message.service';
import { NetworkStatusService } from 'src/app/core/services/network-status.service';
import {
  OfflineQueueService,
  type OfflineQueuedMessage,
} from 'src/app/core/services/platform/offline-queue.service';
import { MessageSendService } from './message-send.service';

class NetworkStatusServiceStub {
  online = true;
  readonly statusChangesSubject = new Subject<boolean>();
  readonly statusChanges$ = this.statusChangesSubject.asObservable();

  startMonitoring = vi.fn();

  markOffline = vi.fn(() => {
    this.online = false;
    this.statusChangesSubject.next(false);
  });

  markOnline = vi.fn(() => {
    this.online = true;
    this.statusChangesSubject.next(true);
  });
}

class OfflineQueueServiceStub {
  private readonly queueSignal = signal<OfflineQueuedMessage[]>([]);
  readonly queue = this.queueSignal.asReadonly();
  readonly size = computed(() => this.queueSignal().length);

  hydrate = vi.fn(async () => undefined);

  getSnapshot(): OfflineQueuedMessage[] {
    return this.queueSignal();
  }

  pendingCountForTicket(calledId: string | null): number {
    if (!calledId) {
      return 0;
    }
    return this.queueSignal().filter((item) => item.calledId === calledId).length;
  }

  enqueue = vi.fn(async (item: OfflineQueuedMessage) => {
    this.queueSignal.update((queue) => [...queue, item]);
  });

  remove = vi.fn(async (itemId: string) => {
    this.queueSignal.update((queue) => queue.filter((item) => item.id !== itemId));
  });

  incrementAttempts = vi.fn(async (itemId: string) => {
    this.queueSignal.update((queue) =>
      queue.map((item) => (item.id === itemId ? { ...item, attempts: item.attempts + 1 } : item)),
    );
  });

  seed(items: OfflineQueuedMessage[]): void {
    this.queueSignal.set(items);
  }
}

class CalledMessageServiceStub {
  send = vi.fn(() =>
    of({
      data: {
        id: 'msg-1',
        ticket_id: 'ticket-1',
        direction: 'outgoing',
        status: 'sent',
      } satisfies CalledMessage,
    }),
  );
}

function createQueuedMessage(overrides: Partial<OfflineQueuedMessage> = {}): OfflineQueuedMessage {
  return {
    id: 'queue-1',
    calledId: 'ticket-1',
    content: 'Mensagem',
    type: 'text',
    clientMessageId: 'client-1',
    createdAt: '2026-04-27T12:00:00.000Z',
    attempts: 0,
    ...overrides,
  };
}

describe('MessageSendService', () => {
  let service: MessageSendService;
  let calledMessageService: CalledMessageServiceStub;
  let networkStatus: NetworkStatusServiceStub;
  let offlineQueue: OfflineQueueServiceStub;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        MessageSendService,
        { provide: CalledMessageService, useClass: CalledMessageServiceStub },
        { provide: NetworkStatusService, useClass: NetworkStatusServiceStub },
        { provide: OfflineQueueService, useClass: OfflineQueueServiceStub },
      ],
    });

    service = TestBed.inject(MessageSendService);
    calledMessageService = TestBed.inject(
      CalledMessageService,
    ) as unknown as CalledMessageServiceStub;
    networkStatus = TestBed.inject(NetworkStatusService) as unknown as NetworkStatusServiceStub;
    offlineQueue = TestBed.inject(OfflineQueueService) as unknown as OfflineQueueServiceStub;

    vi.clearAllMocks();
  });

  it('initialize hidrata fila e inicia monitoramento', async () => {
    networkStatus.online = false;

    await service.initialize();

    expect(offlineQueue.hydrate).toHaveBeenCalledTimes(1);
    expect(networkStatus.startMonitoring).toHaveBeenCalledTimes(1);
  });

  it('sendText enfileira quando offline', async () => {
    networkStatus.online = false;

    const result = await firstValueFrom(service.sendText('ticket-1', 'Mensagem offline', 'temp-1'));

    expect(result.status).toBe('queued');
    expect(offlineQueue.enqueue).toHaveBeenCalledTimes(1);
    expect(calledMessageService.send).not.toHaveBeenCalled();
  });

  it('sendText envia imediatamente quando online', async () => {
    networkStatus.online = true;

    const result = await firstValueFrom(service.sendText('ticket-1', 'Mensagem online', 'temp-1'));

    expect(result.status).toBe('sent');
    expect(calledMessageService.send).toHaveBeenCalledWith(
      'ticket-1',
      'Mensagem online',
      'text',
      undefined,
      undefined,
      'temp-1',
    );
  });

  it('sendText enfileira quando request falha por offline (status 0)', async () => {
    networkStatus.online = true;
    calledMessageService.send.mockReturnValueOnce(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 0,
            statusText: 'Unknown Error',
          }),
      ),
    );

    const result = await firstValueFrom(service.sendText('ticket-1', 'Mensagem', 'temp-1'));

    expect(result.status).toBe('queued');
    expect(networkStatus.markOffline).toHaveBeenCalledTimes(1);
    expect(offlineQueue.enqueue).toHaveBeenCalledTimes(1);
  });

  it('flushQueue envia em FIFO e emite evento de entrega', async () => {
    networkStatus.online = true;
    offlineQueue.seed([
      createQueuedMessage({ id: 'queue-1', clientMessageId: 'temp-1' }),
      createQueuedMessage({ id: 'queue-2', clientMessageId: 'temp-2' }),
    ]);

    const delivered: string[] = [];
    service.queueDelivered$.subscribe((event) => delivered.push(event.clientMessageId));

    await service.flushQueue();

    expect(calledMessageService.send).toHaveBeenNthCalledWith(
      1,
      'ticket-1',
      'Mensagem',
      'text',
      undefined,
      undefined,
      'temp-1',
    );
    expect(calledMessageService.send).toHaveBeenNthCalledWith(
      2,
      'ticket-1',
      'Mensagem',
      'text',
      undefined,
      undefined,
      'temp-2',
    );
    expect(delivered).toEqual(['temp-1', 'temp-2']);
    expect(offlineQueue.getSnapshot()).toEqual([]);
  });

  it('flushQueue incrementa attempts e emite falha em erro não-offline', async () => {
    networkStatus.online = true;
    offlineQueue.seed([createQueuedMessage({ id: 'queue-1', clientMessageId: 'temp-1' })]);

    calledMessageService.send.mockReturnValueOnce(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 422,
            statusText: 'Unprocessable Entity',
          }),
      ),
    );

    const failed: string[] = [];
    service.queueFailed$.subscribe((event) => failed.push(event.clientMessageId));

    await service.flushQueue();

    expect(offlineQueue.incrementAttempts).toHaveBeenCalledWith('queue-1');
    expect(failed).toEqual(['temp-1']);
  });
});
