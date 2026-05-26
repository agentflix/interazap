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
import { lastValueFrom } from 'rxjs';
import { DOCUMENT } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { PreChatComponent } from './components/pre-chat/pre-chat.component';
import { ChatWindowComponent } from './components/chat-window/chat-window.component';
import { WebChatService } from './services/webchat.service';
import { AfSpinnerComponent } from '@shared/components';

/**
 * Página pública de chat acessível em /chat/:tenantSlug.
 * Exibe o formulário de pré-chat (sem sessão) ou a janela de chat (sessão ativa).
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

  /** Indica se o modo escuro estava ativo antes da página forçar o modo claro. */
  private wasDark = false;

  // ─── Child component references ────────────────────────────────────────────
  readonly chatWindowRef = viewChild<ChatWindowComponent>('chatWindow');

  // ─── UI State ──────────────────────────────────────────────────────────────
  /** Indica se existe uma sessão ativa. */
  readonly hasSession = signal(false);
  /** Nome do visitante coletado no pré-chat. */
  readonly visitorName = signal('Visitante');
  /** Telefone do visitante coletado no pré-chat. */
  readonly visitorPhone = signal<string | undefined>(undefined);
  /** Protocolo do chamado. */
  readonly protocol = signal<string | undefined>(undefined);

  /** Nome do tenant obtido do backend. */
  readonly tenantName = signal<string | null>(null);
  /** Indica se a validação do tenant falhou. */
  readonly tenantError = signal(false);
  /** Indica carregamento (validando tenant ou restaurando sessão). */
  readonly isCarregando = signal(true);

  // ─── Computed ───────────────────────────────────────────────────────────────
  readonly showPreChat = computed(() => !this.hasSession() && !this.isCarregando());
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

  async ngOnInit(): Promise<void> {
    // Force light mode for the public-facing webchat.
    // The app-level ThemeService may have applied `.dark` to <html> based on
    // the agent's preference. Visitors should always see the light theme.
    this.wasDark = this.document.documentElement.classList.contains('dark');
    this.document.documentElement.classList.remove('dark');

    // Extract tenantId from route params
    const tenantIdFromRoute = this.route.snapshot.paramMap.get('tenantId');
    this.tenantId.set(tenantIdFromRoute);

    this.isCarregando.set(true);

    // Step 1: Validate tenant
    if (!tenantIdFromRoute) {
      this.tenantError.set(true);
      this.isCarregando.set(false);
      return;
    }

    try {
      const tenantInfo = await lastValueFrom(this.webchatService.getTenantInfo(tenantIdFromRoute));
      this.tenantName.set(tenantInfo.name);
    } catch {
      this.tenantError.set(true);
      this.isCarregando.set(false);
      return;
    }

    // Step 2: Attempt to restore local session
    const sessionIdFromUrl = this.route.snapshot.queryParamMap.get('s') ?? undefined;
    const restored = this.webchatService.restoreSession(sessionIdFromUrl);

    if (!restored) {
      this.isCarregando.set(false);
      return;
    }

    // Step 3: Verify session status with backend
    try {
      const sessionDetail = await lastValueFrom(
        this.webchatService.getSession(restored.sessionId, tenantIdFromRoute),
      );

      const closedStatuses = ['closed', 'resolved'];
      if (sessionDetail.ticket?.status && closedStatuses.includes(sessionDetail.ticket.status)) {
        this.webchatService.clearSession();
        this.isCarregando.set(false);
        return;
      }

      // Session is valid — proceed with normal restore flow
      this.webchatService.connectWebSocket(restored.token, restored.sessionId);
      this.visitorName.set(restored.contactName ?? 'Visitante');
      this.visitorPhone.set(restored.contactPhone);
      this.protocol.set(restored.protocol);
      this.hasSession.set(true);
      this.writeSessionToUrl(restored.sessionId);
      this.pendingSessionId.set(restored.sessionId);

      // Fetch message history from backend
      this.webchatService.fetchSessionMessages(restored.sessionId, restored.token).subscribe({
        error: () => {
          /* silently ignored — messages from sessionStorage already loaded */
        },
      });
    } catch {
      // On error verifying session, clear and show pre-chat
      this.webchatService.clearSession();
    }

    this.isCarregando.set(false);
  }

  ngOnDestroy(): void {
    // Restore dark mode when leaving the webchat page (e.g., agent navigating back).
    if (this.wasDark) {
      this.document.documentElement.classList.add('dark');
    }
  }

  /**
   * Chamado quando o formulário de pré-chat cria uma sessão com sucesso.
   * @param data Dados da sessão: token, ID, nome, telefone e protocolo
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
