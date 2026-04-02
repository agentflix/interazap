import { Injectable, effect, inject, type OnDestroy } from '@angular/core';
import {
  ChatRealtimeService,
  type IntegrationConnectionEvent,
} from '@core/services/chat-realtime.service';
import { shouldFlushIntegrationConnectionImmediately } from '@core/services/integration.service';
import { BufferedRealtimeAdapter } from '../../../core/realtime/buffered-realtime-adapter';

/**
 * Adapter for integration realtime connection events.
 *
 * @remarks
 * Listens to integration connection events from ChatRealtimeService,
 * buffers them, and publishes to the realtime service when appropriate.
 *
 * @example
 * ```typescript
 * const adapter = new IntegrationRealtimeAdapter();
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class IntegrationRealtimeAdapter implements OnDestroy {
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
