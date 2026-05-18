import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfDrawerComponent,
  AfSelectInputComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  type AfSelectOption,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { type Called, type CalledStatus, CalledService } from '@core/services/called.service';
import { UserService } from '@core/services/user.service';
import { type User } from '@core/models/user.model';

/**
 * Ticket page — read-only CRUD listing for supervisors.
 *
 * @remarks
 * Displays all tickets with metrics (wait/service duration), evaluation summary,
 * search by protocol/contact/agent, and force-close action for authorized users.
 */
@Component({
  selector: 'app-ticket-page',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfSelectInputComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    AfDrawerComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './ticket-page.html',
})
export class TicketPage implements OnInit {
  private readonly calledService = inject(CalledService);
  private readonly userService = inject(UserService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly router = inject(Router);

  // ─── List state ────────────────────────────────────────────────────────────
  readonly tickets = signal<Called[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly searchTerm = signal('');
  readonly isFilterOpen = signal(false);
  private readonly pageNumber = signal(1);

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.tickets().length === 0,
  );

  // ─── Filter controls ───────────────────────────────────────────────────────
  readonly filterStatusControl = new FormControl<string>('all', { nonNullable: true });
  readonly filterAgentControl = new FormControl<string>('all', { nonNullable: true });
  readonly filterDateControl = new FormControl<string>('all', { nonNullable: true });

  // ─── Status filter ─────────────────────────────────────────────────────────
  readonly statusOptions: AfSelectOption[] = [
    { label: 'Todos os status', value: 'all' },
    { label: 'Aberto', value: 'open' },
    { label: 'Pendente', value: 'pending' },
    { label: 'Em atendimento', value: 'in_progress' },
    { label: 'Encerrado', value: 'closed' },
  ];

  // ─── Agent filter ──────────────────────────────────────────────────────────
  private readonly agents = signal<User[]>([]);
  readonly agentOptions = computed<AfSelectOption[]>(() => [
    { label: 'Todos os agentes', value: 'all' },
    ...this.agents().map((u) => ({ label: u.name, value: u.id })),
  ]);

  // ─── Date range filter ────────────────────────────────────────────────────
  readonly dateRangeOptions: AfSelectOption[] = [
    { label: 'Qualquer período', value: 'all' },
    { label: 'Hoje', value: 'today' },
    { label: 'Últimos 7 dias', value: '7d' },
    { label: 'Últimos 30 dias', value: '30d' },
    { label: 'Últimos 90 dias', value: '90d' },
  ];

  // ─── Force close ──────────────────────────────────────────────────────────
  readonly showForceCloseModal = signal(false);
  readonly ticketToForceClose = signal<Called | null>(null);
  readonly isForceClosing = signal(false);

  readonly forceCloseMessage = computed(() => {
    const t = this.ticketToForceClose();
    if (!t) return '';
    const contact = t.contact?.name || 'Desconhecido';
    return `Tem certeza que deseja encerrar forçadamente o atendimento do contato "${contact}" (protocolo ${t.protocol || '—'})? O cliente NÃO será notificado e nenhuma avaliação será enviada.`;
  });

  constructor() {
    this.filterStatusControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pageNumber.set(1);
        this.loadTickets();
      });

