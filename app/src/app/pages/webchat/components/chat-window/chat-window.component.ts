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
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { fromEvent } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
import {
  AfButtonComponent,
  AfChatBubbleComponent,
  AfChatComposerComponent,
  AfConfirmModalComponent,
} from '@shared/components';
import { WebChatService } from '../../services/webchat.service';
import { type WebChatMessage } from '../../webchat.model';

/**
 * ChatWindowComponent — displays the chat messages list, typing indicator,
 * and message composer for the active webchat session.
 */
@Component({
  selector: 'app-chat-window',
  standalone: true,
  imports: [AfChatBubbleComponent, AfChatComposerComponent, AfButtonComponent, AfConfirmModalComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './chat-window.component.html',
  styleUrl: './chat-window.component.scss',
})
export class ChatWindowComponent implements OnInit, AfterViewInit {
  private readonly webchatService = inject(WebChatService);
  private readonly destroyRef = inject(DestroyRef);

  @ViewChild('scrollContainer', { read: ElementRef })
  private readonly scrollContainer?: ElementRef<HTMLElement>;

  private scrollElement: HTMLElement | null = null;
  private pendingScrollToBottom = true;

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

  // Visitor name from session
  readonly visitorName = signal('Visitante');

  // Session info
  readonly sessionId = signal<string | null>(null);

  constructor() {
    // Scroll to bottom whenever messages change
    effect(() => {
      const count = this.messages().length;
      if (count === 0) return;
      // Use queueMicrotask to scroll after DOM has updated
      queueMicrotask(() => this.scrollToBottomIfNear());
    });
  }

  ngOnInit(): void {
    // Listen for AI responses to maintain scroll position
    this.webchatService.aiResponse$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pendingScrollToBottom = true;
        this.scrollToBottomIfNear();
      });

    // Listen for sent confirmations
    this.webchatService.messageSent$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(({ messageId }) => {
        this.webchatService.updateMessageStatus(messageId, 'sent');
      });
  }

  ngAfterViewInit(): void {
    queueMicrotask(() => {
      this.scrollElement = this.resolveScrollElement();
      if (this.scrollElement) {
        this.attachScrollListener();
      }
    });
  }

  // ─── Public API ───────────────────────────────────────────────────────────

  /** Initializes the component with session data */
  init(sessionId: string, visitorName: string): void {
    this.sessionId.set(sessionId);
    this.visitorName.set(visitorName);
    this.pendingScrollToBottom = true;
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
        return 'bg-red-400 dark:bg-red-500';
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

  // ─── Private ───────────────────────────────────────────────────────────────

  private scrollToBottomIfNear(): void {
    if (!this.scrollElement) return;
    const { scrollTop, clientHeight, scrollHeight } = this.scrollElement;
    if (scrollTop + clientHeight >= scrollHeight - 100) {
      this.scrollToBottom();
    }
  }

  private scrollToBottom(): void {
    if (!this.scrollElement) return;
    setTimeout(() => {
      if (this.scrollElement) {
        this.scrollElement.scrollTo({
          top: this.scrollElement.scrollHeight,
          behavior: 'smooth',
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
      });
  }
}
