import {
  type AfterViewInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  ElementRef,
  NgZone,
  type OnDestroy,
  type OnInit,
  ViewChild,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import { fromEvent, startWith } from 'rxjs';
import { type CalledMessage } from 'src/app/core/services/called-message.service';
import { ButtonComponent } from 'src/app/shared/components/buttons';

import { UserChatEmptyStateComponent } from './user-chat-empty-state.component';
import { UserChatThreadStore } from './user-chat-thread.store';
import { UserChatThreadViewComponent } from './user-chat-thread-view.component';
import { UserChatTypingIndicatorComponent } from './user-chat-typing-indicator.component';

/**
 * Thread de mensagens de um atendimento com scroll infinito e integração realtime.
 *
 * @remarks
 * Gerencia o scroll automático (mantém o fundo quando o usuário não rolou),
 * paginação reversa ao chegar ao topo, indicador de digitação e contagem
 * de mensagens não lidas fora de tela.
 *
 * Delega o estado ao `UserChatThreadStore` via `providers`.
 */
@Component({
  selector: 'app-user-chat-thread',
  standalone: true,
  imports: [
    ButtonComponent,
    LucideAngularModule,
    UserChatEmptyStateComponent,
    UserChatThreadViewComponent,
    UserChatTypingIndicatorComponent,
  ],
  templateUrl: './user-chat-thread.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    class: 'block h-full overflow-hidden',
  },
  providers: [UserChatThreadStore],
})
export class UserChatThreadComponent implements OnInit, AfterViewInit, OnDestroy {
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);
  private readonly ngZone = inject(NgZone);

  protected readonly store = inject(UserChatThreadStore);

  @ViewChild('scrollContainer', { read: ElementRef })
  private readonly scrollContainer?: ElementRef<HTMLElement>;

  readonly allowStartConversation = input(true);
  readonly ticketId = input<string | null | undefined>(undefined);
  readonly canLoadMessages = input(true);
  readonly readOnlyMode = input(false);

  readonly reply = output<CalledMessage>();

  readonly calledId = this.store.calledId;
  readonly messages = this.store.messages;
  readonly hasMessages = this.store.hasMessages;
  readonly isLoading = this.store.isLoading;
  readonly isLoadingMore = this.store.isLoadingMore;
  readonly loadError = this.store.loadError;
  readonly contactTyping = this.store.contactTyping;

  private readonly routeTicketId = signal<string | null>(null);
  private scrollElement: HTMLElement | null = null;
  private lastScrollTop = 0;
  private userScrollIntentUntil = 0;
  private pendingRestore: { previousHeight: number; previousScrollTop: number } | null = null;
  private pendingScrollToBottom = true;
  private resizeObserver: ResizeObserver | null = null;
  private wasNearBottom = true;
  private latestScrollBehavior: 'auto' | 'smooth' = 'auto';
  private prevMessageCount = 0;

  protected readonly showScrollToBottom = signal(false);
  protected readonly unreadCount = signal(0);

  constructor() {
    effect(() => {
      const inputTicketId = this.ticketId();
      const resolvedTicketId = inputTicketId === undefined ? this.routeTicketId() : inputTicketId;
      this.store.setContext(resolvedTicketId, this.canLoadMessages());
    });

    effect(() => {
      this.calledId();
      this.pendingScrollToBottom = true;
      this.lastScrollTop = 0;
      this.pendingRestore = null;
      this.showScrollToBottom.set(false);
      this.unreadCount.set(0);
      this.prevMessageCount = 0;
    });

    effect(() => {
      const loadingMore = this.isLoadingMore();
      if (!loadingMore && this.pendingRestore !== null) {
        this.restoreScrollAfterPagination(this.pendingRestore);
        this.pendingRestore = null;
      }
    });

    effect(() => {
      const messageCount = this.messages().length;
      const loading = this.isLoading();
      if (messageCount === 0 || loading || !this.pendingScrollToBottom) {
        return;
      }

      this.scrollToBottom('auto');
      this.pendingScrollToBottom = false;
      this.wasNearBottom = true;
    });

    effect(() => {
      const count = this.messages().length;
      if (count === 0) return;

      const delta = count - this.prevMessageCount;
      this.prevMessageCount = count;

      if (delta > 0 && !this.wasNearBottom && this.scrollElement !== null) {
        this.unreadCount.update(n => n + delta);
        this.showScrollToBottom.set(true);
        return;
      }

      if (this.scrollElement !== null && !this.hasUserScrollIntent() && !this.isNearBottom()) {
        return;
      }

      if (this.scrollElement === null || this.isNearBottom()) {
        this.scrollToBottom('smooth');
      }
    });
  }

  ngOnInit(): void {
    this.store.connectRealtime();

    this.route.paramMap
      .pipe(startWith(this.route.snapshot.paramMap), takeUntilDestroyed(this.destroyRef))
      .subscribe((params) => {
        this.routeTicketId.set(params.get('calledId'));
      });
  }

  ngAfterViewInit(): void {
    queueMicrotask(() => {
      this.scrollElement = this.resolveScrollElement();
      if (this.scrollElement === null) return;

      this.attachScrollListeners();
      this.setupResizeObserver();
    });
  }

  ngOnDestroy(): void {
    if (this.resizeObserver) {
      this.resizeObserver.disconnect();
    }
  }

  private setupResizeObserver(): void {
    if (!this.scrollElement || !this.scrollElement.firstElementChild) return;

    this.resizeObserver = new ResizeObserver(() => {
      // Mantém o scroll no final se o usuário já estava no final ou se é o carregamento inicial.
      // Útil para quando imagens terminam de carregar ou mensagens mudam de tamanho.
      if (this.wasNearBottom || this.pendingScrollToBottom) {
        this.scrollToBottom(this.latestScrollBehavior || 'auto');
      }
    });

    this.resizeObserver.observe(this.scrollElement.firstElementChild);
  }

  private attachScrollListeners(): void {
    if (this.scrollElement === null) return;

    this.ngZone.runOutsideAngular(() => {
      fromEvent(this.scrollElement as HTMLElement, 'wheel')
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe(() => this.markUserScrollIntent());

      fromEvent(this.scrollElement as HTMLElement, 'touchmove')
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe(() => this.markUserScrollIntent());

      fromEvent(this.scrollElement as HTMLElement, 'pointerdown')
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe(() => this.markUserScrollIntent());

      fromEvent(this.scrollElement as HTMLElement, 'scroll')
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe(() => this.handleScroll());
    });

    if (this.pendingScrollToBottom && this.hasMessages()) {
      this.scrollToBottom('auto');
      this.pendingScrollToBottom = false;
    }
  }

  protected retryLoadMessages(): void {
    this.pendingScrollToBottom = true;
    this.store.retryLoadMessages();
  }

  protected openStartConversation(): void {
    this.store.openStartConversation();
  }

  protected scrollToBottomClick(): void {
    this.scrollToBottom('smooth');
    this.showScrollToBottom.set(false);
    this.unreadCount.set(0);
  }

  protected onReply(message: CalledMessage): void {
    this.reply.emit(message);
  }

  appendMessage(message: CalledMessage): void {
    this.store.appendMessage(message);
  }

  replaceMessage(tempId: string, message: CalledMessage): void {
    this.store.replaceMessage(tempId, message);
  }

  private handleScroll(): void {
    if (
      this.scrollElement === null ||
      this.isLoading() ||
      this.isLoadingMore() ||
      this.calledId() === null
    ) {
      return;
    }

    const currentScrollTop = this.scrollElement.scrollTop;
    const isScrollingUp = currentScrollTop < this.lastScrollTop;

    this.wasNearBottom = this.isNearBottom();

    if (this.wasNearBottom) {
      this.showScrollToBottom.set(false);
      this.unreadCount.set(0);
    }

    if (currentScrollTop <= 40 && isScrollingUp && this.hasUserScrollIntent()) {
      const previousHeight = this.scrollElement.scrollHeight;
      const previousScrollTop = this.scrollElement.scrollTop;

      const started = this.ngZone.run(() => this.store.loadMore());
      if (started) {
        this.pendingRestore = { previousHeight, previousScrollTop };
      }
    }

    this.lastScrollTop = currentScrollTop;
  }

  private markUserScrollIntent(): void {
    this.userScrollIntentUntil = Date.now() + 1200;
  }

  private hasUserScrollIntent(): boolean {
    return Date.now() <= this.userScrollIntentUntil;
  }

  private isNearBottom(): boolean {
    if (this.scrollElement === null) return true;
    const { scrollTop, clientHeight, scrollHeight } = this.scrollElement;
    return scrollTop + clientHeight >= scrollHeight - 100;
  }

  private restoreScrollAfterPagination(snapshot: {
    previousHeight: number;
    previousScrollTop: number;
  }): void {
    if (this.scrollElement === null) {
      return;
    }

    requestAnimationFrame(() => {
      if (this.scrollElement === null) {
        return;
      }

      const nextHeight = this.scrollElement.scrollHeight;
      const heightDiff = nextHeight - snapshot.previousHeight;
      this.scrollElement.scrollTop = snapshot.previousScrollTop + heightDiff;
      this.lastScrollTop = this.scrollElement.scrollTop;
    });
  }

  private scrollToBottom(behavior: 'auto' | 'smooth' = 'auto'): void {
    if (this.scrollElement === null) {
      return;
    }

    this.latestScrollBehavior = behavior;

    // Use setTimeout to ensure the DOM (especially new messages/images initial structure) has rendered
    setTimeout(() => {
      if (this.scrollElement !== null) {
        this.wasNearBottom = true;
        this.scrollElement.scrollTo({
          top: this.scrollElement.scrollHeight,
          behavior,
        });
      }
    }, 50);
  }

  private resolveScrollElement(): HTMLElement | null {
    const host = this.scrollContainer?.nativeElement;
    return host ?? null;
  }
}
