import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { ActivatedRoute } from '@angular/router';
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
export class WebChatPageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly webchatService = inject(WebChatService);

  // ─── Child component references ────────────────────────────────────────────
  readonly chatWindowRef = viewChild<ChatWindowComponent>('chatWindow');

  // ─── UI State ──────────────────────────────────────────────────────────────
  /** Whether we have an active session */
  readonly hasSession = signal(false);
  /** Visitor name from pre-chat */
  readonly visitorName = signal('Visitante');
  /** Whether we are restoring a session from localStorage */
  readonly isRestoring = signal(true);

  // ─── Computed ───────────────────────────────────────────────────────────────
  readonly showPreChat = computed(() => !this.hasSession() && !this.isRestoring());
  readonly showChat = computed(() => this.hasSession());

  // ─── Tenant ID from route ───────────────────────────────────────────────────
  readonly tenantId = signal<string | null>(null);

  ngOnInit(): void {
    // Extract tenantId from route params
    const tenantIdFromRoute = this.route.snapshot.paramMap.get('tenantId');
    this.tenantId.set(tenantIdFromRoute);
    this.attemptSessionRestore();
  }

  /**
   * Attempts to restore a session from localStorage.
   * If found and valid, connects WebSocket and shows chat window.
   */
  private attemptSessionRestore(): void {
    const restored = this.webchatService.restoreSession();
    if (restored) {
      // Pass sessionId so the service can emit webchat:join on socket connect.
      this.webchatService.connectWebSocket(restored.token, restored.sessionId);
      this.hasSession.set(true);
      // queueMicrotask ensures Angular has processed hasSession change before
      // calling chatWindowRef(), which requires the @if(showChat()) block to be rendered.
      queueMicrotask(() => this.initChatWindow(restored.sessionId));
    }
    this.isRestoring.set(false);
  }

  /**
   * Called when pre-chat form successfully creates a session.
   */
  onSessionReady(data: { token: string; sessionId: string }): void {
    this.visitorName.set(this.resolveVisitorName());
    this.hasSession.set(true);
    // Initialize chat window after view update
    queueMicrotask(() => this.initChatWindow(data.sessionId));
  }

  private initChatWindow(sessionId: string): void {
    const chatWindow = this.chatWindowRef();
    if (chatWindow) {
      chatWindow.init(sessionId, this.visitorName());
    }
  }

  private resolveVisitorName(): string {
    // Could be stored in session or use a default
    return 'Visitante';
  }
}

export default WebChatPageComponent;
