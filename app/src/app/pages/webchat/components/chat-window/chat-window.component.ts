import {
  type AfterViewInit,
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  ElementRef,
  ViewChild,
  computed,
  effect,
  inject,
  output,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { fromEvent } from 'rxjs';
import { debounceTime, finalize, switchMap } from 'rxjs/operators';
import {
  AfButtonComponent,
  AfChatBubbleComponent,
  AfChatComposerComponent,
  AfConfirmModalComponent,
} from '@shared/components';
import { LucideAngularModule } from 'lucide-angular';
import { WebChatService } from '../../services/webchat.service';
import { type WebChatMessage, type WebChatMessageType } from '../../webchat.model';

/**
 * ChatWindowComponent — displays the chat messages list, typing indicator,
 * and message composer for the active webchat session.
 */
@Component({
  selector: 'app-chat-window',
  standalone: true,
  imports: [
    AfChatBubbleComponent,
    AfChatComposerComponent,
    AfButtonComponent,
    AfConfirmModalComponent,
    LucideAngularModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './chat-window.component.html',
  styleUrl: './chat-window.component.scss',
})
export class ChatWindowComponent implements OnInit, AfterViewInit {
  private readonly webchatService = inject(WebChatService);
  private readonly destroyRef = inject(DestroyRef);

  /** Emitido quando o cliente encerra o chamado com sucesso. */
  readonly ticketClosed = output<void>();

  @ViewChild('scrollContainer', { read: ElementRef })
  private readonly scrollContainer?: ElementRef<HTMLElement>;

  @ViewChild('fileInput')
  private readonly fileInputRef!: ElementRef<HTMLInputElement>;

  private scrollElement: HTMLElement | null = null;
  private pendingScrollToBottom = true;
  private prevMessageCount = 0;

  protected readonly showScrollToBottom = signal(false);
  protected readonly unreadCount = signal(0);

  // ─── UI Signals ───────────────────────────────────────────────────────────
  readonly messages = this.webchatService.messages;
  readonly isConnected = this.webchatService.isConnected;
  readonly isAiTyping = this.webchatService.isAiTyping;
  readonly error = this.webchatService.error;
  readonly connectionState = this.webchatService.connectionState;
  readonly ticketStatus = this.webchatService.ticketStatus;
  readonly isClosed = this.webchatService.isClosed;
  readonly isClosing = this.webchatService.isClosing;
  readonly closeError = this.webchatService.closeError;
  readonly isCloseModalOpen = signal(false);

  // Upload state
  readonly isUploading = signal(false);
  readonly uploadError = signal<string | null>(null);
  private readonly pendingAttachmentType = signal<WebChatMessageType | null>(null);

  /** Accept attribute for the hidden file input, derived from selected attachment type */
  protected readonly fileAccept = computed(() => {
    switch (this.pendingAttachmentType()) {
      case 'image':
        return 'image/*';
      case 'video':
        return 'video/*';
      case 'audio':
        return 'audio/*';
      case 'document':
        return '.pdf,.doc,.docx,.xls,.xlsx';
      default:
        return '*/*';
    }
  });

  // Visitor info from session
  readonly visitorName = signal('Visitante');
  readonly visitorPhone = signal<string | undefined>(undefined);
  readonly protocol = signal<string | undefined>(undefined);

  // Session info
  readonly sessionId = signal<string | null>(null);

  constructor() {
    effect(() => {
      const count = this.messages().length;
      if (count === 0) return;
      queueMicrotask(() => {
        const delta = count - this.prevMessageCount;
        this.prevMessageCount = count;
        if (delta > 0 && !this.isNearBottom()) {
          this.unreadCount.update(n => n + delta);
          this.showScrollToBottom.set(true);
        } else {
          this.scrollToBottomIfNear();
        }
      });
    });
  }

  ngOnInit(): void {
    // Listen for AI responses to maintain scroll position
    this.webchatService.aiResponse$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.pendingScrollToBottom = true;
      this.scrollToBottomIfNear();
    });

    // Listen for sent confirmations
    this.webchatService.messageSent$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(({ messageId }) => {
        this.webchatService.updateMessageStatus(messageId, 'sent');
      });

    // Quando atendente encerra o chamado remotamente: atualiza UI e
    // retorna automaticamente para a tela inicial (PreChat) após breve delay,
    // permitindo que o cliente visualize a mensagem de encerramento antes do reload.
    this.webchatService.ticketClosedByAgent$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pendingScrollToBottom = true;
        this.scrollToBottomIfNear();
        // Auto-redirect para PreChat após 2.5s (UX: dá tempo para ler "Atendimento encerrado")
        setTimeout(() => this.ticketClosed.emit(), 2500);
      });
  }

  ngAfterViewInit(): void {
    queueMicrotask(() => {
      this.scrollElement = this.resolveScrollElement();
      if (this.scrollElement) {
        this.attachScrollListener();
        // Scroll inicial: garante que o chat abre já no fundo (sem animação)
        if (this.messages().length > 0) {
          this.scrollToBottom('auto');
        }
      }
    });
  }

  // ─── Public API ───────────────────────────────────────────────────────────

  /** Initializes the component with session data */
  init(sessionId: string, visitorName: string, visitorPhone?: string, protocol?: string): void {
    this.sessionId.set(sessionId);
    this.visitorName.set(visitorName);
    this.visitorPhone.set(visitorPhone);
    this.protocol.set(protocol);
    this.pendingScrollToBottom = true;
    this.prevMessageCount = 0;
    this.showScrollToBottom.set(false);
    this.unreadCount.set(0);
  }

  /** Handles message send from the composer */
  onMessageSent(content: string): void {
    const sessionId = this.sessionId();
    if (!sessionId || !content.trim() || this.isClosed()) return;

    const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2)}`;

    this.webchatService.sendMessage(sessionId, content.trim(), tempId).subscribe({
      error: () => {
        // Optimistic message will be marked as failed
        this.webchatService.updateMessageStatus(tempId, 'failed');
      },
    });
  }

  /** Triggered by af-chat-composer (attachmentTypeSelected) — opens file picker for the given type */
  onAttachmentSelected(type: WebChatMessageType): void {
    if (this.isClosed() || this.isUploading()) return;
    this.pendingAttachmentType.set(type);
    this.fileInputRef.nativeElement.click();
  }

  /** Triggered by the hidden file input (change) — uploads file then sends message */
  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB
    if (file.size > MAX_SIZE_BYTES) {
      this.uploadError.set('O arquivo não pode ser maior que 5 MB.');
      input.value = '';
      return;
    }

    const messageType: WebChatMessageType = this.pendingAttachmentType() ?? 'document';
    const sessionId = this.sessionId();
    if (!sessionId) return;

    this.isUploading.set(true);
    this.uploadError.set(null);

    this.webchatService
      .uploadMedia(file)
      .pipe(
        switchMap((upload) =>
          this.webchatService.sendFileMessage(
            sessionId,
            upload.url,
            upload.mime_type,
            messageType,
            upload.file_name,
          ),
        ),
        takeUntilDestroyed(this.destroyRef),
        finalize(() => {
          this.isUploading.set(false);
          input.value = '';
        }),
      )
      .subscribe({
        error: (err: Error) => this.uploadError.set(err?.message ?? 'Falha no envio'),
      });
  }

  openCloseModal(): void {
    if (this.isClosed() || this.isClosing()) {
      return;
    }
    this.isCloseModalOpen.set(true);
  }

  closeCloseModal(): void {
    if (this.isClosing()) {
      return;
    }
    this.isCloseModalOpen.set(false);
  }

  confirmClose(): void {
    if (this.isClosed() || this.isClosing()) {
      return;
    }

    this.webchatService
      .closeTicket()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isCloseModalOpen.set(false);
          this.ticketClosed.emit();
        },
      });
  }

  /** Formats a timestamp for display */
  formatTime(isoString: string): string {
    try {
      const date = new Date(isoString);
      if (isNaN(date.getTime())) {
        return '';
      }
      return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } catch {
      return '';
    }
  }

  /** Determines the bubble direction CSS class */
  getBubbleDirection(direction: 'incoming' | 'outgoing'): 'in' | 'out' {
    return direction === 'outgoing' ? 'out' : 'in';
  }

  /** Maps internal message status to bubble component status */
  getStatusForBubble(status: WebChatMessage['status']): 'sent' | 'delivered' | 'read' {
    if (status === 'sent' || status === 'delivered' || status === 'read') {
      return status;
    }
    return 'sent';
  }

  /** Connection status dot CSS class */
  readonly connectionStatusDot = computed(() => {
    switch (this.connectionState()) {
      case 'connected':
        return 'bg-green-400 dark:bg-green-500';
      case 'connecting':
        return 'bg-yellow-400 dark:bg-yellow-500 animate-pulse';
      case 'error':
        return 'bg-danger-400 dark:bg-danger-500';
      default:
        return 'bg-neutral-400 dark:bg-neutral-500';
    }
  });

  /** Connection status label text */
  readonly connectionLabel = computed(() => {
    switch (this.connectionState()) {
      case 'connected':
        return 'Online';
      case 'connecting':
        return 'Conectando';
      case 'error':
        return 'Erro';
      default:
        return 'Offline';
    }
  });

  /** Track by message ID for @for */
  trackByMessageId(_index: number, message: WebChatMessage): string {
    return message.id;
  }

  protected scrollToBottomClick(): void {
    this.scrollToBottom('smooth');
    this.showScrollToBottom.set(false);
    this.unreadCount.set(0);
  }

  // ─── Private ───────────────────────────────────────────────────────────────

  private isNearBottom(): boolean {
    if (!this.scrollElement) return true;
    const { scrollTop, clientHeight, scrollHeight } = this.scrollElement;
    return scrollTop + clientHeight >= scrollHeight - 100;
  }

  private scrollToBottomIfNear(): void {
    if (this.isNearBottom()) {
      this.scrollToBottom();
    }
  }

  private scrollToBottom(behavior: ScrollBehavior = 'smooth'): void {
    if (!this.scrollElement) return;
    setTimeout(() => {
      if (this.scrollElement) {
        this.scrollElement.scrollTo({
          top: this.scrollElement.scrollHeight,
          behavior,
        });
      }
    }, 50);
  }

  private resolveScrollElement(): HTMLElement | null {
    return this.scrollContainer?.nativeElement ?? null;
  }

  private attachScrollListener(): void {
    if (!this.scrollElement) return;

    fromEvent(this.scrollElement, 'scroll')
      .pipe(debounceTime(100), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pendingScrollToBottom = false;
        if (this.isNearBottom()) {
          this.showScrollToBottom.set(false);
          this.unreadCount.set(0);
        }
      });
  }
}
