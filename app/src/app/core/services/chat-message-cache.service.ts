import { Injectable, signal, type WritableSignal } from '@angular/core';
import type { CalledMessage } from './called-message.service';
import type { ChatMessageStatusEvent } from './chat-realtime.events';

/**
 * Maximum number of ticket caches held in memory.
 * When limit is reached, the oldest cache is evicted.
 */
const MAX_CACHE_SIZE = 10;

/**
 * Delegate interface for external message store (e.g. ChatStore).
 * When a delegate is registered, every write operation is mirrored to it,
 * making the delegate the central source of truth while preserving this
 * service's public API for consumers.
 */
export interface ChatMessageCacheDelegate {
  getMessages(ticketId: string): CalledMessage[];
  setMessages(ticketId: string, messages: CalledMessage[]): void;
}

/**
 * In-memory message cache per chat ticket.
 *
 * @remarks
 * Stores message arrays as signals, keyed by ticketId.
 * Supports prepend, replace, and status update operations.
 * When a delegate is registered via `setDelegate()`, reads and writes
 * are forwarded to the delegate, eliminating duplicate caches.
 */
@Injectable({ providedIn: 'root' })
export class ChatMessageCacheService {
  private readonly cache = new Map<string, WritableSignal<CalledMessage[]>>();
  private delegate: ChatMessageCacheDelegate | null = null;

  /**
   * Registers an external store as the central source of truth for messages.
   * Once set, read/write operations are forwarded to the delegate.
   *
   * @param delegate - The external message store implementing ChatMessageCacheDelegate
   */
  setDelegate(delegate: ChatMessageCacheDelegate): void {
    this.delegate = delegate;
  }

  /**
   * Gets an existing cache signal for a ticket or creates a new empty one.
   * When a delegate is set, the signal is synced from the delegate on first access.
   *
   * @param ticketId - The ticket identifier
   * @returns WritableSignal containing the message array for this ticket
   */
  getOrCreate(ticketId: string): WritableSignal<CalledMessage[]> {
    const existing = this.cache.get(ticketId);
    if (existing !== undefined) {
      // Sync from delegate if available
      if (this.delegate !== null) {
        const delegateMessages = this.delegate.getMessages(ticketId);
        if (delegateMessages.length > 0 && existing().length === 0) {
          existing.set(delegateMessages);
        }
      }
      return existing;
    }

    if (this.cache.size >= MAX_CACHE_SIZE) {
      const oldestKey = this.evictOldest();
      if (oldestKey !== null) {
        this.cache.delete(oldestKey);
      }
    }

    const initialMessages = this.delegate !== null ? this.delegate.getMessages(ticketId) : [];
    const newSignal = signal<CalledMessage[]>(initialMessages);
    this.cache.set(ticketId, newSignal);
    return newSignal;
  }

  /**
   * Prepends a message to the beginning of the ticket's cache.
   *
   * @param ticketId - The ticket identifier
   * @param message - The message to prepend
   */
  prepend(ticketId: string, message: CalledMessage): void {
    const sig = this.getOrCreate(ticketId);
    sig.update((messages) => {
      const next = [message, ...messages];
      this.delegate?.setMessages(ticketId, next);
      return next;
    });
  }

  /**
   * Appends a message to the end of the ticket's cache.
   *
   * @param ticketId - The ticket identifier
   * @param message - The message to append
   */
  append(ticketId: string, message: CalledMessage): void {
    const sig = this.getOrCreate(ticketId);
    sig.update((messages) => {
      const next = [...messages, message];
      this.delegate?.setMessages(ticketId, next);
      return next;
    });
  }

  /**
   * Replaces all messages in a ticket's cache with the provided array.
   *
   * @param ticketId - The ticket identifier
   * @param messages - Complete message array to set
   */
  setAll(ticketId: string, messages: CalledMessage[]): void {
    const sig = this.getOrCreate(ticketId);
    sig.set(messages);
    this.delegate?.setMessages(ticketId, messages);
  }

