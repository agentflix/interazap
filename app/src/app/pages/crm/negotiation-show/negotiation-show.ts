import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import { type Observable } from 'rxjs';
import { map, filter, switchMap, finalize } from 'rxjs/operators';
import { PageTitle } from '@shared/components/page-title/page-title';
import { ButtonComponent } from '@shared/components/buttons';
import { toast } from 'ngx-sonner';
import {
  type Negotiation,
  type NegotiationCompanySummary,
  type NegotiationContactSummary,
  NegotiationService,
} from 'src/app/core/services/negotiation.service';
import { type Funnel, type FunnelStep, FunnelService } from 'src/app/core/services/funnel.service';
import { type ReasonLoss, ReasonLossService } from 'src/app/core/services/reason-loss.service';
import { ContactService } from 'src/app/core/services/contact.service';
import { type Contact } from 'src/app/core/models/contact.model';
import { type CRMCompany, CRMCompanyService } from 'src/app/core/services/crm-company.service';
import {
  type NegotiationTask,
  NegotiationTaskService,
} from 'src/app/core/services/negotiation-task.service';
import { NegotiationAnnotationService } from 'src/app/core/services/negotiation-annotation.service';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import { RealtimeService } from 'src/app/core/services/realtime.service';
import {
  type MetricCard,
  type NegotiationBadge,
  type NegotiationPayloadResponse,
  type NegotiationTabId,
} from './negotiation-show.model';
import { getStatusLabel, normalizeId } from './negotiation-show.utils';
import {
  NegotiationContactsTabComponent,
  NegotiationEditModalComponent,
  NegotiationFilesTabComponent,
  NegotiationHeaderComponent,
  NegotiationHistoryTabComponent,
  NegotiationLossModalComponent,
  NegotiationProductsTabComponent,
  NegotiationSummaryCardComponent,
  NegotiationTasksTabComponent,
  NegotiationUpcomingTasksComponent,
  NegotiationContactCardComponent,
  NegotiationCompanyCardComponent,
} from './components';
import { ProposalListComponent } from '../proposals/proposal-list/proposal-list';

/**
 * Parent page for negotiation details orchestrating feature components.
 */
