import {
  type OnInit,
  type OnDestroy,
  ChangeDetectionStrategy,
  Component,
  computed,
  effect,
  inject,
  signal,
  untracked,
  viewChild,
} from '@angular/core';
import { DOCUMENT } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { PreChatComponent } from '../components/pre-chat/pre-chat.component';
import { ChatWindowComponent } from '../components/chat-window/chat-window.component';
import { WebChatService } from '../services/webchat.service';
import { AfSpinnerComponent } from '@shared/components';

/**
 * WebChatEmbedComponent — embeddable chat widget for use in iframes on external sites.
 * Distinct from the full page: this version is self-contained and isolated (no outer layout).
 * Route: /embed/:tenantSlug
 */
@Component({
  selector: 'app-webchat-embed',
  standalone: true,
  imports: [PreChatComponent, ChatWindowComponent, AfSpinnerComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './webchat-embed.component.html',
  styleUrl: './webchat-embed.component.scss',
})
export class WebChatEmbedComponent implements OnInit, OnDestroy {
  private readonly route = inject(ActivatedRoute);
  private readonly webchatService = inject(WebChatService);
  private readonly document = inject(DOCUMENT);

  /** Tracks whether dark mode was active before this embed forced light mode */
  private wasDark = false;

  // ─── Child component references ────────────────────────────────────────────
  readonly chatWindowRef = viewChild<ChatWindowComponent>('chatWindow');

  // ─── UI State ──────────────────────────────────────────────────────────────
  readonly hasSession = signal(false);
  readonly visitorName = signal('Visitante');
  readonly isRestoring = signal(true);

  // ─── Computed ───────────────────────────────────────────────────────────────
  readonly showPreChat = computed(() => !this.hasSession() && !this.isRestoring());
  readonly showChat = computed(() => this.hasSession());

  readonly tenantId = signal<string | null>(null);

  // ─── Pending init (sessionId aguardando chatWindowRef disponível) ────────────
  private readonly pendingSessionId = signal<string | null>(null);

  constructor() {
    // Reage ao chatWindowRef() ficando disponível após hasSession=true renderizar
    // o ChatWindowComponent. Elimina race condition do queueMicrotask.
    effect(() => {
      const ref = this.chatWindowRef();
      const sessionId = this.pendingSessionId();
      if (ref && sessionId) {
        untracked(() => {
          ref.init(sessionId, this.visitorName());
          this.pendingSessionId.set(null);
        });
      }
    });
  }

  ngOnInit(): void {
    // Force light mode for the public-facing embed widget.
    this.wasDark = this.document.documentElement.classList.contains('dark');
    this.document.documentElement.classList.remove('dark');

    this.tenantId.set(this.route.snapshot.paramMap.get('tenantId'));
    this.attemptSessionRestore();
  }

  ngOnDestroy(): void {
    // When embedded, disconnect on component destroy to clean up
    this.webchatService.disconnect();
    // Restore dark mode if it was active before the embed forced light mode.
    if (this.wasDark) {
      this.document.documentElement.classList.add('dark');
    }
  }

  /**
   * Attempts to restore a session from localStorage when embedded.
   */
  private attemptSessionRestore(): void {
    const restored = this.webchatService.restoreSession();
    if (restored) {
      // Passa sessionId para o service emitir webchat:join ao conectar.
      this.webchatService.connectWebSocket(restored.token, restored.sessionId);
      this.hasSession.set(true);
      this.pendingSessionId.set(restored.sessionId);

      // Busca histórico do banco para garantir mensagens completas.
      this.webchatService.fetchSessionMessages(restored.sessionId, restored.token).subscribe({
        error: () => {
          /* silently ignored */
        },
      });
    }
    this.isRestoring.set(false);
  }

  /**
   * Called when pre-chat form successfully creates a session.
   */
  onSessionReady(data: { token: string; sessionId: string }): void {
    this.visitorName.set(this.resolveVisitorName());
    this.hasSession.set(true);
    this.pendingSessionId.set(data.sessionId);
  }

  private initChatWindow(sessionId: string): void {
    const chatWindow = this.chatWindowRef();
    if (chatWindow) {
      chatWindow.init(sessionId, this.visitorName());
    }
  }

  private resolveVisitorName(): string {
    return 'Visitante';
  }
}

export default WebChatEmbedComponent;
