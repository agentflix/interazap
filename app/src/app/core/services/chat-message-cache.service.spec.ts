import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { ChatMessageCacheService } from './chat-message-cache.service';
import type { CalledMessage } from './called-message.service';
import type { ChatMessageStatusEvent } from './chat-realtime.events';

const createMessage = (id: string, overrides: Partial<CalledMessage> = {}): CalledMessage => ({
  id,
  ticket_id: 'ticket-1',
  content: 'Test message',
  direction: 'outgoing',
  status: 'pending',
  type: 'text',
  created_at: new Date().toISOString(),
  ...overrides,
});

const createStatusEvent = (
  messageId: string,
  status: ChatMessageStatusEvent['status'],
  extra: Partial<ChatMessageStatusEvent> = {},
): ChatMessageStatusEvent => ({
  message_id: messageId,
  status,
  ...extra,
});

describe('ChatMessageCacheService', () => {
  let service: ChatMessageCacheService;

  beforeEach(() => {
    service = new ChatMessageCacheService();
  });

  afterEach(() => {
    service.invalidateAll();
  });

  describe('getOrCreate', () => {
    it('should return same signal for same ticketId (idempotent)', () => {
      const signal1 = service.getOrCreate('ticket-1');
      const signal2 = service.getOrCreate('ticket-1');
      expect(signal1).toBe(signal2);
    });

    it('should create empty signal for new ticketId', () => {
      const signal = service.getOrCreate('ticket-new');
      expect(signal()).toEqual([]);
    });

    it('should evict oldest when reaching MAX_CACHE_SIZE (10)', () => {
      for (let i = 1; i <= 10; i++) {
        const s = service.getOrCreate(`ticket-${i}`);
        s.set([createMessage(`msg-${i}`)]);
      }

      service.getOrCreate('ticket-11');

      const signal1 = service.getOrCreate('ticket-1');
      expect(signal1()).toEqual([]);
    });
  });

  describe('prepend', () => {
    it('should add message to the beginning of the array', () => {
      const signal = service.getOrCreate('ticket-1');
      signal.set([createMessage('msg-1')]);

      service.prepend('ticket-1', createMessage('msg-2'));

      const messages = signal();
      expect(messages).toHaveLength(2);
      expect(messages[0].id).toBe('msg-2');
      expect(messages[1].id).toBe('msg-1');
    });

    it('should create entry if ticket does not exist', () => {
      service.prepend('ticket-new', createMessage('msg-1'));

      const signal = service.getOrCreate('ticket-new');
      expect(signal()).toHaveLength(1);
      expect(signal()[0].id).toBe('msg-1');
    });
  });

  describe('replace', () => {
    it('should replace message by tempId', () => {
      const signal = service.getOrCreate('ticket-1');
      signal.set([createMessage('temp-123', { status: 'pending' })]);

      const realMessage = createMessage('real-456', { status: 'sent' });
      service.replace('ticket-1', 'temp-123', realMessage);

      const messages = signal();
      expect(messages).toHaveLength(1);
      expect(messages[0].id).toBe('real-456');
      expect(messages[0].status).toBe('sent');
    });

    it('should append when tempId not found (fallback safe)', () => {
      const signal = service.getOrCreate('ticket-1');
      signal.set([createMessage('existing')]);

      const newMessage = createMessage('new-msg');
      service.replace('ticket-1', 'non-existent-id', newMessage);

      const messages = signal();
      expect(messages).toHaveLength(2);
      expect(messages[1].id).toBe('new-msg');
    });

    it('should create entry and prepend if ticket does not exist', () => {
      const realMessage = createMessage('real-msg');
      service.replace('ticket-new', 'temp-id', realMessage);

      const signal = service.getOrCreate('ticket-new');
      expect(signal()).toHaveLength(1);
      expect(signal()[0].id).toBe('real-msg');
    });
  });

  describe('updateStatus', () => {
    it('should update status by message_id', () => {
      const signal = service.getOrCreate('ticket-1');
      signal.set([createMessage('msg-1', { status: 'pending' })]);

      service.updateStatus(
        'ticket-1',
        createStatusEvent('msg-1', 'sent', { sent_at: '2026-03-18T10:00:00Z' }),
      );

      const messages = signal();
      expect(messages[0].status).toBe('sent');
      expect(messages[0].sent_at).toBe('2026-03-18T10:00:00Z');
    });

    it('should update status by external_id when message_id does not match', () => {
      const signal = service.getOrCreate('ticket-1');
      signal.set([createMessage('msg-1', { external_id: 'ext-123', status: 'pending' })]);

      service.updateStatus('ticket-1', {
        external_id: 'ext-123',
        status: 'delivered',
        delivered_at: '2026-03-18T10:01:00Z',
      });

      const messages = signal();
      expect(messages[0].status).toBe('delivered');
      expect(messages[0].delivered_at).toBe('2026-03-18T10:01:00Z');
    });

    it('should ignore silently when message does not exist in cache', () => {
      const signal = service.getOrCreate('ticket-1');
      signal.set([createMessage('msg-1')]);

      service.updateStatus('ticket-1', createStatusEvent('non-existent', 'failed'));

      expect(signal()[0].status).toBe('pending');
    });

    it('should do nothing when ticket does not exist', () => {
      service.updateStatus('ticket-non-existent', createStatusEvent('msg-1', 'sent'));
    });
  });

  describe('invalidate', () => {
    it('should remove only the specified ticket', () => {
      const signal1 = service.getOrCreate('ticket-1');
      signal1.set([createMessage('msg-1')]);
      const signal2 = service.getOrCreate('ticket-2');
      signal2.set([createMessage('msg-2')]);

      service.invalidate('ticket-1');

      expect(service.getOrCreate('ticket-1')()).toEqual([]);
      expect(service.getOrCreate('ticket-2')()).toHaveLength(1);
    });
  });

  describe('invalidateAll', () => {
    it('should clear the entire Map', () => {
      service.getOrCreate('ticket-1').set([createMessage('msg-1')]);
      service.getOrCreate('ticket-2').set([createMessage('msg-2')]);

      service.invalidateAll();

      expect(service.getOrCreate('ticket-1')()).toEqual([]);
      expect(service.getOrCreate('ticket-2')()).toEqual([]);
    });
  });

  describe('delegate pattern', () => {
    const createDelegate = () => ({
      getMessages: vi.fn().mockReturnValue([]),
      setMessages: vi.fn(),
    });

    it('should forward reads to delegate on getOrCreate', () => {
      const delegate = createDelegate();
      const delegateMessages = [createMessage('dm-1')];
      delegate.getMessages.mockReturnValue(delegateMessages);

      service.setDelegate(delegate);
      const sig = service.getOrCreate('ticket-1');

      expect(delegate.getMessages).toHaveBeenCalledWith('ticket-1');
      expect(sig()).toEqual(delegateMessages);
    });

    it('should sync from delegate when local signal is empty', () => {
      // Create a local empty signal first
      service.getOrCreate('ticket-1');

      const delegate = createDelegate();
      delegate.getMessages.mockReturnValue([createMessage('dm-1')]);
      service.setDelegate(delegate);

      // Re-access should sync from delegate
      const sig = service.getOrCreate('ticket-1');
      expect(sig()).toEqual([createMessage('dm-1')]);
    });

    it('should mirror prepend to delegate', () => {
      const delegate = createDelegate();
      service.setDelegate(delegate);

      service.prepend('ticket-1', createMessage('msg-1'));

      expect(delegate.setMessages).toHaveBeenCalledWith(
        'ticket-1',
        expect.arrayContaining([expect.objectContaining({ id: 'msg-1' })]),
      );
    });

    it('should mirror append to delegate', () => {
      const delegate = createDelegate();
      service.setDelegate(delegate);

      service.append('ticket-1', createMessage('msg-1'));

      expect(delegate.setMessages).toHaveBeenCalledWith(
        'ticket-1',
        expect.arrayContaining([expect.objectContaining({ id: 'msg-1' })]),
      );
    });

    it('should mirror setAll to delegate', () => {
      const delegate = createDelegate();
      service.setDelegate(delegate);

      const messages = [createMessage('msg-1'), createMessage('msg-2')];
      service.setAll('ticket-1', messages);

      expect(delegate.setMessages).toHaveBeenCalledWith('ticket-1', messages);
    });

    it('should mirror replace to delegate', () => {
      const delegate = createDelegate();
      service.setDelegate(delegate);

      const sig = service.getOrCreate('ticket-1');
      sig.set([createMessage('temp-1', { status: 'pending' })]);

      const realMsg = createMessage('real-1', { status: 'sent' });
      service.replace('ticket-1', 'temp-1', realMsg);

      expect(delegate.setMessages).toHaveBeenCalledWith(
        'ticket-1',
        expect.arrayContaining([expect.objectContaining({ id: 'real-1', status: 'sent' })]),
      );
    });

    it('should mirror updateStatus to delegate', () => {
      const delegate = createDelegate();
      service.setDelegate(delegate);

      const sig = service.getOrCreate('ticket-1');
      sig.set([createMessage('msg-1', { status: 'pending' })]);

      service.updateStatus('ticket-1', createStatusEvent('msg-1', 'sent'));

      expect(delegate.setMessages).toHaveBeenCalledWith(
        'ticket-1',
        expect.arrayContaining([expect.objectContaining({ id: 'msg-1', status: 'sent' })]),
      );
    });
  });
});
