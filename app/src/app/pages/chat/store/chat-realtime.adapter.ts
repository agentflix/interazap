import { Injectable, inject, effect, type OnDestroy } from '@angular/core';
import {
  ChatRealtimeService,
  type ChatActivitySubevent,
} from '@core/services/chat-realtime.service';
import { ChatRewriteRolloutService } from '@core/services/chat-rewrite-rollout.service';
import { BufferedRealtimeAdapter } from '../../../core/realtime/buffered-realtime-adapter';
import { ChatStore, type ChatRealtimeAdapterEvent } from './chat.store';

/**
 * Adaptador que conecta os eventos do serviço realtime ao ChatStore.
 *
 * @remarks
 * Escuta o stream unificado de atividades, eventos de edição e digitação
 * do ChatRealtimeService e os encaminha ao ChatStore via adaptador com
 * buffer para deduplicação e processamento em lote.
 *
 * @example
 * ```typescript
 * // Adaptador é instanciado automaticamente quando o ChatStore é utilizado
 * const adapter = new ChatRealtimeAdapter();
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class ChatRealtimeAdapter implements OnDestroy {
  private readonly realtimeService = inject(ChatRealtimeService);
  private readonly chatRewriteRollout = inject(ChatRewriteRolloutService);
  private readonly chatStore = inject(ChatStore);
  private hasConnectedAtLeastOnce = false;
  private wasConnected = false;
  private readonly bufferedAdapter = new BufferedRealtimeAdapter<ChatRealtimeAdapterEvent>({
    windowMs: 100,
    strategy: 'window',
    onFlush: (events) => {
      this.chatStore.applyBatch(events);
    },
    dedupeKeyResolver: (event) => this.resolveDeduplicationKey(event),
  });

  constructor() {
    if (!this.chatRewriteRollout.isEnabledForCurrentUser()) {
      return;
    }
    this.setupListeners();
  }

  private setupListeners(): void {
    effect(() => {
      const isConnected = this.realtimeService.connected();

      if (isConnected) {
        if (this.hasConnectedAtLeastOnce && !this.wasConnected) {
          this.chatStore.resyncSelectedTicketOnReconnect();
        }
        this.hasConnectedAtLeastOnce = true;
      }

      this.wasConnected = isConnected;
    });

    // Escuta a stream unificada de Activity para reduzir overhead (chat.activity)
    effect(() => {
      const { event, version } = this.realtimeService.activity();
      if (event && version > 0) {
        // Quando há uma activity, envia todos os subevents para o buffer
        event.subevents.forEach((subevent: ChatActivitySubevent) => {
          this.bufferedAdapter.push({
            type: subevent.type,
            data: subevent.data,
            timestamp: Date.now(),
          });
        });
      }
    });

    effect(() => {
      const { event, version } = this.realtimeService.edit();
      if (event !== null && event !== undefined && version > 0) {
        this.bufferedAdapter.push({
          type: 'msg.edit',
          data: event,
          timestamp: Date.now(),
        });
      }
    });

    // Handle typing events that are not part of activity
    effect(() => {
      const { event, version } = this.realtimeService.typing();
      if (event !== null && event !== undefined && version > 0) {
        this.bufferedAdapter.push({
          type: 'typing',
          data: event,
          timestamp: Date.now(),
        });
      }
    });
  }

  /**
   * Resolves a deduplication key for an event to prevent duplicate processing.
   *
   * @param event - The realtime adapter event
   * @returns A unique key string for deduplication
   */
  private resolveDeduplicationKey(event: ChatRealtimeAdapterEvent): string {
    if (event.data !== null && typeof event.data === 'object') {
      const toKeyPart = (value: unknown): string | null => {
        if (typeof value !== 'string') {
          return null;
        }

        const normalized = value.trim();
        return normalized.length > 0 ? normalized : null;
      };

      const typedData = event.data as {
        message_id?: string;
        ticket_id?: string;
        external_id?: string;
        chat_id?: string;
        edited_at?: string;
        content?: string;
      };

      if (event.type === 'msg.edit' || event.type === 'chat.message.edit') {
        const messageId = toKeyPart(typedData.message_id);
        if (messageId !== null) {
          const ticketId = toKeyPart(typedData.ticket_id) ?? '__missing_ticket__';
          const externalId = toKeyPart(typedData.external_id) ?? '__no_external__';
          const chatId = toKeyPart(typedData.chat_id) ?? '__no_chat__';
          const editedAt = toKeyPart(typedData.edited_at);
          const content = toKeyPart(typedData.content);
          const revisionKey =
            editedAt ?? (content !== null ? `content:${content}` : '__no_revision__');

          return `${event.type}:${messageId}:ticket:${ticketId}:external:${externalId}:chat:${chatId}:revision:${revisionKey}`;
        }
      }

      const messageId = toKeyPart(typedData.message_id);
      if (messageId !== null) {
        return `${event.type}:${messageId}`;
      }

      const ticketId = toKeyPart(typedData.ticket_id);
      if (ticketId !== null) {
        return `${event.type}:${ticketId}`;
      }
    }
    return event.type;
  }

  ngOnDestroy(): void {
    this.bufferedAdapter.dispose();
  }
}
