type BufferStrategy = 'window' | 'debounce';

/** Configuração para adaptadores de eventos em tempo real com buffer. */
export interface BufferedRealtimeAdapterOptions<TEvent> {
  windowMs: number;
  strategy: BufferStrategy;
  onFlush: (events: TEvent[]) => void;
  dedupeKeyResolver?: (event: TEvent) => string;
  flushImmediately?: (event: TEvent) => boolean;
}

/** Adaptador de buffer reutilizável para streams de eventos em tempo real. */
export class BufferedRealtimeAdapter<TEvent> {
  private readonly buffer: TEvent[] = [];
  private timer: ReturnType<typeof setTimeout> | null = null;

  constructor(private readonly options: BufferedRealtimeAdapterOptions<TEvent>) {}

  /** Adiciona um novo evento ao pipeline da estratégia de buffer. */
  push(event: TEvent): void {
    if (this.options.flushImmediately?.(event) === true) {
      this.flushBuffer();
      this.options.onFlush([event]);
      return;
    }

    this.buffer.push(event);
    this.scheduleFlush();
  }

  /** Descarta timers e o estado de buffer pendente. */
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
