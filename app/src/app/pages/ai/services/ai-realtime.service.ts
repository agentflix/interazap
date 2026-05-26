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
 * Serviço realtime para eventos WebSocket de execução da IA.
 *
 * Gerencia subscrições WebSocket para eventos de execução (iniciado, pensando,
 * chamadas de ferramentas, streaming, concluído, falha, bloqueado) e os expõe
 * como signals Angular para atualizações reativas da UI.
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

  /** Rastreia event_ids processados para evitar eventos duplicados. */
  private readonly processedEventIds = new Set<string>();

  /** Indica se a conexão WebSocket está ativa. */
  readonly connected = this._realtime.connected;

  /** Expõe o RealtimeService subjacente para gerenciamento de salas. */
  readonly realtime = this._realtime;

  /**
   * Visão agregada do último evento recebido por tipo.
   *
   * @returns Objeto contendo o evento mais recente de cada fase de execução,
   * o nome do tipo de evento e um contador de versão que incrementa a cada evento.
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
   * Estabelece a conexão WebSocket e vincula todos os listeners de eventos.
   */
  connect(): void {
    this._realtime.connect();
    this.bindEvents();
  }

  /**
   * Vincula todos os listeners de eventos de execução da IA ao RealtimeService.
   * Garante que os listeners sejam anexados apenas uma vez por instância do serviço.
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
   * Processa um evento WebSocket recebido e atualiza o signal correspondente.
   * @param eventName Nome do evento (ex.: 'ai.run.completed')
   * @param payload Payload bruto do evento WebSocket
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
   * Normaliza um payload bruto de WebSocket para um AiRunEvent validado.
   * @param payload Payload bruto a normalizar
   * @returns Payload normalizado ou null se a validação falhar
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
   * Type guard para verificar se um valor é um objeto simples (Record).
   * @param value Valor a verificar
   * @returns true se o valor for um objeto não nulo
   */
  private isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
  }

  /**
   * Converte um payload normalizado para AiRunToolCallEvent.
   * @param payload Payload normalizado do evento
   * @returns Evento de chamada de ferramenta ou null se campos obrigatórios estiverem ausentes
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
   * Converte um payload normalizado para AiRunToolResultEvent.
   * @param payload Payload normalizado do evento
   * @returns Evento de resultado de ferramenta ou null se campos obrigatórios estiverem ausentes
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
   * Converte um payload normalizado para AiRunCompletedEvent.
   * @param payload Payload normalizado do evento
   * @returns Evento de conclusão com output analisado
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
   * Converte um payload normalizado para AiStreamingEvent.
   * @param payload Payload normalizado do evento
   * @returns Evento de streaming ou null se o chunk estiver ausente
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
   * Extrai com segurança uma string não vazia de um record.
   * @param value Record de onde extrair
   * @param key Chave a buscar
   * @returns Valor string ou null se não for uma string válida não vazia
   */
  private getString(value: Record<string, unknown>, key: string): string | null {
    const candidate = value[key];
    return typeof candidate === 'string' && candidate.trim() !== '' ? candidate : null;
  }

  /**
   * Extrai com segurança um número finito de um record.
   * @param value Record de onde extrair
   * @param key Chave a buscar
   * @returns Valor numérico ou null se não for um número finito válido
   */
  private getNumber(value: Record<string, unknown>, key: string): number | null {
    const candidate = value[key];
    return typeof candidate === 'number' && Number.isFinite(candidate) ? candidate : null;
  }

  /**
   * Extrai com segurança um booleano de um record.
   * @param value Record de onde extrair
   * @param key Chave a buscar
   * @returns Valor booleano ou null se não for booleano
   */
  private getBoolean(value: Record<string, unknown>, key: string): boolean | null {
    const candidate = value[key];
    return typeof candidate === 'boolean' ? candidate : null;
  }

  /**
   * Extrai com segurança um objeto simples de um record.
   * @param value Record de onde extrair
   * @param key Chave a buscar
   * @returns Valor objeto ou null se não for um objeto válido
   */
  private getRecord(value: Record<string, unknown>, key: string): Record<string, unknown> | null {
    const candidate = value[key];
    return this.isRecord(candidate) ? candidate : null;
  }
}