  /**
   * Replaces a temporary (optimistic) message with the confirmed message from server.
   *
   * @param ticketId - The ticket identifier
   * @param tempId - The temporary message ID to replace
   * @param realMessage - The confirmed message from server
   */
  replace(ticketId: string, tempId: string, realMessage: CalledMessage): void {
    const sig = this.cache.get(ticketId);
    if (sig === undefined) {
      this.getOrCreate(ticketId);
      this.append(ticketId, realMessage);
      return;
    }

    let replaced = false;
    sig.update((messages) => {
      // Find optimistic message by id or external_id (it may already be replaced by websocket)
      const optimisticIdx = messages.findIndex(
        (m) => String(m.id) === String(tempId) || m.external_id === tempId,
      );

      // Check if real message already exists (from websocket delivery)
      const realExistsIdx = messages.findIndex((m) => String(m.id) === String(realMessage.id));

      if (optimisticIdx !== -1) {
        // Found optimistic message - replace it with real
        replaced = true;
        const updated = [...messages];
        updated[optimisticIdx] = realMessage;
        // If real message was also there as separate entry, remove duplicate
        if (realExistsIdx !== -1 && realExistsIdx !== optimisticIdx) {
          updated.splice(realExistsIdx, 1);
        }
        this.delegate?.setMessages(ticketId, updated);
        return updated;
      }

      if (realExistsIdx !== -1) {
        // Real message exists but no optimistic to replace - nothing to do
        replaced = true;
        return messages;
      }

      // Neither exists - append
      return messages;
    });

    if (!replaced) {
      sig.update((messages) => {
        const next = [...messages, realMessage];
        this.delegate?.setMessages(ticketId, next);
        return next;
      });
    }
  }

  /**
   * Updates message status (sent, delivered, read) in the ticket's cache.
   *
   * @param ticketId - The ticket identifier
   * @param event - Status event with message_id or external_id to match
   */
  updateStatus(ticketId: string, event: ChatMessageStatusEvent): void {
    const sig = this.cache.get(ticketId);
    if (sig === undefined) {
      return;
    }

    sig.update((messages) => {
      let updated = false;
      const newMessages = messages.map((m) => {
        const matchById =
          event.message_id !== undefined && String(m.id) === String(event.message_id);
        const matchByExternalId =
          event.external_id !== undefined && m.external_id === event.external_id;

        if (matchById || matchByExternalId) {
          updated = true;
          const updatedMessage = { ...m };

          if (event.status !== undefined) {
            updatedMessage.status = event.status;
          }
          if (event.sent_at !== undefined) {
            updatedMessage.sent_at = event.sent_at;
          }
          if (event.delivered_at !== undefined) {
            updatedMessage.delivered_at = event.delivered_at;
          }
          if (event.read_at !== undefined) {
            updatedMessage.read_at = event.read_at;
          }
          if (event.file_url !== undefined) {
            updatedMessage.file_url = event.file_url;
          }
          if (event.file_name !== undefined) {
            updatedMessage.file_name = event.file_name;
          }
          if (event.mime_type !== undefined) {
            updatedMessage.mime_type = event.mime_type;
          }
          if (event.file_size !== undefined) {
            updatedMessage.file_size = event.file_size;
          }
          if (event.media_transcription !== undefined) {
            updatedMessage.media_transcription = event.media_transcription;
          }
          if (event.media_transcription_status !== undefined) {
            updatedMessage.media_transcription_status = event.media_transcription_status;
          }

          return updatedMessage;
        }
        return m;
      });

      if (updated) {
        this.delegate?.setMessages(ticketId, newMessages);
      }
      return updated ? newMessages : messages;
    });
  }

  /**
   * Removes the cache entry for a specific ticket.
   *
   * @param ticketId - The ticket identifier
   */
  invalidate(ticketId: string): void {
    this.cache.delete(ticketId);
  }

  /**
   * Clears all cached message arrays.
   */
  invalidateAll(): void {
    this.cache.clear();
  }

  private evictOldest(): string | null {
    const keys = Array.from(this.cache.keys());
    return keys.length > 0 ? keys[0] : null;
  }
}
