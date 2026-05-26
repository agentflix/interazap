import { Injectable, signal, type WritableSignal } from '@angular/core';
import type { CalledMessage } from './called-message.service';
import type { ChatMessageStatusEvent } from './chat-realtime.events';
import type { ChatMessageCacheDelegate } from '@core/models/chat-message-cache.model';
export type { ChatMessageCacheDelegate } from '@core/models/chat-message-cache.model';


/**
 * Número máximo de caches de ticket mantidos em memória.
 * Quando o limite é atingido, o cache mais antigo é descartado.
 */
const MAX_CACHE_SIZE = 10;

/**
 * Delegate interface for external message store (e.g. ChatStore).
 * When a delegate is registered, every write operation is mirrored to it,
 * making the delegate the central source of truth while preserving this
 * service's public API for consumers.
 */

/**
 * Cache de mensagens em memória por ticket de chat.
 *
 * @remarks
 * Armazena arrays de mensagens como signals, indexados por `ticketId`.
 * Suporta operações de prepend, replace e atualização de status.
 * Quando um delegate é registrado via `setDelegate()`, leituras e escritas
 * são encaminhadas ao delegate, eliminando caches duplicados.
 */
@Injectable({ providedIn: 'root' })
export class ChatMessageCacheService {
  private readonly cache = new Map<string, WritableSignal<CalledMessage[]>>();
  private delegate: ChatMessageCacheDelegate | null = null;

  /**
   * Registra um store externo como fonte de verdade central para mensagens.
   * Uma vez definido, operações de leitura/escrita são encaminhadas ao delegate.
   *
   * @param delegate - Store externo de mensagens que implementa `ChatMessageCacheDelegate`
   */
  setDelegate(delegate: ChatMessageCacheDelegate): void {
    this.delegate = delegate;
  }

  /**
   * Retorna o signal de cache existente para um ticket ou cria um novo vazio.
   * Quando um delegate está definido, o signal é sincronizado com o delegate no primeiro acesso.
   *
   * @param ticketId - Identificador do ticket
   * @returns `WritableSignal` contendo o array de mensagens do ticket
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
   * Insere uma mensagem no início do cache do ticket.
   *
   * @param ticketId - Identificador do ticket
   * @param message - Mensagem a inserir no início
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
   * Adiciona uma mensagem ao final do cache do ticket.
   *
   * @param ticketId - Identificador do ticket
   * @param message - Mensagem a adicionar ao final
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
   * Substitui todas as mensagens do cache do ticket pelo array fornecido.
   *
   * @param ticketId - Identificador do ticket
   * @param messages - Array completo de mensagens a definir
   */
  setAll(ticketId: string, messages: CalledMessage[]): void {
    const sig = this.getOrCreate(ticketId);
    sig.set(messages);
    this.delegate?.setMessages(ticketId, messages);
  }

  /**
   * Substitui uma mensagem temporária (otimista) pela mensagem confirmada pelo servidor.
   *
   * @param ticketId - Identificador do ticket
   * @param tempId - ID temporário da mensagem a substituir
   * @param realMessage - Mensagem confirmada pelo servidor
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
   * Atualiza o status (enviado, entregue, lido) de uma mensagem no cache do ticket.
   *
   * @param ticketId - Identificador do ticket
   * @param event - Evento de status contendo `message_id` ou `external_id` para localizar a mensagem
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
   * Remove a entrada de cache de um ticket específico.
   *
   * @param ticketId - Identificador do ticket
   */
  invalidate(ticketId: string): void {
    this.cache.delete(ticketId);
  }

  /**
   * Limpa todos os arrays de mensagens em cache.
   */
  invalidateAll(): void {
    this.cache.clear();
  }

  private evictOldest(): string | null {
    const keys = Array.from(this.cache.keys());
    return keys.length > 0 ? keys[0] : null;
  }
}
