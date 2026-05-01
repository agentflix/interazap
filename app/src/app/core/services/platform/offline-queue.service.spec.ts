import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { OfflineQueueService, type OfflineQueuedMessage } from './offline-queue.service';
import { PlatformService } from './platform.service';

const STORAGE_KEY = 'chat_offline_queue_v1';

function createQueuedMessage(overrides: Partial<OfflineQueuedMessage> = {}): OfflineQueuedMessage {
  return {
    id: 'queue-1',
    calledId: 'ticket-1',
    content: 'Mensagem offline',
    type: 'text',
    clientMessageId: 'client-1',
    createdAt: '2026-04-27T12:00:00.000Z',
    attempts: 0,
    ...overrides,
  };
}

describe('OfflineQueueService', () => {
  let service: OfflineQueueService;
  let isMobile = false;

  beforeEach(() => {
    isMobile = false;
    localStorage.removeItem(STORAGE_KEY);

    TestBed.configureTestingModule({
      providers: [
        OfflineQueueService,
        {
          provide: PlatformService,
          useValue: {
            get isMobile() {
              return isMobile;
            },
          },
        },
      ],
    });

    service = TestBed.inject(OfflineQueueService);
    vi.clearAllMocks();
  });

  it('hydrate carrega fila da web e mantém ordem FIFO', async () => {
    const first = createQueuedMessage({ id: 'queue-1', createdAt: '2026-01-01T10:00:00.000Z' });
    const second = createQueuedMessage({ id: 'queue-2', createdAt: '2026-01-01T10:01:00.000Z' });
    localStorage.setItem(STORAGE_KEY, JSON.stringify([first, second]));

    await service.hydrate();

    expect(service.getSnapshot()).toEqual([first, second]);
    expect(service.size()).toBe(2);
  });

  it('enqueue persiste item e incrementa contagem por ticket', async () => {
    await service.hydrate();

    await service.enqueue(createQueuedMessage({ id: 'queue-1', calledId: 'ticket-1' }));
    await service.enqueue(createQueuedMessage({ id: 'queue-2', calledId: 'ticket-1' }));
    await service.enqueue(createQueuedMessage({ id: 'queue-3', calledId: 'ticket-2' }));

    expect(service.size()).toBe(3);
    expect(service.pendingCountForTicket('ticket-1')).toBe(2);
    expect(service.pendingCountForTicket('ticket-2')).toBe(1);
  });

  it('remove e incrementAttempts atualizam fila persistida', async () => {
    await service.hydrate();

    await service.enqueue(createQueuedMessage({ id: 'queue-1' }));
    await service.enqueue(createQueuedMessage({ id: 'queue-2' }));

    await service.incrementAttempts('queue-1');
    expect(service.getSnapshot()[0]?.attempts).toBe(1);

    await service.remove('queue-1');

    expect(service.getSnapshot()).toEqual([expect.objectContaining({ id: 'queue-2' })]);
  });

  it('em mobile usa storage nativo (Preferences) para persistência', async () => {
    isMobile = true;

    const servicePrivate = service as object as {
      readFromNativeStorage: () => Promise<string | null>;
      writeToNativeStorage: (value: string) => Promise<void>;
    };

    const readFromNativeStorage = vi
      .spyOn(servicePrivate, 'readFromNativeStorage')
      .mockResolvedValue(JSON.stringify([createQueuedMessage()]));
    const writeToNativeStorage = vi
      .spyOn(servicePrivate, 'writeToNativeStorage')
      .mockResolvedValue(undefined);

    await service.hydrate();
    await service.enqueue(createQueuedMessage({ id: 'queue-2' }));

    expect(readFromNativeStorage).toHaveBeenCalledTimes(1);
    expect(writeToNativeStorage).toHaveBeenCalledTimes(1);
  });

  it('clear zera fila e storage', async () => {
    await service.hydrate();
    await service.enqueue(createQueuedMessage());

    await service.clear();

    expect(service.getSnapshot()).toEqual([]);
    expect(service.size()).toBe(0);
  });
});
