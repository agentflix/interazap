import {
  type ElementRef,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { DatePipe, JsonPipe } from '@angular/common';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfPageTitleComponent,
  AfScrollAreaComponent,
  AfSelectInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import {
  type AutopilotRun,
  type AiRunCompletedEvent,
  type AiRunEvent,
  type AiRunToolCallEvent,
  type AiRunToolResultEvent,
  type AiStreamingEvent,
} from '@ai/models/ai.model';
import { AiAgentService } from '@ai/services/ai-agent.service';
import { AiRealtimeService } from '@ai/services/ai-realtime.service';
import { AutopilotRunService } from '@ai/services/autopilot-run.service';
import { StreamingMessageBubbleComponent } from '../components/streaming-message-bubble/streaming-message-bubble';

type SimulatorTimelineType =
  | 'ai.run.started'
  | 'ai.run.thinking'
  | 'ai.run.tool_call'
  | 'ai.run.tool_result'
  | 'ai.run.completed'
  | 'ai.run.failed'
  | 'ai.run.blocked'
  | 'ai.run.streaming';

type SimulatorJsonPrimitive = string | number | boolean | null;
type SimulatorJsonValue =
  | SimulatorJsonPrimitive
  | { [key: string]: SimulatorJsonValue }
  | SimulatorJsonValue[];
type SimulatorPayloadMap = Record<string, SimulatorJsonValue | undefined>;

interface SimulatorTimelineEvent {
  id: string;
  type: SimulatorTimelineType;
  runId: string;
  payload: SimulatorPayloadMap;
  createdAt: string;
}

/** Chat message for the simulator chat panel. */
interface SimulatorChatMessage {
  id: string;
  role: 'user' | 'assistant';
  text: string;
  isStreaming: boolean;
  isFinal: boolean;
  audioUrl?: string | null;
  createdAt: string;
}

/**
 * AI Autopilot simulator with realtime timeline.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
 */
@Component({
  selector: 'app-ai-simulator',
  standalone: true,
  imports: [
    DatePipe,
    JsonPipe,
    ReactiveFormsModule,
    LucideAngularModule,
    AfPageTitleComponent,
    AfButtonComponent,
    AfScrollAreaComponent,
    AfSelectInputComponent,
    AfLoadingButtonComponent,
    StreamingMessageBubbleComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './simulator.html',
})
export class AiSimulatorComponent {
  private readonly fb = inject(FormBuilder);
  private readonly destroyRef = inject(DestroyRef);
  private readonly route = inject(ActivatedRoute);
  private readonly agentService = inject(AiAgentService);
  private readonly runService = inject(AutopilotRunService);
  private readonly toast = inject(ToastService);
  readonly realtimeService = inject(AiRealtimeService);

  readonly timelineContainer = viewChild<ElementRef<HTMLElement>>('timelineContainer');
  readonly chatContainer = viewChild<ElementRef<HTMLElement>>('chatContainer');
  readonly agentOptions = signal<AfSelectOption[]>([]);
  readonly timeline = signal<SimulatorTimelineEvent[]>([]);
  readonly chatMessages = signal<SimulatorChatMessage[]>([]);
  readonly debugView = signal<'timeline' | 'history'>('timeline');
  readonly historyRuns = signal<AutopilotRun[]>([]);
  readonly historyPage = signal(1);
  readonly historyLastPage = signal(1);
  readonly historyLoading = signal(false);
  readonly selectedHistoryRun = signal<AutopilotRun | null>(null);
  readonly activeRunId = signal<string | null>(null);
  readonly isExecuting = signal(false);
  readonly isCancelling = signal(false);
  readonly isLoadingData = signal(true);
  readonly loadError = signal<string | null>(null);
  readonly hasConnectedOnce = signal(false);
  readonly streamingText = signal('');
  readonly streamReconnectNeeded = signal(false);
  readonly slowNetworkWarning = signal(false);

  private pollingTimer: ReturnType<typeof setInterval> | null = null;

  readonly form = this.fb.group({
    agent_id: this.fb.nonNullable.control('', [Validators.required]),
    message: this.fb.nonNullable.control(''),
  });

  constructor() {
    this.loadDependencies();
    this.loadHistory();
    this.realtimeService.connect();

    // Pre-select agent from query params (from workspace "Testar" button)
    const agentIdParam = this.route.snapshot.queryParamMap.get('agent_id');
    if (agentIdParam) {
      this.form.controls.agent_id.setValue(agentIdParam);
    }

    effect(
      () => {
        const event = this.realtimeService.lastEvent();
        if (!event.type || event.version === 0) {
          return;
        }

        const runId = this.activeRunId();
        if (!runId) {
          return;
        }

        // Handle streaming chunks → update chat message progressively
        if (event.type === 'ai.run.streaming' && event.streaming) {
          const chunk = event.streaming;
          if (chunk.run_id === runId) {
            this.streamingText.update((t) => t + chunk.chunk);
            this.updateStreamingMessage(this.streamingText(), chunk.is_final);

            // Also add to timeline
            this.timeline.update((items) => [
              ...items,
              {
                id: `streaming-${event.version}`,
                type: 'ai.run.streaming' as SimulatorTimelineType,
                runId: chunk.run_id,
                payload: {
                  chunk: chunk.chunk,
                  chunk_index: chunk.chunk_index,
                  is_final: chunk.is_final,
                },
                createdAt: new Date().toISOString(),
              },
            ]);
            this.scrollTimelineToBottom();
            this.scrollChatToBottom();
            return;
          }
        }

        // Handle completed → finalize chat message with full response
        if (event.type === 'ai.run.completed' && event.completed) {
          const completed = event.completed;
          if (completed.run_id === runId) {
            const response = completed.output?.response ?? this.streamingText() ?? '';
            const audioUrl = this.extractAudioUrl(completed.output);
            this.finalizeAssistantMessage(response, audioUrl);
          }
        }

        const timelineEvent = this.toTimelineEvent(event.type, runId, event);
        if (!timelineEvent || timelineEvent.runId !== runId) {
          return;
        }

        this.timeline.update((items) => [...items, timelineEvent]);
        this.syncRunningState(timelineEvent.type);
        this.scrollTimelineToBottom();
      },
      { allowSignalWrites: true },
    );

    effect(
      () => {
        const connected = this.realtimeService.connected();
        const runId = this.activeRunId();
        const executing = this.isExecuting();

        if (connected) {
          this.hasConnectedOnce.set(true);
          this.streamReconnectNeeded.set(false);
          this.stopPolling();
          return;
        }

        if (this.hasConnectedOnce() && executing && runId) {
          this.streamReconnectNeeded.set(true);
          this.startPolling(runId);
        } else {
          this.streamReconnectNeeded.set(false);
        }
      },
      { allowSignalWrites: true },
    );

    this.destroyRef.onDestroy(() => {
      this.stopPolling();
    });
  }

  /**
   * Send a chat message and execute the agent.
   */
  sendChatMessage(): void {
    const text = this.form.controls.message.value?.trim();
    if (!text || this.isExecuting()) return;

    const agentId = this.form.controls.agent_id.value;
    if (!agentId) {
      this.toast.error('Selecione um agente.');
      return;
    }

    // Add user message to chat
    this.chatMessages.update((msgs) => [
      ...msgs,
      {
        id: `user-${Date.now()}`,
        role: 'user',
        text,
        isStreaming: false,
        isFinal: true,
        createdAt: new Date().toISOString(),
      },
    ]);
    this.form.controls.message.setValue('');
    this.scrollChatToBottom();

    // Add placeholder assistant message
    const assistantMsgId = `assistant-${Date.now()}`;
    this.chatMessages.update((msgs) => [
      ...msgs,
      {
        id: assistantMsgId,
        role: 'assistant',
        text: '',
        isStreaming: true,
        isFinal: false,
        createdAt: new Date().toISOString(),
      },
    ]);
    this.streamingText.set('');

    this.executeRun(agentId, text);
  }

  /**
   * Execute a run with selected agent.
   */
  private executeRun(agentId: string, userMessage: string): void {
    this.timeline.set([]);
    this.isExecuting.set(true);
    this.debugView.set('timeline');

    this.runService
      .create({
        agent_id: agentId,
        message: userMessage,
        agent_role: 'general',
        playbook: {
          name: 'simulator',
          objective: userMessage,
        },
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (run: AutopilotRun) => {
          this.activeRunId.set(run.id);
          this.timeline.update((items) => [
            ...items,
            {
              id: `queued-${run.id}`,
              type: 'ai.run.started',
              runId: run.id,
              payload: { status: run.status, message: 'Execução enfileirada...' },
              createdAt: new Date().toISOString(),
            },
          ]);

          if (run.status === 'completed' || run.status === 'blocked' || run.status === 'failed') {
            this.handleTerminalRun(run);
            return;
          }

          // Join the run room for real-time updates
          const runRoom = `run:${run.id}`;
          this.realtimeService.realtime.joinRooms([runRoom]);

          // Start slow network monitor
          this.realtimeService.realtime.startSlowNetworkMonitor(() => {
            this.slowNetworkWarning.set(true);
          });

          this.startPolling(run.id);
          this.scrollTimelineToBottom();
        },
        error: () => {
          this.isExecuting.set(false);
          this.finalizeAssistantMessage('Erro ao iniciar execução.');
          this.toast.error('Falha ao iniciar execução');
        },
      });
  }

  private handleTerminalRun(run: AutopilotRun): void {
    const output = run.output as AiRunCompletedEvent['output'] | undefined;
    const response = output?.response ?? this.streamingText() ?? 'Execução finalizada.';
    const audioUrl = this.extractAudioUrl(output);
    this.finalizeAssistantMessage(response, audioUrl);
    this.isExecuting.set(false);
    this.stopPolling();
    this.clearRunRoom(run.id);
    this.slowNetworkWarning.set(false);

    const type: SimulatorTimelineType =
      run.status === 'completed'
        ? 'ai.run.completed'
        : run.status === 'blocked'
          ? 'ai.run.blocked'
          : 'ai.run.failed';

    this.timeline.update((items) => [
      ...items,
      {
        id: `terminal-${run.id}-${Date.now()}`,
        type,
        runId: run.id,
        payload: output ? { ...output } : { status: run.status },
        createdAt: new Date().toISOString(),
      },
    ]);
    this.scrollTimelineToBottom();
    this.scrollChatToBottom();
  }

  cancel(): void {
    const runId = this.activeRunId();
    if (!runId || this.isCancelling()) {
      return;
    }

    this.isCancelling.set(true);
    this.runService
      .cancel(runId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isCancelling.set(false);
          this.isExecuting.set(false);
          this.stopPolling();
          this.clearRunRoom(runId);
          this.slowNetworkWarning.set(false);
          this.timeline.update((items) => [
            ...items,
            {
              id: `cancel-${runId}-${Date.now()}`,
              type: 'ai.run.failed',
              runId,
              payload: { message: 'Execução cancelada.' },
              createdAt: new Date().toISOString(),
            },
          ]);
          this.scrollTimelineToBottom();
        },
        error: () => {
          this.isCancelling.set(false);
          this.toast.error('Falha ao cancelar execução');
        },
      });
  }

  private clearRunRoom(runId: string): void {
    const runRoom = `run:${runId}`;
    this.realtimeService.realtime.leaveRooms([runRoom]);
    this.realtimeService.realtime.stopSlowNetworkMonitor();
  }

  selectTab(tab: 'timeline' | 'history'): void {
    this.debugView.set(tab);
    if (tab === 'history') {
      this.loadHistory();
    }
  }

  /**
   * Switch debug panel to history view.
   */
  switchToHistory(): void {
    this.selectTab('history');
  }

  goToHistoryPage(page: number): void {
    if (page < 1 || page > this.historyLastPage()) {
      return;
    }
    this.historyPage.set(page);
    this.loadHistory();
  }

  viewHistoryRun(runId: string): void {
    this.runService
      .get(runId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (run) => {
          this.selectedHistoryRun.set(run);
        },
        error: () => {
          this.toast.error('Falha ao carregar histórico da execução');
        },
      });
  }

  trackTimeline = (_index: number, event: SimulatorTimelineEvent): string => event.id;

  labelFor(type: SimulatorTimelineType): string {
    switch (type) {
      case 'ai.run.started':
        return '🤖 Execução iniciada...';
      case 'ai.run.thinking':
        return '💭 Processando...';
      case 'ai.run.tool_call':
        return '🔧 Chamada de ferramenta';
      case 'ai.run.tool_result':
        return '✅ Resultado da ferramenta';
      case 'ai.run.completed':
        return '💬 Resposta final';
      case 'ai.run.blocked':
        return '🛑 Bloqueado por guardrail';
      case 'ai.run.failed':
        return '❌ Execução com falha';
      case 'ai.run.streaming':
        return '📡 Streaming chunk';
    }
  }

  /**
   * Update the last assistant message with streaming text.
   */
  private updateStreamingMessage(text: string, isFinal: boolean): void {
    this.chatMessages.update((msgs) => {
      const lastIdx = this.findLastAssistantMessageIndex(msgs);
      if (lastIdx === -1) return msgs;
      const updated = [...msgs];
      updated[lastIdx] = {
        ...updated[lastIdx],
        text,
        isStreaming: !isFinal,
        isFinal,
      };
      return updated;
    });
  }

  /**
   * Finalize the last assistant message (stream ended or response received).
   */
  private finalizeAssistantMessage(text: string, audioUrl: string | null = null): void {
    this.chatMessages.update((msgs) => {
      const lastIdx = this.findLastAssistantMessageIndex(msgs);
      if (lastIdx === -1) return msgs;
      const updated = [...msgs];
      updated[lastIdx] = {
        ...updated[lastIdx],
        text: text || updated[lastIdx].text,
        isStreaming: false,
        isFinal: true,
        audioUrl,
      };
      return updated;
    });
  }

  private extractAudioUrl(output: AiRunCompletedEvent['output'] | undefined): string | null {
    if (output === undefined) {
      return null;
    }

    const outputRecord = output as SimulatorPayloadMap;
    const directKeys = ['audio_url', 'audioUrl', 'voice_url'] as const;

    for (const key of directKeys) {
      const candidate = outputRecord[key];
      if (typeof candidate === 'string' && candidate.trim() !== '') {
        return candidate;
      }
    }

    const raw = outputRecord['raw'];
    if (typeof raw === 'object' && raw !== null) {
      const rawRecord = raw as SimulatorPayloadMap;

      for (const key of directKeys) {
        const candidate = rawRecord[key];
        if (typeof candidate === 'string' && candidate.trim() !== '') {
          return candidate;
        }
      }

      const audio = rawRecord['audio'];
      if (typeof audio === 'object' && audio !== null) {
        const nestedUrl = (audio as SimulatorPayloadMap)['url'];
        if (typeof nestedUrl === 'string' && nestedUrl.trim() !== '') {
          return nestedUrl;
        }
      }
    }

    return null;
  }

  private findLastAssistantMessageIndex(messages: readonly SimulatorChatMessage[]): number {
    for (let index = messages.length - 1; index >= 0; index -= 1) {
      if (messages[index]?.role === 'assistant') {
        return index;
      }
    }

    return -1;
  }

  private loadDependencies(): void {
    this.isLoadingData.set(true);
    this.loadError.set(null);

    this.agentService
      .list({ page: 1, per_page: 100 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.agentOptions.set(
            response.data.map((agent) => ({
              value: agent.id,
              label: agent.name,
            })),
          );
          this.isLoadingData.set(false);
        },
        error: () => {
          this.loadError.set('Falha ao carregar agentes');
          this.isLoadingData.set(false);
        },
      });
  }

  private loadHistory(): void {
    this.historyLoading.set(true);

    this.runService
      .list({ page: this.historyPage(), per_page: 10 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.historyRuns.set(response.data);
          this.historyLastPage.set(response.meta.last_page);
          this.historyLoading.set(false);
        },
        error: () => {
          this.historyLoading.set(false);
          this.toast.error('Falha ao carregar histórico');
        },
      });
  }

  private startPolling(runId: string): void {
    if (this.pollingTimer) {
      return;
    }

    this.pollingTimer = setInterval(() => {
      this.runService
        .get(runId)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: (run) => {
            const status = run.status;
            if (status !== 'queued' && status !== 'running') {
              this.handleTerminalRun(run);
            }
          },
          error: () => {
            this.stopPolling();
            this.isExecuting.set(false);
          },
        });
    }, 3000);
  }

  private stopPolling(): void {
    if (!this.pollingTimer) {
      return;
    }
    clearInterval(this.pollingTimer);
    this.pollingTimer = null;
  }

  private toTimelineEvent(
    eventType: string,
    activeRunId: string,
    events: {
      started: AiRunEvent | null;
      thinking: AiRunEvent | null;
      toolCall: AiRunToolCallEvent | null;
      toolResult: AiRunToolResultEvent | null;
      completed: AiRunCompletedEvent | null;
      failed: AiRunEvent | null;
      blocked: AiRunEvent | null;
      streaming: AiStreamingEvent | null;
      type: string | null;
      version: number;
    },
  ): SimulatorTimelineEvent | null {
    // Streaming events are handled separately in the constructor effect
    if (eventType === 'ai.run.streaming') {
      return null;
    }

    const payload =
      eventType === 'ai.run.started'
        ? events.started
        : eventType === 'ai.run.thinking'
          ? events.thinking
          : eventType === 'ai.run.tool_call'
            ? events.toolCall
            : eventType === 'ai.run.tool_result'
              ? events.toolResult
              : eventType === 'ai.run.completed'
                ? events.completed
                : eventType === 'ai.run.failed'
                  ? events.failed
                  : events.blocked;

    if (!payload || payload.run_id !== activeRunId) {
      return null;
    }

    return {
      id: `${eventType}-${events.version}`,
      type: eventType as SimulatorTimelineType,
      runId: payload.run_id,
      payload: { ...payload },
      createdAt: new Date().toISOString(),
    };
  }

  private syncRunningState(eventType: SimulatorTimelineType): void {
    if (
      eventType === 'ai.run.completed' ||
      eventType === 'ai.run.failed' ||
      eventType === 'ai.run.blocked'
    ) {
      this.isExecuting.set(false);
      this.stopPolling();
    }
  }

  private scrollTimelineToBottom(): void {
    queueMicrotask(() => {
      const content = this.timelineContainer()?.nativeElement;
      if (!content) {
        return;
      }

      const scrollElement = content.parentElement;
      if (!scrollElement) {
        return;
      }
      scrollElement.scrollTop = scrollElement.scrollHeight;
    });
  }

  private scrollChatToBottom(): void {
    queueMicrotask(() => {
      const content = this.chatContainer()?.nativeElement;
      if (!content) {
        return;
      }

      const scrollElement = content.parentElement;
      if (!scrollElement) {
        return;
      }
      scrollElement.scrollTop = scrollElement.scrollHeight;
    });
  }
}
