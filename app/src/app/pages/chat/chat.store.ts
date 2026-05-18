import { computed, inject, Injectable, signal } from '@angular/core';
import { finalize } from 'rxjs';
import { type Called, CalledService } from 'src/app/core/services/called.service';
import {
  type CalledMessage,
  CalledMessageService,
} from 'src/app/core/services/called-message.service';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { InstanceService } from 'src/app/core/services/instance.service';
import { toast } from 'ngx-sonner';
import { type Contact } from 'src/app/core/models/contact.model';
import { type WindowStatus } from 'src/app/core/models/window-status.model';
import type { ComposerMode } from '@chat/models/chat-state.model';
export type { ChatState, ComposerMode } from '@chat/models/chat-state.model';


/**
 * Modo do composer de mensagens.
 * - `free`: texto livre (canal não-Meta ou dentro da janela 24h)
 * - `mixed`: Meta dentro da janela — permite texto + template
 * - `template-only`: Meta fora da janela — só template aprovado
 */

/**
 * Interface representing the chat state for managing selected called/ticket information.
 */

/**
 * Global chat store for managing selected ticket state and actions.
 *
 * @remarks
 * Manages the currently selected called/ticket, loading states, and message sending state.
 * Provides computed selectors for common derived state like contact and sentiment.
 *
 * @example
 * ```typescript
 * const chatStore = inject(ChatStore);
 * chatStore.selectCalled(ticketId);
 * ```
 */
@Injectable({ providedIn: 'root' })
export class ChatStore {
  private readonly calledService = inject(CalledService);
  private readonly messageService = inject(CalledMessageService);
  private readonly chatRefresh = inject(ChatRefreshService);
  private readonly instanceService = inject(InstanceService);

  // State Signals
  readonly selectedCalledId = signal<string | null>(null);
  readonly selectedCalled = signal<Called | null>(null);
  readonly isLoadingCalled = signal(false);
  readonly isSending = signal(false);
  readonly replyingTo = signal<CalledMessage | null>(null);
  readonly isMediaBatchSending = signal(false);

  /** Mapa de instâncias carregadas (instance_id → provider). */
  readonly instanceProviders = signal<Record<string, string>>({});

  /** Status da janela 24h para o contato do ticket selecionado. */
  readonly windowStatus = signal<WindowStatus | null>(null);

  // Computed Selectors
  readonly hasSelection = computed(() => Boolean(this.selectedCalledId()));
  readonly canSendMessage = computed(() => this.selectedCalled()?.status === 'in_progress');
  readonly selectedContact = computed(() => this.selectedCalled()?.contact ?? null);
  readonly selectedSentiment = computed(() => this.selectedCalled()?.sentiment ?? null);

  /**
   * Modo do composer baseado no provider da instância + status da janela 24h.
   *
   * - `free`: canal não-Meta ou Meta sem restrição de janela
   * - `mixed`: Meta dentro da janela 24h (pode enviar texto + template)
   * - `template-only`: Meta fora da janela 24h (só template)
   */
  readonly composerMode = computed<ComposerMode>(() => {
    const ticket = this.selectedCalled();
    const instanceId = ticket?.instance_id ? String(ticket.instance_id) : null;
    if (!instanceId) {
      return 'free';
    }
    const provider = this.instanceProviders()[instanceId];
    if (provider !== 'meta') {
      return 'free';
    }
    const ws = this.windowStatus();
    if (ws?.canSendFreeText) {
      return 'mixed';
    }
    return 'template-only';
  });

  constructor() {
    this.loadInstanceProviders();
  }

  /**
   * Carrega a lista de instâncias e constrói o mapa instance_id → provider.
   */
  private loadInstanceProviders(): void {
    this.instanceService
      .list({ is_active: true, per_page: 100 })
      .subscribe({
        next: (res) => {
          const map: Record<string, string> = {};
          for (const inst of res.data ?? []) {
            if (inst.id !== undefined && inst.id !== null) {
              map[String(inst.id)] = inst.provider ?? '';
            }
          }
          this.instanceProviders.set(map);
        },
        error: () => {
          // Silencioso — o fallback é tratar como 'free'
        },
      });
  }

  // Actions

  /**
   * Selects a called/ticket by ID, fetching its data if valid.
   *
   * @param id - The ticket ID to select, or null to deselect
   */
  selectCalled(id: string | null): void {
    this.selectedCalledId.set(id);
    if (id) {
      this.fetchCalled(id);
    } else {
      this.selectedCalled.set(null);
    }
  }

  /**
   * Fetches the full called/ticket data for the given ID and updates selectedCalled.
   *
   * @param id - The ticket ID to fetch
   */
  fetchCalled(id: string): void {
    this.isLoadingCalled.set(true);
    this.calledService
      .get(id)
      .pipe(finalize(() => this.isLoadingCalled.set(false)))
      .subscribe({
        next: (response) => this.selectedCalled.set(response.data),
        error: () => this.selectedCalled.set(null),
      });
  }

  /**
   * Starts attendance for the currently selected ticket.
   * Updates local state optimistically and syncs with server.
   */
  startAttendance(): void {
    const id = this.selectedCalledId();
    if (!id) return;

    const current = this.selectedCalled();
    if (current) {
      this.selectedCalled.set({ ...current, status: 'in_progress' });
    }

    this.calledService.open(id).subscribe({
      next: (response) => {
        this.selectedCalled.set(response.data);
        this.chatRefresh.request();
        toast.success('Atendimento iniciado.');
      },
      error: () => {
        if (current) this.selectedCalled.set(current);
        toast.error('Não foi possível iniciar o atendimento.');
      },
    });
  }

  /**
   * Sets the message to reply to (quoted message).
   *
   * @param message - The message to reply to, or null to clear
   */
  setReplyingTo(message: CalledMessage | null): void {
    this.replyingTo.set(message);
  }

  /**
   * Sets the sending state for the composer.
   *
   * @param isSending - Whether a message is currently being sent
   */
  setSending(isSending: boolean): void {
    this.isSending.set(isSending);
  }

  /**
   * Sets the media batch sending state.
   *
   * @param isSending - Whether media is currently being sent
   */
  setMediaBatchSending(isSending: boolean): void {
    this.isMediaBatchSending.set(isSending);
  }

  /**
   * Updates the contact data in the currently selected called.
   *
   * @param contact - The updated contact data
   */
  updateContact(contact: Contact): void {
    const current = this.selectedCalled();
    if (current) {
      this.selectedCalled.set({ ...current, contact });
    }
  }

  /**
   * Define o status da janela 24h para o ticket atual.
   *
   * @param status - WindowStatus ou null para limpar
   */
  setWindowStatus(status: WindowStatus | null): void {
    this.windowStatus.set(status);
  }

  /**
   * Limpa o status da janela 24h (ex: ao trocar de ticket).
   */
  clearWindowStatus(): void {
    this.windowStatus.set(null);
  }
}