    this.filterAgentControl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.pageNumber.set(1);
      this.loadTickets();
    });

    this.filterDateControl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.pageNumber.set(1);
      this.loadTickets();
    });
  }

  ngOnInit(): void {
    this.loadTickets();
    this.loadAgents();
  }

  // ─── Public actions ────────────────────────────────────────────────────────

  openFilters(): void {
    this.isFilterOpen.set(true);
  }

  closeFilters(): void {
    this.isFilterOpen.set(false);
  }

  clearFilters(): void {
    this.filterStatusControl.setValue('all');
    this.filterAgentControl.setValue('all');
    this.filterDateControl.setValue('all');
  }

  applyFilters(): void {
    this.pageNumber.set(1);
    this.loadTickets();
    this.closeFilters();
  }

  onSearch(term: string): void {
    this.searchTerm.set(term);
    this.pageNumber.set(1);
    this.loadTickets();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadTickets();
  }

  retry(): void {
    this.loadTickets();
  }

  /** Navigate to ticket details page */
  viewTicket(ticket: Called): void {
    void this.router.navigate(['/chat/ticket', ticket.id]);
  }

  /** Open force close confirmation */
  openForceClose(ticket: Called): void {
    this.ticketToForceClose.set(ticket);
    this.showForceCloseModal.set(true);
  }

  /** Execute force close after confirmation */
  handleForceCloseConfirmed(): void {
    const ticket = this.ticketToForceClose();
    if (!ticket || this.isForceClosing()) return;

    this.isForceClosing.set(true);
    this.calledService
      .close(ticket.id, { mode: 'forced' })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isForceClosing.set(false);
          this.showForceCloseModal.set(false);
          this.ticketToForceClose.set(null);
          this.toast.success('Atendimento encerrado forçadamente.');
          this.loadTickets();
        },
        error: () => {
          this.isForceClosing.set(false);
          this.toast.error('Erro ao encerrar atendimento. Verifique suas permissões.');
        },
      });
  }

  // ─── Helpers ───────────────────────────────────────────────────────────────

  /** Map CalledStatus to AfStatusBadge status type */
  mapTicketStatus(status: CalledStatus): 'online' | 'warning' | 'idle' | 'offline' {
    const map: Record<CalledStatus, 'online' | 'warning' | 'idle' | 'offline'> = {
      open: 'online',
      pending: 'warning',
      in_progress: 'idle',
      closed: 'offline',
    };
    return map[status] ?? 'offline';
  }

  /** Translate status to Portuguese label */
  translateStatus(status: CalledStatus): string {
    const map: Record<CalledStatus, string> = {
      open: 'Aberto',
      pending: 'Pendente',
      in_progress: 'Em atendimento',
      closed: 'Encerrado',
    };
    return map[status] ?? status;
  }

  /** Format seconds to a human-readable duration (e.g. 2h 15m, 45s) */
  formatDuration(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined) return '—';
    if (seconds < 60) return `${seconds}s`;

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${minutes}m`;
  }

  // ─── Data loading ──────────────────────────────────────────────────────────

  private loadTickets(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const statusValue = this.filterStatusControl.value;
    const agentValue = this.filterAgentControl.value;
    const dateValue = this.filterDateControl.value;

    const status = statusValue !== 'all' ? (statusValue as CalledStatus) : undefined;
    const agent_id = agentValue !== 'all' ? agentValue : undefined;
    const { from, to } = this.computeDateRange(dateValue);

    this.calledService
      .list({
        search: this.searchTerm() || undefined,
        page: this.pageNumber(),
        per_page: 15,
        group_by_contact: false,
        status,
        agent_id,
        from,
        to,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.tickets.set(response.data);
          this.meta.set(response.meta);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.hasError.set(true);
        },
      });
  }

  private loadAgents(): void {
    this.userService
      .list({ is_active: true, per_page: 200, sort_by: 'name', sort_dir: 'asc' })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.agents.set(response.data),
      });
  }

  /** Compute from/to ISO dates based on the selected date range preset */
  private computeDateRange(value: string): { from?: string; to?: string } {
    if (value === 'all') return {};
    const now = new Date();
    const to = now.toISOString().slice(0, 10);

    if (value === 'today') return { from: to, to };

    const daysMap: Record<string, number> = { '7d': 7, '30d': 30, '90d': 90 };
    const days = daysMap[value];
    if (!days) return {};

    const fromDate = new Date(now);
    fromDate.setDate(fromDate.getDate() - days);
    return { from: fromDate.toISOString().slice(0, 10), to };
  }
}

export default TicketPage;
