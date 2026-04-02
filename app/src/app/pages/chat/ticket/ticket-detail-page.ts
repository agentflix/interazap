import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfConfirmModalComponent,
  AfDescriptionListComponent,
  AfPageTitleComponent,
  type AfDescriptionItem,
} from '@shared/components';
import { UserChat } from '../components/user-chat/user-chat';
import { ToastService } from '@core/services/toast.service';
import { type Called, type CalledStatus, CalledService } from '@core/services/called.service';

/**
 * Ticket Detail page — read-only detail view with message history.
 *
 * @remarks
 * Shows ticket metadata, metrics, evaluation, and chat-like read-only message history.
 * Supports force close from the detail page.
 *
 * @example
 * ```html
 * <app-ticket-detail-page />
 * ```
 */
@Component({
  selector: 'app-ticket-detail-page',
  standalone: true,
  imports: [
    LucideAngularModule,
    AfAlertComponent,
    AfButtonComponent,
    AfConfirmModalComponent,
    AfDescriptionListComponent,
    AfPageTitleComponent,
    UserChat,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './ticket-detail-page.html',
})
export class TicketDetailPage implements OnInit {
  private readonly calledService = inject(CalledService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  // ─── Ticket state ──────────────────────────────────────────────────────────
  readonly ticket = signal<Called | null>(null);
  readonly currentTicketId = signal<string | null>(null);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);

  readonly pageTitle = computed(() => {
    const t = this.ticket();
    if (t === null) return 'Carregando...';
    const label =
      t.protocol !== null && t.protocol !== undefined && t.protocol !== ''
        ? t.protocol
        : String(t.id);
    return `Atendimento #${label}`;
  });

  // ─── Force close ──────────────────────────────────────────────────────────
  readonly showForceCloseModal = signal(false);
  readonly isForceClosing = signal(false);

  readonly forceCloseMsg = computed(() => {
    const t = this.ticket();
    if (t === null) return '';
    const contact = t.contact?.name ?? 'Desconhecido';
    return `Tem certeza que deseja encerrar forçadamente o atendimento do contato "${contact}"? O cliente NÃO será notificado.`;
  });

  // ─── Description list items ────────────────────────────────────────────────
  readonly ticketInfoItems = computed<AfDescriptionItem[]>(() => {
    const t = this.ticket();
    if (t === null) return [];
    return this.buildTicketInfoItems(t);
  });

  readonly metricsItems = computed<AfDescriptionItem[]>(() => {
    const t = this.ticket();
    if (t === null) return [];
    return this.buildMetricsItems(t);
  });

  ngOnInit(): void {
    const ticketId = this.route.snapshot.paramMap.get('ticketId');
    if (ticketId !== null && ticketId !== '') {
      this.currentTicketId.set(ticketId);
      this.loadTicket(ticketId);
    }
  }

  // ─── Public actions ────────────────────────────────────────────────────────

  goBack(): void {
    void this.router.navigate(['/chat/ticket']);
  }

  openForceClose(): void {
    this.showForceCloseModal.set(true);
  }

  handleForceCloseConfirmed(): void {
    const t = this.ticket();
    if (!t || this.isForceClosing()) return;

    this.isForceClosing.set(true);
    this.calledService
      .close(t.id, { mode: 'forced' })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isForceClosing.set(false);
          this.showForceCloseModal.set(false);
          this.ticket.set(response.data);
          this.toast.success('Atendimento encerrado forçadamente.');
        },
        error: () => {
          this.isForceClosing.set(false);
          this.toast.error('Erro ao encerrar atendimento.');
        },
      });
  }

  // ─── Helpers ───────────────────────────────────────────────────────────────

  /** Build ticket info description items — extracted to reduce computed complexity */
  private buildTicketInfoItems(t: Called): AfDescriptionItem[] {
    const closedModeMap: Record<string, string> = { forced: 'Forçado', normal: 'Normal' };
    const closedModeDetail =
      t.closed_mode !== null && t.closed_mode !== undefined
        ? (closedModeMap[t.closed_mode] ?? '—')
        : '—';
    const createdDate =
      t.queued_at !== null && t.queued_at !== undefined ? t.queued_at : (t.created_at ?? undefined);

    return [
      { term: 'Protocolo', detail: t.protocol ?? '—' },
      { term: 'Status', detail: this.translateStatus(t.status) },
      { term: 'Canal', detail: this.translateChannel(t.channel) },
      { term: 'Contato', detail: t.contact?.name ?? '—' },
      { term: 'Telefone', detail: t.contact?.phone ?? '—' },
      { term: 'Agente', detail: t.user?.name ?? '—' },
      { term: 'Criado em', detail: this.formatDate(createdDate) },
      { term: 'Encerrado em', detail: this.formatDate(t.closed_at) },
      { term: 'Modo de encerramento', detail: closedModeDetail },
    ];
  }

  /** Build metrics description items — extracted to reduce computed complexity */
  private buildMetricsItems(t: Called): AfDescriptionItem[] {
    const hasEval = t.evaluation?.has_evaluation === true;
    const evalDetail = hasEval ? `${t.evaluation?.rating ?? '—'} estrelas` : 'Sem avaliação';

    return [
      { term: 'Tempo de espera', detail: this.formatDuration(t.wait_duration_seconds) },
      { term: 'Tempo de atendimento', detail: this.formatDuration(t.service_duration_seconds) },
      { term: 'Avaliação', detail: evalDetail },
      { term: 'Comentário', detail: t.evaluation?.comment ?? '—' },
      { term: 'Sentimento', detail: this.translateSentiment(t.sentiment) },
    ];
  }

  private translateStatus(status: CalledStatus): string {
    const map: Record<CalledStatus, string> = {
      open: 'Aberto',
      pending: 'Pendente',
      in_progress: 'Em atendimento',
      closed: 'Encerrado',
    };
    return map[status] ?? status;
  }

  private translateChannel(channel: string): string {
    const map: Record<string, string> = {
      whatsapp: 'WhatsApp',
      internal: 'Interno',
      external: 'Externo',
      portal: 'Portal',
    };
    return map[channel] ?? channel;
  }

  private translateSentiment(sentiment: string | null | undefined): string {
    if (sentiment === null || sentiment === undefined) return '—';
    const map: Record<string, string> = {
      positive: 'Positivo',
      neutral: 'Neutro',
      negative: 'Negativo',
      critical: 'Crítico',
    };
    return map[sentiment] ?? sentiment;
  }

  private formatDuration(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined) return '—';
    if (seconds < 60) return `${seconds}s`;
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${minutes}m`;
  }

  private formatDate(value: string | null | undefined): string {
    if (value === null || value === undefined) return '—';
    try {
      return new Date(value).toLocaleString('pt-BR');
    } catch {
      return value;
    }
  }

  // ─── Data loading ──────────────────────────────────────────────────────────

  private loadTicket(ticketId: string): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.calledService
      .get(ticketId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.ticket.set(response.data);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.hasError.set(true);
        },
      });
  }
}

export default TicketDetailPage;
