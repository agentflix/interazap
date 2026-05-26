import { Injectable, effect, inject, type OnDestroy } from '@angular/core';
import {
  ChatRealtimeService,
  type IntegrationConnectionEvent,
} from '@core/services/chat-realtime.service';
import { shouldFlushIntegrationConnectionImmediately } from '@core/services/integration.service';
import { BufferedRealtimeAdapter } from '../../../core/realtime/buffered-realtime-adapter';

/**
 * Adaptador para eventos realtime de conexão de integrações (canais).
 *
 * @remarks
 * Escuta os eventos de conexão de integração do ChatRealtimeService,
 * aplica buffer e publica no serviço realtime quando apropriado.
 *
 * @example
 * ```typescript
 * const adapter = new ChannelRealtimeAdapter();
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class ChannelRealtimeAdapter implements OnDestroy {
  private readonly realtimeService = inject(ChatRealtimeService);
  private lastProcessedVersion = 0;

  private readonly bufferedAdapter = new BufferedRealtimeAdapter<IntegrationConnectionEvent>({
    windowMs: 120,
    strategy: 'debounce',
    flushImmediately: (event) => shouldFlushIntegrationConnectionImmediately(event),
    onFlush: (events) => {
      const latest = events.at(-1);
      if (latest) {
        this.realtimeService.publishBufferedIntegrationConnection(latest);
      }
    },
  });

  constructor() {
    effect(() => {
      const payload = this.realtimeService.integrationConnectionIncoming();
      if (payload.version <= 0 || payload.version === this.lastProcessedVersion || !payload.event) {
        return;
      }

      this.lastProcessedVersion = payload.version;
      this.bufferedAdapter.push(payload.event);
    });
  }

  ngOnDestroy(): void {
    this.bufferedAdapter.dispose();
  }
}
