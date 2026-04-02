import { computed, DestroyRef, inject, Injectable, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RealtimeService } from '@core/services/realtime.service';
import {
  type AiRunCompletedEvent,
  type AiRunEvent,
  type AiRunToolCallEvent,
  type AiRunToolResultEvent,
  type AiStreamingEvent,
} from '@ai/models/ai.model';

/**
 * Realtime service for AI run websocket events.
 *
 * Manages WebSocket subscriptions to AI run events (started, thinking,
 * tool calls, streaming, completed, failed, blocked) and exposes them
 * as Angular signals for reactive UI updates.
 *
 * @class AiRealtimeService
 */
@Injectable({ providedIn: 'root' })
export class AiRealtimeService {
  private readonly _realtime = inject(RealtimeService);
  private readonly destroyRef = inject(DestroyRef);

  private readonly _runStarted = signal<AiRunEvent | null>(null);
  private readonly _runThinking = signal<AiRunEvent | null>(null);
  private readonly _runToolCall = signal<AiRunToolCallEvent | null>(null);
  private readonly _runToolResult = signal<AiRunToolResultEvent | null>(null);
  private readonly _runCompleted = signal<AiRunCompletedEvent | null>(null);
  private readonly _runFailed = signal<AiRunEvent | null>(null);
  private readonly _runBlocked = signal<AiRunEvent | null>(null);
  private readonly _runStreaming = signal<AiStreamingEvent | null>(null);
  private readonly _lastEventType = signal<string | null>(null);
  private readonly _eventCount = signal(0);
  private listenersBound = false;

  /** Tracks processed event_ids to prevent duplicate events. */
  private readonly processedEventIds = new Set<string>();

  /** Indicates whether the WebSocket connection is active. */
  readonly connected = this._realtime.connected;

  /** Exposes the underlying RealtimeService for room management. */
  readonly realtime = this._realtime;

  /**
   * Aggregated view of the last received event for each type.
   *
   * @returns An object containing the most recent event for each run phase,
   * the event type name, and a version counter that increments on each event.
   */
  readonly lastEvent = computed(() => ({
    started: this._runStarted(),
    thinking: this._runThinking(),
    toolCall: this._runToolCall(),
    toolResult: this._runToolResult(),
    completed: this._runCompleted(),
    failed: this._runFailed(),
    blocked: this._runBlocked(),
    streaming: this._runStreaming(),
    type: this._lastEventType(),
    version: this._eventCount(),
  }));

  /**
   * Establishes the WebSocket connection and binds all event listeners.
   */
  connect(): void {
    this._realtime.connect();
    this.bindEvents();
  }

  /**
   * Binds all AI run event listeners to the RealtimeService.
   * Ensures listeners are only attached once per service instance.
   */
  private bindEvents(): void {
    if (this.listenersBound) {
      return;
    }

    const events = [
      'ai.run.started',
      'ai.run.thinking',
      'ai.run.tool_call',
      'ai.run.tool_result',
      'ai.run.completed',
      'ai.run.failed',
      'ai.run.blocked',
      'ai.run.streaming',
    ];

    for (const eventName of events) {
      this._realtime
        .on(eventName)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe((payload) => this.handleEvent(eventName, payload));
    }

    this.listenersBound = true;
  }

  /**
   * Processes an incoming WebSocket event and updates the appropriate signal.
   *
   * @param eventName - The name of the event (e.g., 'ai.run.completed')
   * @param payload - The raw event payload from the WebSocket
   */
  private handleEvent(eventName: string, payload: unknown): void {
    const eventPayload = this.normalizePayload(payload);
    if (!eventPayload) {
      return;
    }

    // Deduplicate by event_id
    const eventId = this.getString(eventPayload, 'event_id');
    if (eventId && this.processedEventIds.has(eventId)) {
      return; // Already processed this event
    }
    if (eventId) {
      this.processedEventIds.add(eventId);
      // Keep the set bounded to prevent memory leaks
      if (this.processedEventIds.size > 1000) {
        const firstKey = this.processedEventIds.values().next().value;
        if (firstKey) {
          this.processedEventIds.delete(firstKey);
        }
      }
    }

    switch (eventName) {
      case 'ai.run.started':
        this._runStarted.set(eventPayload);
        this._lastEventType.set(eventName);
        break;
      case 'ai.run.thinking':
        this._runThinking.set(eventPayload);
        this._lastEventType.set(eventName);
        break;
      case 'ai.run.tool_call':
        this._runToolCall.set(this.toToolCallEvent(eventPayload));
        this._lastEventType.set(eventName);
        break;
      case 'ai.run.tool_result':
        this._runToolResult.set(this.toToolResultEvent(eventPayload));
        this._lastEventType.set(eventName);
        break;
      case 'ai.run.completed':
        this._runCompleted.set(this.toCompletedEvent(eventPayload));
        this._lastEventType.set(eventName);
        break;
      case 'ai.run.failed':
        this._runFailed.set(eventPayload);
        this._lastEventType.set(eventName);
        break;
      case 'ai.run.blocked':
        this._runBlocked.set(eventPayload);
        this._lastEventType.set(eventName);
        break;
      case 'ai.run.streaming':
        this._runStreaming.set(this.toStreamingEvent(eventPayload));
        this._lastEventType.set(eventName);
        break;
      default:
        return;
    }

    this._eventCount.update((value) => value + 1);
  }

