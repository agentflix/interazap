import {
  type OnInit,
  type OnDestroy,
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  signal,
  viewChild,
} from '@angular/core';
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

  ngOnInit(): void {
    this.tenantId.set(this.route.snapshot.paramMap.get('tenantId'));
    this.attemptSessionRestore();
  }

  ngOnDestroy(): void {
    // When embedded, disconnect on component destroy to clean up
    this.webchatService.disconnect();
  }

  /**
   * Attempts to restore a session from localStorage when embedded.
   */
  private attemptSessionRestore(): void {
    const restored = this.webchatService.restoreSession();
    if (restored) {
      this.webchatService.connectWebSocket(restored.token);
      this.hasSession.set(true);
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
    queueMicrotask(() => this.initChatWindow(data.sessionId));
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
