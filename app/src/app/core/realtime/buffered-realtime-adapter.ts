type BufferStrategy = 'window' | 'debounce';

/** Configuration for buffered realtime adapters. */
export interface BufferedRealtimeAdapterOptions<TEvent> {
  windowMs: number;
  strategy: BufferStrategy;
  onFlush: (events: TEvent[]) => void;
  dedupeKeyResolver?: (event: TEvent) => string;
  flushImmediately?: (event: TEvent) => boolean;
}

/** Reusable buffering adapter for realtime event streams. */
export class BufferedRealtimeAdapter<TEvent> {
  private readonly buffer: TEvent[] = [];
  private timer: ReturnType<typeof setTimeout> | null = null;

  constructor(private readonly options: BufferedRealtimeAdapterOptions<TEvent>) {}

  /** Adds a new event into the buffer strategy pipeline. */
  push(event: TEvent): void {
    if (this.options.flushImmediately?.(event) === true) {
      this.flushBuffer();
      this.options.onFlush([event]);
      return;
    }

    this.buffer.push(event);
    this.scheduleFlush();
  }

  /** Disposes timers and pending buffered state. */
  dispose(): void {
    this.clearTimer();
    this.buffer.length = 0;
  }

  private scheduleFlush(): void {
    if (this.options.strategy === 'window') {
      if (this.timer !== null) {
        return;
      }
      this.timer = setTimeout(() => {
        this.timer = null;
        this.flushBuffer();
      }, this.options.windowMs);
      return;
    }

    this.clearTimer();
    this.timer = setTimeout(() => {
      this.timer = null;
      this.flushBuffer();
    }, this.options.windowMs);
  }

  private flushBuffer(): void {
    if (this.buffer.length === 0) {
      return;
    }

    const next = this.options.dedupeKeyResolver
      ? this.deduplicate(this.buffer, this.options.dedupeKeyResolver)
      : [...this.buffer];
    this.buffer.length = 0;

    if (next.length > 0) {
      this.options.onFlush(next);
    }
  }

  private deduplicate(events: readonly TEvent[], resolveKey: (event: TEvent) => string): TEvent[] {
    const seen = new Set<string>();
    const deduped: TEvent[] = [];

    for (let index = events.length - 1; index >= 0; index -= 1) {
      const current = events[index];
      const key = resolveKey(current);
      if (!seen.has(key)) {
        seen.add(key);
        deduped.unshift(current);
      }
    }

    return deduped;
  }

  private clearTimer(): void {
    if (this.timer !== null) {
      clearTimeout(this.timer);
      this.timer = null;
    }
  }
}