  /**
   * Normalizes a raw WebSocket payload into a validated AiRunEvent.
   *
   * @param payload - Raw payload to normalize
   * @returns Normalized payload or null if validation fails
   */
  private normalizePayload(payload: unknown): (AiRunEvent & Record<string, unknown>) | null {
    if (!this.isRecord(payload)) {
      return null;
    }

    const runId = this.getString(payload, 'run_id');
    const tenantId = this.getString(payload, 'tenant_id');
    const status = this.getString(payload, 'status');

    if (!runId || !tenantId || !status) {
      return null;
    }

    const normalizedPayload: AiRunEvent & Record<string, unknown> = {
      ...payload,
      run_id: runId,
      tenant_id: tenantId,
      status,
    };

    return normalizedPayload;
  }

  /**
   * Type guard to check if a value is a plain object record.
   *
   * @param value - Value to check
   * @returns True if value is a non-null object
   */
  private isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
  }

  /**
   * Converts a normalized payload to AiRunToolCallEvent.
   *
   * @param payload - Normalized event payload
   * @returns Tool call event or null if required fields are missing
   */
  private toToolCallEvent(
    payload: AiRunEvent & Record<string, unknown>,
  ): AiRunToolCallEvent | null {
    const toolName = this.getString(payload, 'tool_name');
    if (!toolName) {
      return null;
    }

    return {
      ...payload,
      tool_name: toolName,
      tool_args: this.getRecord(payload, 'tool_args') ?? {},
      iteration: this.getNumber(payload, 'iteration') ?? 0,
    };
  }

  /**
   * Converts a normalized payload to AiRunToolResultEvent.
   *
   * @param payload - Normalized event payload
   * @returns Tool result event or null if required fields are missing
   */
  private toToolResultEvent(
    payload: AiRunEvent & Record<string, unknown>,
  ): AiRunToolResultEvent | null {
    const toolName = this.getString(payload, 'tool_name');
    if (!toolName) {
      return null;
    }

    return {
      ...payload,
      tool_name: toolName,
      result: this.getRecord(payload, 'result') ?? {},
      success: this.getBoolean(payload, 'success') ?? false,
      iteration: this.getNumber(payload, 'iteration') ?? 0,
    };
  }

  /**
   * Converts a normalized payload to AiRunCompletedEvent.
   *
   * @param payload - Normalized event payload
   * @returns Completed event with parsed output
   */
  private toCompletedEvent(
    payload: AiRunEvent & Record<string, unknown>,
  ): AiRunCompletedEvent | null {
    const output = this.getRecord(payload, 'output') ?? {};
    const normalizedOutput: AiRunCompletedEvent['output'] = {};

    const response = this.getString(output, 'response');
    if (response) {
      normalizedOutput.response = response;
    }

    if (Object.prototype.hasOwnProperty.call(output, 'error')) {
      const error = output['error'];
      if (typeof error === 'string' || error === null) {
        normalizedOutput.error = error as string | null;
      }
    }

    if (Object.prototype.hasOwnProperty.call(output, 'blocked')) {
      const blocked = output['blocked'];
      if (typeof blocked === 'boolean') {
        normalizedOutput.blocked = blocked;
      }
    }

    if (Object.prototype.hasOwnProperty.call(output, 'raw')) {
      const raw = output['raw'];
      if (this.isRecord(raw)) {
        normalizedOutput.raw = raw as AiRunCompletedEvent['output']['raw'];
      }
    }

    return {
      ...payload,
      output: normalizedOutput,
    };
  }

  /**
   * Converts a normalized payload to AiStreamingEvent.
   *
   * @param payload - Normalized event payload
   * @returns Streaming event or null if chunk is missing
   */
  private toStreamingEvent(payload: AiRunEvent & Record<string, unknown>): AiStreamingEvent | null {
    const chunk = this.getString(payload, 'chunk');
    if (chunk === null) {
      return null;
    }

    return {
      run_id: payload.run_id,
      chunk,
      chunk_index: this.getNumber(payload, 'chunk_index') ?? 0,
      is_final: this.getBoolean(payload, 'is_final') ?? false,
      accumulated_text: this.getString(payload, 'accumulated_text') ?? undefined,
    };
  }

  /**
   * Safely extracts a non-empty string from a record.
   *
   * @param value - Record to extract from
   * @param key - Key to look up
   * @returns The string value or null if not a valid non-empty string
   */
  private getString(value: Record<string, unknown>, key: string): string | null {
    const candidate = value[key];
    return typeof candidate === 'string' && candidate.trim() !== '' ? candidate : null;
  }

  /**
   * Safely extracts a finite number from a record.
   *
   * @param value - Record to extract from
   * @param key - Key to look up
   * @returns The number value or null if not a valid finite number
   */
  private getNumber(value: Record<string, unknown>, key: string): number | null {
    const candidate = value[key];
    return typeof candidate === 'number' && Number.isFinite(candidate) ? candidate : null;
  }

  /**
   * Safely extracts a boolean from a record.
   *
   * @param value - Record to extract from
   * @param key - Key to look up
   * @returns The boolean value or null if not a boolean
   */
  private getBoolean(value: Record<string, unknown>, key: string): boolean | null {
    const candidate = value[key];
    return typeof candidate === 'boolean' ? candidate : null;
  }

  /**
   * Safely extracts a plain object from a record.
   *
   * @param value - Record to extract from
   * @param key - Key to look up
   * @returns The object value or null if not a valid object
   */
  private getRecord(value: Record<string, unknown>, key: string): Record<string, unknown> | null {
    const candidate = value[key];
    return this.isRecord(candidate) ? candidate : null;
  }
}
