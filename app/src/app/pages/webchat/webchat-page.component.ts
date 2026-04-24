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
import { ActivatedRoute, Router } from '@angular/router';
import { PreChatComponent } from './components/pre-chat/pre-chat.component';
import { ChatWindowComponent } from './components/chat-window/chat-window.component';
import { WebChatService } from './services/webchat.service';
import { AfSpinnerComponent } from '@shared/components';

/**
 * WebChatPageComponent — public-facing chat page at /chat/:tenantSlug.
 * Shows either the PreChat form (no session) or the ChatWindow (session active).
 */
@Component({
  selector: 'app-webchat-page',
  standalone: true,
  imports: [PreChatComponent, ChatWindowComponent, AfSpinnerComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './webchat-page.component.html',
})
export class WebChatPageComponent implements OnInit, OnDestroy {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly webchatService = inject(WebChatService);
  private readonly document = inject(DOCUMENT);

  /** Tracks whether dark mode was active before this page forced light mode */
  private wasDark = false;

  // ─── Child component references ────────────────────────────────────────────
  readonly chatWindowRef = viewChild<ChatWindowComponent>('chatWindow');

  // ─── UI State ──────────────────────────────────────────────────────────────
  /** Whether we have an active session */
  readonly hasSession = signal(false);
  /** Visitor name from pre-chat */
  readonly visitorName = signal('Visitante');
  /** Visitor phone */
  readonly visitorPhone = signal<string | undefined>(undefined);
  /** Call protocol */
  readonly protocol = signal<string | undefined>(undefined);

  /** Whether we are restoring a session from localStorage */
  readonly isRestoring = signal(true);

  // ─── Computed ───────────────────────────────────────────────────────────────
  readonly showPreChat = computed(() => !this.hasSession() && !this.isRestoring());
  readonly showChat = computed(() => this.hasSession());

  // ─── Tenant ID from route ───────────────────────────────────────────────────
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
          ref.init(sessionId, this.visitorName(), this.visitorPhone(), this.protocol());
          this.pendingSessionId.set(null);
        });
      }
    });
  }

  ngOnInit(): void {
    // Force light mode for the public-facing webchat.
    // The app-level ThemeService may have applied `.dark` to <html> based on
    // the agent's preference. Visitors should always see the light theme.
    this.wasDark = this.document.documentElement.classList.contains('dark');
    this.document.documentElement.classList.remove('dark');

    // Extract tenantId from route params
    const tenantIdFromRoute = this.route.snapshot.paramMap.get('tenantId');
    this.tenantId.set(tenantIdFromRoute);
    this.attemptSessionRestore();
  }

  ngOnDestroy(): void {
    // Restore dark mode when leaving the webchat page (e.g., agent navigating back).
    if (this.wasDark) {
      this.document.documentElement.classList.add('dark');
    }
  }

  /**
   * Attempts to restore a session from localStorage.
   * If found and valid, connects WebSocket and shows chat window.
   * Also fetches message history from the backend so messages are
   * visible even if localStorage was cleared or the session is opened
   * on a different device/browser.
   */
  private attemptSessionRestore(): void {
    // Prefer sessionId from URL query param (?s=) so a bookmarked URL
    // always opens the correct session even if localStorage was cleared.
    const sessionIdFromUrl = this.route.snapshot.queryParamMap.get('s') ?? undefined;
    const restored = this.webchatService.restoreSession(sessionIdFromUrl);
    if (restored) {
      // Pass sessionId so the service can emit webchat:join on socket connect.
      this.webchatService.connectWebSocket(restored.token, restored.sessionId);
      this.visitorName.set(restored.contactName ?? 'Visitante');
      this.visitorPhone.set(restored.contactPhone);
      this.protocol.set(restored.protocol);
      this.hasSession.set(true);
      // Ensure URL reflects the active session.
      this.writeSessionToUrl(restored.sessionId);
      // pendingSessionId dispara o effect que chama init() quando chatWindowRef estiver disponível.
      this.pendingSessionId.set(restored.sessionId);

      // Busca histórico do banco para garantir que mensagens estejam completas,
      // mesmo que localStorage esteja vazio ou seja de outro dispositivo.
      this.webchatService.fetchSessionMessages(restored.sessionId, restored.token).subscribe({
        error: () => {
          /* silently ignored — mensagens do localStorage já carregadas */
        },
      });
    }
    this.isRestoring.set(false);
  }

  /**
   * Called when pre-chat form successfully creates a session.
   */
  onSessionReady(data: {
    token: string;
    sessionId: string;
    contactName?: string;
    contactPhone?: string;
    protocol?: string;
  }): void {
    this.visitorName.set(data.contactName ?? this.resolveVisitorName());
    this.visitorPhone.set(data.contactPhone);
    this.protocol.set(data.protocol);
    this.hasSession.set(true);
    // Escreve sessionId na URL para que F5 restaure a sessão correta.
    this.writeSessionToUrl(data.sessionId);
    // pendingSessionId dispara o effect que chama init() quando chatWindowRef estiver disponível.
    this.pendingSessionId.set(data.sessionId);
  }

  /**
   * Chamado quando o cliente encerra o chamado no ChatWindow.
   * Limpa a sessão persistida e volta ao formulário de pré-chat.
   */
  onTicketClosed(): void {
    this.webchatService.clearSession();
    this.webchatService.disconnect();
    this.hasSession.set(false);
    // Remove sessionId from URL when ticket is closed.
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { s: null },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

  private writeSessionToUrl(sessionId: string): void {
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { s: sessionId },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

  private resolveVisitorName(): string {
    // Could be stored in session or use a default
    return 'Visitante';
  }
}

export default WebChatPageComponent;