@Component({
  selector: 'app-negotiation-show',
  standalone: true,
  imports: [
    PageTitle,
    ButtonComponent,
    NegotiationHeaderComponent,
    NegotiationSummaryCardComponent,
    NegotiationUpcomingTasksComponent,
    NegotiationContactCardComponent,
    NegotiationCompanyCardComponent,
    NegotiationHistoryTabComponent,
    NegotiationTasksTabComponent,
    NegotiationContactsTabComponent,
    NegotiationProductsTabComponent,
    NegotiationFilesTabComponent,
    NegotiationLossModalComponent,
    NegotiationEditModalComponent,
    ProposalListComponent,
  ],
  templateUrl: './negotiation-show.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationShow {
  private readonly currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
  });
  private readonly route = inject(ActivatedRoute);
  private readonly negotiationService = inject(NegotiationService);
  private readonly funnelService = inject(FunnelService);
  private readonly reasonLossService = inject(ReasonLossService);
  private readonly contactService = inject(ContactService);
  private readonly crmCompanyService = inject(CRMCompanyService);
  private readonly negotiationTaskService = inject(NegotiationTaskService);
  private readonly negotiationAnnotationService = inject(NegotiationAnnotationService);
  private readonly authStore = inject(AuthStoreService);
  private readonly realtimeService = inject(RealtimeService);
  private readonly destroyRef = inject(DestroyRef);

  readonly negotiation = signal<Negotiation | null>(null);
  readonly isLoading = signal(true);
  readonly isUpdatingStatus = signal(false);
  readonly isUpdatingDetails = signal(false);
  readonly errorMessage = signal<string | null>(null);

  readonly activeTab = signal<NegotiationTabId>('history');

  readonly funnels = signal<Funnel[]>([]);
  readonly steps = signal<FunnelStep[]>([]);
  readonly contacts = signal<Contact[]>([]);
  readonly companies = signal<CRMCompany[]>([]);
  readonly reasonLosses = signal<ReasonLoss[]>([]);

  readonly tasks = signal<NegotiationTask[]>([]);
  readonly isTasksLoading = signal(false);
  readonly openTaskModalToken = signal(0);

  readonly isLossModalOpen = signal(false);
  readonly isEditModalOpen = signal(false);
  readonly showWinCelebration = signal(false);

  readonly confettiPieces = [
    { id: 1, icon: '🎉', left: 8, delay: 0 },
    { id: 2, icon: '✨', left: 16, delay: 100 },
    { id: 3, icon: '🎊', left: 24, delay: 50 },
    { id: 4, icon: '🥳', left: 33, delay: 120 },
    { id: 5, icon: '🎉', left: 42, delay: 80 },
    { id: 6, icon: '✨', left: 52, delay: 0 },
    { id: 7, icon: '🎊', left: 63, delay: 140 },
    { id: 8, icon: '🥳', left: 72, delay: 20 },
    { id: 9, icon: '🎉', left: 82, delay: 110 },
    { id: 10, icon: '✨', left: 90, delay: 60 },
  ] as const;

  readonly currentUserId = computed(() => this.authStore.user()?.id ?? null);

  readonly resolvedContact = computed<NegotiationContactSummary | null>(() => {
    const current = this.negotiation();
    if (!current) return null;

    if (current.contact?.name) {
      return current.contact;
    }

    const contactId = current.contact_id;
    if (!contactId) return null;

    const found = this.contacts().find((item) => String(item.id) === String(contactId));
    return found ? { id: found.id, name: found.name } : null;
  });

  readonly resolvedCompany = computed<NegotiationCompanySummary | null>(() => {
    const current = this.negotiation();
    if (!current) return null;

    if (current.crm_company?.name) {
      return current.crm_company;
    }

    if (current.company?.name) {
      return current.company;
    }

    const companyId = current.crm_company_id ?? current.company_id;
    if (!companyId) return null;

    const found = this.companies().find((item) => String(item.id) === String(companyId));
    if (!found) return null;

    return {
      id: found.id,
      name: found.name,
      address: found.address,
      city: found.city,
      state: found.state,
      zip_code: found.zip_code,
      phone: found.phone,
    };
  });

  readonly tabs: { id: NegotiationTabId; label: string }[] = [
    { id: 'history', label: 'Histórico' },
    { id: 'tasks', label: 'Tarefas' },
    { id: 'contacts', label: 'Contatos' },
    { id: 'products', label: 'Produtos e Serviços' },
    { id: 'proposals', label: 'Propostas' },
    { id: 'files', label: 'Arquivos' },
  ];

  readonly badges = computed<NegotiationBadge[]>(() => {
    const current = this.negotiation();
    if (!current) return [];

    const statusBadge: NegotiationBadge = {
      label: getStatusLabel(current.status),
      tone:
        current.status === 'won' ? 'success' : current.status === 'lost' ? 'warning' : 'primary',
    };

    const result: NegotiationBadge[] = [statusBadge];

    if (current.funnel?.name) {
      result.push({ label: current.funnel.name, tone: 'info' });
    }

    if (current.step?.name) {
      result.push({ label: current.step.name, tone: 'primary' });
    }

    return result;
  });

  readonly metrics = computed<MetricCard[]>(() => {
    const current = this.negotiation();
    return [
      {
        label: 'Valor total',
        value: this.formatCurrency(current?.value ?? 0),
        helper: 'Calculado pelos produtos',
      },
      {
        label: 'Previsão de fechamento',
        value: this.formatDate(current?.expected_close_date),
        helper: 'Atualize conforme necessário',
      },
      {
        label: 'Criada em',
        value: this.formatDateTime(current?.created_at),
        helper: 'Data de abertura',
      },
      {
        label: 'Etapa atual',
        value: current?.step?.name ?? '-',
        helper: current?.funnel?.name ? `Funil: ${current.funnel.name}` : 'Funil não informado',
      },
    ];
  });

  constructor() {
    this.loadFunnels();
    this.loadReasonLosses();
    this.loadContacts();
    this.loadCompanies();
    this.listenRealtime();

    this.route.paramMap
      .pipe(
        map((params) => params.get('id')),
        filter((id): id is string => Boolean(id)),
        switchMap((id) => {
          this.isLoading.set(true);
          this.errorMessage.set(null);
          return this.negotiationService.get(id);
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          const payload = this.resolveNegotiationPayload(response.data);
          if (!payload) {
            this.isLoading.set(false);
            this.errorMessage.set('Não foi possível carregar a negociação.');
            return;
          }

          this.negotiation.set(this.normalizeNegotiation(payload));
          this.loadSteps(payload.funnel_id);
          this.loadTasks(payload.id);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.errorMessage.set('Não foi possível carregar a negociação.');
        },
      });
  }

  setActiveTab(tabId: NegotiationTabId): void {
    this.activeTab.set(tabId);
    if (tabId === 'tasks') {
      this.loadTasks();
    }
  }

  onCreateTaskFromSummary(): void {
    this.activeTab.set('tasks');
    this.openTaskModalToken.update((value) => value + 1);
  }

  onChildError(message: string): void {
    toast.error(message);
  }

  onFunnelChange(value: string | number): void {
    const funnelId = normalizeId(value);
    if (!funnelId) return;

    this.funnelService
      .listSteps(funnelId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const nextSteps = response.data.steps ?? [];
          this.steps.set(nextSteps);
          const firstStep = nextSteps[0];
          if (firstStep) {
            this.updateNegotiation({ funnel_id: funnelId, step_id: firstStep.id });
          } else {
            this.errorMessage.set('Este funil não possui etapas configuradas.');
          }
        },
        error: () => {
          this.errorMessage.set('Não foi possível carregar as etapas do funil.');
        },
      });
  }

  onStepChange(value: string | number): void {
    const stepId = normalizeId(value);
    if (!stepId) return;
    this.updateNegotiation({ step_id: stepId });
  }

  onExpectedCloseChange(value: string): void {
    this.updateNegotiation({ expected_close_date: value || undefined });
  }

  markAsWon(): void {
    this.runStatusTransition(
      (id) => this.negotiationService.markAsWon(id),
      'Status alterado para Ganha.',
      'Não foi possível marcar como ganha.',
      () => this.triggerWinCelebration(),
    );
  }

  reopen(): void {
    this.runStatusTransition(
      (id) => this.negotiationService.reopen(id),
      'Status alterado para Aberta.',
      'Não foi possível reabrir a negociação.',
    );
  }

  openLossModal(): void {
    this.isLossModalOpen.set(true);
  }

  closeLossModal(): void {
    this.isLossModalOpen.set(false);
  }

  confirmLoss(event: { reasonId: string | number; comment?: string }): void {
    this.runStatusTransition(
      (id) => this.negotiationService.markAsLost(id, event.reasonId, event.comment),
      'Status alterado para Perdida.',
      'Não foi possível marcar como perdida.',
      () => this.closeLossModal(),
    );
  }

  openEditModal(): void {
    this.isEditModalOpen.set(true);
  }

  closeEditModal(): void {
    this.isEditModalOpen.set(false);
  }

  onNegotiationSaved(negotiation: Negotiation): void {
    const previous = this.negotiation();
    const normalized = this.normalizeNegotiation(negotiation);
    this.negotiation.set(normalized);
    this.loadSteps(normalized.funnel_id);

    const changeSummary = this.buildNegotiationChangeSummary(previous, normalized);
    if (changeSummary.length > 0) {
      this.trackNegotiationChange(`Alterações na negociação: ${changeSummary.join(' · ')}`);
    }

    this.closeEditModal();
    toast.success('Negociação atualizada com sucesso.');
  }

  loadTasks(negotiationId?: string | number): void {
    const id = negotiationId ?? this.negotiation()?.id;
    if (!id) return;

    this.isTasksLoading.set(true);
    this.negotiationTaskService
      .list(id)
      .pipe(
        finalize(() => this.isTasksLoading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => this.tasks.set(response.data.tasks ?? []),
        error: () => {
          this.tasks.set([]);
        },
      });
  }

  refreshNegotiation(): void {
    const current = this.negotiation();
    if (!current) return;

    this.negotiationService
      .get(current.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const payload = this.resolveNegotiationPayload(response.data);
          if (!payload) return;
          this.negotiation.set(this.normalizeNegotiation(payload));
        },
        error: () => {
          // Keep current state if refresh fails.
        },
      });
  }

  private updateNegotiation(payload: Partial<Negotiation>): void {
    const current = this.negotiation();
    if (!current || this.isUpdatingDetails()) return;

    this.isUpdatingDetails.set(true);
    this.negotiationService
      .update(current.id, {
        title: current.title,
        funnel_id: payload.funnel_id ?? current.funnel_id!,
        step_id: payload.step_id ?? current.step_id!,
        contact_id: current.contact_id!,
        crm_company_id: current.crm_company_id!,
        expected_close_date:
          payload.expected_close_date ?? current.expected_close_date ?? undefined,
        notes: current.notes ?? undefined,
        status: current.status,
      })
      .pipe(
        finalize(() => this.isUpdatingDetails.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          const updated = this.resolveNegotiationPayload(response.data);
          if (!updated) return;
          const nextNegotiation = this.normalizeNegotiation({ ...current, ...updated });
          this.negotiation.set(nextNegotiation);

          const changeSummary = this.buildNegotiationChangeSummary(current, nextNegotiation);
          if (changeSummary.length > 0) {
            this.trackNegotiationChange(`Alterações na negociação: ${changeSummary.join(' · ')}`);
          }
        },
        error: () => {
          this.errorMessage.set('Não foi possível atualizar a negociação.');
        },
      });
  }

  private runStatusTransition(
    request: (negotiationId: string | number) => Observable<{ data: NegotiationPayloadResponse }>,
    successNote: string,
    errorMessage: string,
    onSuccess?: () => void,
  ): void {
    const current = this.negotiation();
    if (!current || this.isUpdatingStatus()) {
      return;
    }

    this.isUpdatingStatus.set(true);
    request(current.id)
      .pipe(
        finalize(() => this.isUpdatingStatus.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          const updated = this.resolveNegotiationPayload(response.data);
          if (!updated) {
            return;
          }

          const nextNegotiation = this.normalizeNegotiation({ ...current, ...updated });
          this.negotiation.set(nextNegotiation);
          this.trackNegotiationChange(successNote);
          onSuccess?.();
        },
        error: () => {
          this.errorMessage.set(errorMessage);
        },
      });
  }

  private listenRealtime(): void {
    this.realtimeService.connect();
    this.realtimeService
      .on<{
        negotiation_id?: string | number;
        action?: string;
        task?: NegotiationTask;
        task_id?: string | number;
      }>('negotiation.task.changed')
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((payload) => {
        const current = this.negotiation();
        if (!current || String(current.id) !== String(payload.negotiation_id)) return;

        if (payload.action === 'deleted' && payload.task_id) {
          this.tasks.set(
            this.tasks().filter((item) => String(item.id) !== String(payload.task_id)),
          );
          return;
        }

        if (payload.task) {
          const exists = this.tasks().some((item) => String(item.id) === String(payload.task!.id));
          if (exists) {
            this.tasks.set(
              this.tasks().map((item) =>
                String(item.id) === String(payload.task!.id) ? { ...item, ...payload.task! } : item,
              ),
            );
          } else {
            this.tasks.set([payload.task, ...this.tasks()]);
          }
        }
      });
  }

  private loadFunnels(): void {
    this.funnelService
      .all()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.funnels.set(response.data.funnels ?? []),
        error: () => {
          this.errorMessage.set('Não foi possível carregar os funis.');
        },
      });
  }

  private loadSteps(funnelId?: string | number | null): void {
    if (!funnelId) {
      this.steps.set([]);
      return;
    }

    this.funnelService
      .listSteps(funnelId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.steps.set(response.data.steps ?? []),
        error: () => {
          this.steps.set([]);
        },
      });
  }

  private loadContacts(): void {
    this.contactService
      .list({ per_page: 100, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.contacts.set(response.data),
        error: () => {
          this.contacts.set([]);
        },
      });
  }

  private loadCompanies(): void {
    this.crmCompanyService
      .list({ per_page: 100, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.companies.set(response.data),
        error: () => {
          this.companies.set([]);
        },
      });
  }

  private loadReasonLosses(): void {
    this.reasonLossService
      .all()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.reasonLosses.set(response.data ?? []),
        error: () => {
          this.reasonLosses.set([]);
        },
      });
  }

  private resolveNegotiationPayload(
    payload: NegotiationPayloadResponse | null | undefined,
  ): Negotiation | null {
    if (!payload) {
      return null;
    }

    return 'negotiation' in payload ? payload.negotiation : payload;
  }

  private normalizeNegotiation(negotiation: Negotiation): Negotiation {
    const normalized = { ...negotiation } as Negotiation & {
      amount?: number;
      expected_close?: string;
      crm_contact_id?: string | number;
      crm_negotiation_funnel_id?: string | number;
      crm_negotiation_funnel_step_id?: string | number;
      auth_user_id?: string | number;
      crm_contact?: NegotiationContactSummary;
    };

    const value = normalized.value ?? normalized.amount;
    const expectedCloseDate = normalized.expected_close_date ?? normalized.expected_close;
    const contactId = normalized.contact_id ?? normalized.crm_contact_id;
    const companyId = normalized.crm_company_id ?? normalized.company_id;
    const funnelId = normalized.funnel_id ?? normalized.crm_negotiation_funnel_id;
    const stepId = normalized.step_id ?? normalized.crm_negotiation_funnel_step_id;
    const userId = normalized.user_id ?? normalized.auth_user_id;
    const contact = normalized.contact ?? normalized.crm_contact;
    const company = normalized.crm_company ?? normalized.company;

    return {
      ...normalized,
      value,
      expected_close_date: expectedCloseDate,
      contact_id: contactId,
      crm_company_id: companyId,
      company_id: companyId,
      funnel_id: funnelId,
      step_id: stepId,
      user_id: userId,
      contact,
      crm_company: company,
      company,
    };
  }

  private buildNegotiationChangeSummary(
    previous: Negotiation | null,
    current: Negotiation,
  ): string[] {
    if (!previous) {
      return [];
    }

    const changes: string[] = [];

    if (previous.title !== current.title) {
      changes.push(`Título: "${previous.title}" → "${current.title}"`);
    }

    if (String(previous.funnel_id ?? '') !== String(current.funnel_id ?? '')) {
      changes.push(
        `Funil: ${this.getFunnelLabel(previous.funnel_id)} → ${this.getFunnelLabel(current.funnel_id)}`,
      );
    }

    if (String(previous.step_id ?? '') !== String(current.step_id ?? '')) {
      changes.push(
        `Etapa: ${this.getStepLabel(previous.step_id)} → ${this.getStepLabel(current.step_id)}`,
      );
    }

    if (String(previous.contact_id ?? '') !== String(current.contact_id ?? '')) {
      changes.push(`Contato principal atualizado`);
    }

    if (String(previous.crm_company_id ?? '') !== String(current.crm_company_id ?? '')) {
      changes.push(`Empresa atualizada`);
    }

    if ((previous.expected_close_date ?? '') !== (current.expected_close_date ?? '')) {
      changes.push(
        `Previsão: ${this.formatDate(previous.expected_close_date)} → ${this.formatDate(current.expected_close_date)}`,
      );
    }

    if ((previous.status ?? '') !== (current.status ?? '')) {
      changes.push(
        `Status: ${getStatusLabel(previous.status)} → ${getStatusLabel(current.status)}`,
      );
    }

    return changes;
  }

  private getFunnelLabel(funnelId?: string | number): string {
    if (!funnelId) return '-';
    const current = this.funnels().find((item) => String(item.id) === String(funnelId));
    return current?.name ?? String(funnelId);
  }

  private getStepLabel(stepId?: string | number): string {
    if (!stepId) return '-';
    const current = this.steps().find((item) => String(item.id) === String(stepId));
    return current?.name ?? String(stepId);
  }

  private trackNegotiationChange(content: string): void {
    const current = this.negotiation();
    if (!current?.id) return;

    const trimmed = content.trim();
    if (trimmed.length === 0) return;

    this.negotiationAnnotationService
      .create(current.id, { content: trimmed })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        error: () => {
          // Keep action success even when note creation fails.
        },
      });
  }

  private triggerWinCelebration(): void {
    this.showWinCelebration.set(true);
    this.playWinSound();

    window.setTimeout(() => {
      this.showWinCelebration.set(false);
    }, 1700);
  }

  private playWinSound(): void {
    const audioContextConstructor =
      window.AudioContext ??
      (window as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!audioContextConstructor) {
      return;
    }

    const audioContext = new audioContextConstructor();
    const now = audioContext.currentTime;

    const notes = [523.25, 659.25, 783.99];
    notes.forEach((frequency, index) => {
      const oscillator = audioContext.createOscillator();
      const gainNode = audioContext.createGain();

      oscillator.type = 'triangle';
      oscillator.frequency.setValueAtTime(frequency, now);

      gainNode.gain.setValueAtTime(0.0001, now + index * 0.09);
      gainNode.gain.exponentialRampToValueAtTime(0.18, now + index * 0.09 + 0.02);
      gainNode.gain.exponentialRampToValueAtTime(0.0001, now + index * 0.09 + 0.18);

      oscillator.connect(gainNode);
      gainNode.connect(audioContext.destination);
      oscillator.start(now + index * 0.09);
      oscillator.stop(now + index * 0.09 + 0.2);
    });

    window.setTimeout(() => {
      audioContext.close().catch(() => undefined);
    }, 500);
  }

  formatDate(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '-' : date.toLocaleDateString('pt-BR');
  }

  formatDateTime(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
      ? '-'
      : date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  }

  private formatCurrency(value: number): string {
    return this.currencyFormatter.format(value);
  }
}
