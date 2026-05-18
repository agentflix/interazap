import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  effect,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { FormControl } from '@angular/forms';
import { toSignal, takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime, distinctUntilChanged, startWith, map } from 'rxjs/operators';
import { LucideAngularModule } from 'lucide-angular';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { CurrencyPipe } from '@shared/pipes/currency.pipe';
import {
  type CdkDragDrop,
  DragDropModule,
  moveItemInArray,
  transferArrayItem,
} from '@angular/cdk/drag-drop';
import { merge } from 'rxjs';
import { toast } from 'ngx-sonner';
import {
  AfDataTableComponent,
  AfDrawerComponent,
  AfEmptyStateComponent,
  AfPaginationComponent,
  AfSkeletonTableRowComponent,
  AfTableActionsComponent,
} from '@shared/components';
import { ButtonComponent, LoadingButtonComponent } from '@shared/components/buttons';
import { ModalComponent } from '@shared/components/modal/modal';
import { PageTitle } from '@shared/components/page-title/page-title';
import {
  type SelectOption,
  CurrencyInputComponent,
  SearchInputComponent,
  SelectInputComponent,
  TextInputComponent,
} from '@shared/components/inputs';
import {
  type KanbanStepPage,
  type Negotiation,
  type NegotiationFilters,
  type NegotiationKanbanStep,
  type NegotiationStatus,
  NegotiationService,
} from 'src/app/core/services/negotiation.service';
import { type AfReportFilterPayload } from '@shared/components/report-filters/report-filters';
import { type AfReportExportPayload } from '@shared/components/report-export/report-export';
import { type Funnel, FunnelService } from 'src/app/core/services/funnel.service';
import { type Contact } from 'src/app/core/models/contact.model';
import { ContactService } from 'src/app/core/services/contact.service';
import { type CRMCompany, CRMCompanyService } from 'src/app/core/services/crm-company.service';
import { UserService } from 'src/app/core/services/user.service';
import { type User } from '@core/models/user.model';
import { NegotiationFormComponent } from './components';
import { formatDate } from '@shared/utils/string.utils';

import type { NegotiationFilterChip, ViewMode } from './negotiations.model';
export * from './negotiations.model';


type NegotiationStatusFilter = 'all' | NegotiationStatus;

@Component({
  selector: 'app-negotiations',
  standalone: true,
  imports: [
    RouterLink,
    LucideAngularModule,
    DragDropModule,
    NegotiationFormComponent,
    ButtonComponent,
    LoadingButtonComponent,
    ModalComponent,
    PageTitle,
    CurrencyInputComponent,
    SelectInputComponent,
    SearchInputComponent,
    TextInputComponent,
    CurrencyPipe,
    AfDataTableComponent,
    AfDrawerComponent,
    AfEmptyStateComponent,
    AfPaginationComponent,
    AfSkeletonTableRowComponent,
    AfTableActionsComponent,
  ],
  templateUrl: './negotiations.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Negotiations {
  private readonly negotiationService = inject(NegotiationService);
  private readonly funnelService = inject(FunnelService);
  private readonly userService = inject(UserService);
  private readonly contactService = inject(ContactService);
  private readonly crmCompanyService = inject(CRMCompanyService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  readonly viewMode = signal<ViewMode>('kanban');
  readonly isFilterOpen = signal(false);
  readonly isKanbanModalOpen = signal(false);
  readonly kanbanEditingItem = signal<Negotiation | null>(null);

  // ─── List state ────────────────────────────────────────────────────────────
  readonly negotiations = signal<Negotiation[]>([]);
  readonly isListLoading = signal(true);
  readonly listMeta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isListEmpty = computed(() => !this.isListLoading() && this.negotiations().length === 0);
  readonly isListModalOpen = signal(false);
  readonly listEditingItem = signal<Negotiation | null>(null);
  readonly isListDeleteOpen = signal(false);
  readonly listDeletingItem = signal<Negotiation | null>(null);
  readonly isListDeleting = signal(false);

  readonly negotiationForm = viewChild<NegotiationFormComponent>('negotiationForm');
  readonly kanbanForm = viewChild<NegotiationFormComponent>('kanbanForm');

  readonly funnelId = signal<string | number | null>(null);
  readonly funnels = signal<Funnel[]>([]);
  readonly kanbanSteps = signal<NegotiationKanbanStep[]>([]);
  readonly isKanbanLoading = signal(false);
  readonly isKanbanMoving = signal(false);
  /** Rastreia o estado de carregamento de "mais negociações" por etapa. */
  readonly kanbanStepLoading = signal<Record<string, boolean>>({});

  readonly contacts = signal<Contact[]>([]);
  readonly companies = signal<CRMCompany[]>([]);
  readonly users = signal<User[]>([]);

  readonly searchControl = new FormControl('', { nonNullable: true });
  readonly stepFilterControl = new FormControl<string | number | null>(null);
  readonly userFilterControl = new FormControl<string | number | null>(null);
  readonly dateFromControl = new FormControl<string | null>(null);
  readonly dateToControl = new FormControl<string | null>(null);

  readonly companyFilterControl = new FormControl<string | number | null>(null);
  readonly contactFilterControl = new FormControl<string | number | null>(null);
  readonly amountMinControl = new FormControl<number | null>(null);
  readonly amountMaxControl = new FormControl<number | null>(null);
  readonly expectedCloseFromControl = new FormControl<string | null>(null);
  readonly expectedCloseToControl = new FormControl<string | null>(null);

  readonly negotiationStatusControl = new FormControl<NegotiationStatusFilter>('all', {
    nonNullable: true,
  });
  readonly tempNegotiationStatusControl = new FormControl<NegotiationStatusFilter>('all', {
    nonNullable: true,
  });
  readonly negotiationStatus = toSignal(
    this.negotiationStatusControl.valueChanges.pipe(startWith(this.negotiationStatusControl.value)),
    { initialValue: this.negotiationStatusControl.value },
  );

  readonly funnelSelectControl = new FormControl<string | number | null>('');
  readonly funnelOptions = computed<SelectOption[]>(() => [
    { label: 'Todos os funis', value: '' },
    ...this.funnels().map((funnel) => ({ label: funnel.name, value: funnel.id })),
  ]);
  readonly stepOptions = computed<SelectOption[]>(() => {
    const funnelId = this.normalizeId(this.funnelSelectControl.value);
    if (!funnelId) {
      return [{ label: 'Todas as etapas', value: '' }];
    }

    const funnel = this.funnels().find((item) => String(item.id) === String(funnelId));
    const steps = funnel?.steps ?? [];

    return [
      { label: 'Todas as etapas', value: '' },
      ...steps.map((step) => ({ label: step.name, value: step.id })),
    ];
  });
  readonly responsibleOptions = computed<SelectOption[]>(() => [
    { label: 'Todos os responsáveis', value: '' },
    ...this.users().map((user) => ({ label: user.name, value: user.id })),
  ]);
  readonly companyOptions = computed<SelectOption[]>(() => [
    { label: 'Todas as empresas', value: '' },
    ...this.companies().map((company) => ({ label: company.name, value: company.id })),
  ]);
  readonly contactOptions = computed<SelectOption[]>(() => [
    { label: 'Todos os contatos', value: '' },
    ...this.contacts().map((contact) => ({ label: contact.name, value: contact.id })),
  ]);

  readonly activeFilterChips = computed<NegotiationFilterChip[]>(() => {
    const chips: NegotiationFilterChip[] = [];

    if (this.searchControl.value.trim()) {
      chips.push({ key: 'search', label: `Busca: ${this.searchControl.value.trim()}` });
    }

    if (this.funnelSelectControl.value) {
      const label = this.funnelOptions().find(
        (o) => String(o.value) === String(this.funnelSelectControl.value),
      )?.label;
      chips.push({ key: 'funnel_id', label: `Funil: ${label ?? this.funnelSelectControl.value}` });
    }

    if (this.stepFilterControl.value) {
      const label = this.stepOptions().find(
        (o) => String(o.value) === String(this.stepFilterControl.value),
      )?.label;
      chips.push({ key: 'step_id', label: `Etapa: ${label ?? this.stepFilterControl.value}` });
    }

    if (this.negotiationStatusControl.value !== 'all') {
      chips.push({
        key: 'status',
        label: `Status: ${this.getStatusLabel(this.negotiationStatusControl.value)}`,
      });
    }

    if (this.userFilterControl.value) {
      const label = this.responsibleOptions().find(
        (o) => String(o.value) === String(this.userFilterControl.value),
      )?.label;
      chips.push({
        key: 'user_id',
        label: `Responsável: ${label ?? this.userFilterControl.value}`,
      });
    }

    if (this.dateFromControl.value || this.dateToControl.value) {
      chips.push({
        key: 'period',
        label: `Período: ${this.dateFromControl.value || '...'} até ${this.dateToControl.value || '...'}`,
      });
    }

    if (this.companyFilterControl.value) {
      const label = this.companyOptions().find(
        (o) => String(o.value) === String(this.companyFilterControl.value),
      )?.label;
      chips.push({
        key: 'crm_company_id',
        label: `Empresa: ${label ?? this.companyFilterControl.value}`,
      });
    }

    if (this.contactFilterControl.value) {
      const label = this.contactOptions().find(
        (o) => String(o.value) === String(this.contactFilterControl.value),
      )?.label;
      chips.push({
        key: 'contact_id',
        label: `Contato: ${label ?? this.contactFilterControl.value}`,
      });
    }

    if (this.amountMinControl.value !== null || this.amountMaxControl.value !== null) {
      chips.push({
        key: 'amount',
        label: `Valor: ${this.amountMinControl.value ?? 0} - ${this.amountMaxControl.value ?? '∞'}`,
      });
    }

    if (this.expectedCloseFromControl.value || this.expectedCloseToControl.value) {
      chips.push({
        key: 'expected_close',
        label: `Prev. fechamento: ${this.expectedCloseFromControl.value || '...'} até ${this.expectedCloseToControl.value || '...'}`,
      });
    }

    return chips;
  });

  readonly advancedFiltersCount = computed(() => {
    let count = 0;
    if (this.companyFilterControl.value) count++;
    if (this.contactFilterControl.value) count++;
    if (this.amountMinControl.value !== null || this.amountMaxControl.value !== null) count++;
    if (this.expectedCloseFromControl.value || this.expectedCloseToControl.value) count++;
    return count;
  });

  readonly filterStatusOptions: SelectOption[] = [
    { label: 'Todos', value: 'all' },
    { label: 'Abertas', value: 'open' },
    { label: 'Ganhas', value: 'won' },
    { label: 'Perdidas', value: 'lost' },
  ];

  constructor() {
    this.funnelSelectControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.onFunnelChangeValue(value));

    this.funnelSelectControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.stepFilterControl.setValue(null, { emitEvent: false });
      });

    merge(
      this.funnelSelectControl.valueChanges,
      this.negotiationStatusControl.valueChanges,
      this.stepFilterControl.valueChanges,
      this.userFilterControl.valueChanges,
      this.dateFromControl.valueChanges,
      this.dateToControl.valueChanges,
      this.companyFilterControl.valueChanges,
      this.contactFilterControl.valueChanges,
      this.amountMinControl.valueChanges,
      this.amountMaxControl.valueChanges,
      this.expectedCloseFromControl.valueChanges,
      this.expectedCloseToControl.valueChanges,
      this.searchControl.valueChanges.pipe(debounceTime(300), distinctUntilChanged()),
    )
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.applyCurrentFilters());

    effect(() => {
      this.funnelSelectControl.setValue(this.funnelId() ?? '', { emitEvent: false });
    });

    this.hydrateFiltersFromQueryParams();
    this.loadFunnels();
    this.loadUsers();
    this.loadContacts();
    this.loadCompanies();
    this.reloadCurrentView();
  }

  openFilter(): void {
    this.tempNegotiationStatusControl.setValue(this.negotiationStatusControl.value);
    this.isFilterOpen.set(true);
  }

  closeFilter(): void {
    this.isFilterOpen.set(false);
  }

  applyFilter(): void {
    this.applyCurrentFilters();
    this.closeFilter();
  }

  clearFilter(): void {
    this.clearAllFilters();
  }

  setViewMode(mode: ViewMode): void {
    if (this.viewMode() === mode) {
      return;
    }

    this.viewMode.set(mode);
    this.reloadCurrentView();
  }

  onFunnelChange(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    if (!target) return;
    this.funnelId.set(this.normalizeId(target.value || null));
  }

  onFunnelChangeValue(value: string | number | null): void {
    this.funnelId.set(this.normalizeId(value || null));
  }

  removeActiveFilter(key: string): void {
    if (!this.resetFilterByKey(key)) {
      return;
    }

    this.applyCurrentFilters();
  }

  onFiltersApplied(payload: AfReportFilterPayload): void {
    this.dateFromControl.setValue(payload.startDate || null);
    this.dateToControl.setValue(payload.endDate || null);
    this.negotiationStatusControl.setValue((payload.status as NegotiationStatusFilter) || 'all');
  }

  onExport(payload: AfReportExportPayload): void {
    console.warn('Export requested:', payload.format);
  }

  openListCreate(): void {
    this.listEditingItem.set(null);
    this.isListModalOpen.set(true);
  }

  openListEdit(negotiation: Negotiation): void {
    this.listEditingItem.set(negotiation);
    this.isListModalOpen.set(true);
  }

  closeListModal(): void {
    this.isListModalOpen.set(false);
    this.listEditingItem.set(null);
  }

  openListDelete(negotiation: Negotiation): void {
    this.listDeletingItem.set(negotiation);
    this.isListDeleteOpen.set(true);
  }

  closeListDelete(): void {
    this.isListDeleteOpen.set(false);
    this.listDeletingItem.set(null);
  }

  confirmListDelete(): void {
    const item = this.listDeletingItem();
    if (!item || this.isListDeleting()) return;
    this.isListDeleting.set(true);
    this.negotiationService
      .delete(item.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isListDeleting.set(false);
          this.closeListDelete();
          toast.success('Negociação excluída com sucesso.');
          this.loadList();
        },
        error: () => {
          this.isListDeleting.set(false);
          toast.error('Não foi possível excluir a negociação.');
        },
      });
  }

  handleListFormSaved(_item: Negotiation): void {
    this.closeListModal();
    toast.success('Negociação salva com sucesso.');
    this.loadList();
    this.loadKanban();
  }

  loadListPage(page: number): void {
    this.listMeta.update((meta) => ({ ...meta, current_page: page }));
    this.loadList();
  }

  openKanbanCreate(): void {
    this.kanbanEditingItem.set(null);
    this.isKanbanModalOpen.set(true);
  }

  openKanbanEdit(negotiation: Negotiation): void {
    this.kanbanEditingItem.set(negotiation);
    this.isKanbanModalOpen.set(true);
  }

  closeKanbanModal(): void {
    this.isKanbanModalOpen.set(false);
    this.kanbanEditingItem.set(null);
  }

  handleKanbanFormSaved(_item: Negotiation): void {
    this.closeKanbanModal();
    toast.success('Negociação salva com sucesso.');
    this.loadKanban();
    this.loadList();
  }

  onKanbanDrop(event: CdkDragDrop<Negotiation[]>, targetStep: NegotiationKanbanStep): void {
    if (!event.previousContainer || !event.container) return;

    const steps = this.kanbanSteps().map((step) => ({
      ...step,
      negotiations: [...(step.negotiations || [])],
    }));

    const sourceStep = steps.find(
      (step) => this.getDropListId(step) === event.previousContainer.id,
    );
    const destinationStep = steps.find((step) => this.getDropListId(step) === event.container.id);

    if (!sourceStep || !destinationStep) return;

    const sourceList = sourceStep.negotiations || [];
    const destinationList = destinationStep.negotiations || [];

    if (event.previousContainer === event.container) {
      moveItemInArray(destinationList, event.previousIndex, event.currentIndex);
    } else {
      transferArrayItem(sourceList, destinationList, event.previousIndex, event.currentIndex);
    }

    const moved = event.item.data as Negotiation;
    const position = event.currentIndex + 1;

    moved.step_id = targetStep.id;
    destinationStep.negotiations = destinationList.map((item) => ({
      ...item,
      step_id: destinationStep.id,
    }));

    if (sourceStep.id !== destinationStep.id) {
      sourceStep.negotiations = sourceList;
    }

    this.kanbanSteps.set(steps);
    this.isKanbanMoving.set(true);

    this.negotiationService
      .move(moved.id, targetStep.id, position)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isKanbanMoving.set(false);
          // Recarrega apenas as colunas afetadas para evitar reload completo do kanban.
          const affectedStepIds = new Set<string>();
          affectedStepIds.add(String(sourceStep.id));
          affectedStepIds.add(String(destinationStep.id));
          affectedStepIds.forEach((stepId) => this.reloadKanbanStep(stepId));
        },
        error: () => {
          this.isKanbanMoving.set(false);
          this.showError('Não foi possível mover a negociação.');
          this.loadKanban();
        },
      });
  }

  getDropListId(step: NegotiationKanbanStep): string {
    return `step-${step.id}`;
  }

  markAsWon(negotiation: Negotiation): void {
    this.negotiationService
      .markAsWon(negotiation.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.showSuccess('Negociação marcada como ganha!');
          this.loadList();
          this.loadKanban();
        },
        error: () => this.showError('Não foi possível atualizar a negociação.'),
      });
  }

  markAsLost(negotiation: Negotiation): void {
    this.negotiationService
      .markAsLost(negotiation.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.showSuccess('Negociação marcada como perdida!');
          this.loadList();
          this.loadKanban();
        },
        error: () => this.showError('Não foi possível atualizar a negociação.'),
      });
  }

  getStatusBadgeClass(status: NegotiationStatus): string {
    switch (status) {
      case 'won':
        return 'bg-success/10 text-success border-success/30';
      case 'lost':
        return 'bg-danger/10 text-danger border-danger/30';
      default:
        return 'bg-warning/10 text-warning border-warning/30';
    }
  }

  getStatusLabel(status: NegotiationStatus): string {
    switch (status) {
      case 'won':
        return 'Ganha';
      case 'lost':
        return 'Perdida';
      default:
        return 'Aberta';
    }
  }

  getStepTotal(step: NegotiationKanbanStep): number {
    // Prioriza total_value do servidor (soma de todos os registros da etapa,
    // independente da paginação) para exibir o valor real da coluna.
    if (step.total_value !== undefined) {
      return step.total_value;
    }
    return (step.negotiations || []).reduce(
      (sum, negotiation) => sum + (negotiation.value || 0),
      0,
    );
  }

  formatDate(value?: string): string {
    return formatDate(value);
  }

  getStepHeaderBackground(step: NegotiationKanbanStep): string {
    const color = (step.color ?? '').trim();
    if (!color) {
      return '';
    }

    // Suporta #RGB e #RRGGBB para gerar um fundo bem sutil.
    const hex = color.replace('#', '');
    if (!/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/.test(hex)) {
      return '';
    }

    const normalized =
      hex.length === 3
        ? hex
            .split('')
            .map((char) => char + char)
            .join('')
        : hex;

    const r = Number.parseInt(normalized.slice(0, 2), 16);
    const g = Number.parseInt(normalized.slice(2, 4), 16);
    const b = Number.parseInt(normalized.slice(4, 6), 16);

    return `rgba(${r}, ${g}, ${b}, 0.09)`;
  }

  /** Retorna true se a etapa está carregando mais negociações. */
  isStepLoading(stepId: string | number): boolean {
    return this.kanbanStepLoading()[String(stepId)] ?? false;
  }

  /**
   * Carrega a próxima página de negociações de uma etapa via cursor pagination
   * e adiciona ao final da lista existente (infinite scroll / "Carregar mais").
   */
  loadMoreStep(step: NegotiationKanbanStep): void {
    if (!step.has_more || !step.next_cursor || this.isStepLoading(String(step.id))) return;

    const funnelId = this.normalizeId(this.funnelId());
    if (!funnelId) return;

    this.kanbanStepLoading.update((state) => ({ ...state, [String(step.id)]: true }));

    const filters = this.getCurrentFilters();

    this.negotiationService
      .kanbanStep(String(step.id), String(funnelId), step.next_cursor, {
        status: filters.status,
        search: filters.search,
        step_id: filters.step_id,
        crm_company_id: filters.crm_company_id,
        contact_id: filters.contact_id,
        user_id: filters.user_id,
        date_from: filters.date_from,
        date_to: filters.date_to,
        amount_min: filters.amount_min,
        amount_max: filters.amount_max,
        expected_close_from: filters.expected_close_from,
        expected_close_to: filters.expected_close_to,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response: { data: KanbanStepPage }) => {
          this.kanbanStepLoading.update((state) => ({ ...state, [String(step.id)]: false }));
          this.kanbanSteps.update((steps) =>
            steps.map((s) => {
              if (String(s.id) !== String(step.id)) return s;
              return {
                ...s,
                negotiations: [...(s.negotiations ?? []), ...response.data.negotiations],
                has_more: response.data.has_more,
                next_cursor: response.data.next_cursor,
              };
            }),
          );
        },
        error: () => {
          this.kanbanStepLoading.update((state) => ({ ...state, [String(step.id)]: false }));
          this.showError('Não foi possível carregar mais negociações.');
        },
      });
  }

  /** Loads negotiations for the list view */
  private loadList(): void {
    this.isListLoading.set(true);
    const filters = this.getCurrentFilters();

    this.negotiationService
      .list({
        ...filters,
        page: this.listMeta().current_page,
        per_page: this.listMeta().per_page,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.negotiations.set(response.data);
          this.listMeta.set(response.meta);
          this.isListLoading.set(false);
        },
        error: () => {
          this.isListLoading.set(false);
          this.showError('Não foi possível carregar as negociações.');
        },
      });
  }

  /**
   * Recarrega a primeira página de uma etapa específica do kanban sem afetar as demais colunas.
   * Utilizado após move/drop para atualizar apenas as colunas fonte e destino.
   */
  private reloadKanbanStep(stepId: string): void {
    const funnelId = this.normalizeId(this.funnelId());
    if (!funnelId) return;

    const filters = this.getCurrentFilters();

    this.negotiationService
      .kanbanStep(stepId, String(funnelId), null, {
        status: filters.status,
        search: filters.search,
        step_id: filters.step_id,
        crm_company_id: filters.crm_company_id,
        contact_id: filters.contact_id,
        user_id: filters.user_id,
        date_from: filters.date_from,
        date_to: filters.date_to,
        amount_min: filters.amount_min,
        amount_max: filters.amount_max,
        expected_close_from: filters.expected_close_from,
        expected_close_to: filters.expected_close_to,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response: { data: KanbanStepPage }) => {
          this.kanbanSteps.update((steps) =>
            steps.map((s) => {
              if (String(s.id) !== stepId) return s;
              return {
                ...s,
                negotiations: response.data.negotiations,
                has_more: response.data.has_more,
                next_cursor: response.data.next_cursor,
              };
            }),
          );
        },
        error: () => {
          // Em caso de falha no reload parcial, faz reload completo como fallback.
          this.loadKanban();
        },
      });
  }

  private loadFunnels(): void {
    this.funnelService
      .all()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const funnels = response.data?.funnels ?? [];
          this.funnels.set(funnels);
          if (!this.funnelId() && funnels.length) {
            const defaultFunnel = funnels.find((funnel) => funnel.is_default) ?? funnels[0];
            this.funnelId.set(defaultFunnel.id);
          }

          if (this.viewMode() === 'kanban') {
            this.loadKanban();
          }
        },
        error: () => this.showError('Não foi possível carregar os funis.'),
      });
  }

  private loadKanban(): void {
    const funnelId = this.normalizeId(this.funnelId());
    if (!funnelId) {
      this.kanbanSteps.set([]);
      return;
    }

    this.isKanbanLoading.set(true);
    const filters = this.getCurrentFilters();

    this.negotiationService
      .kanban(funnelId, {
        status: filters.status,
        search: filters.search,
        step_id: filters.step_id,
        crm_company_id: filters.crm_company_id,
        contact_id: filters.contact_id,
        user_id: filters.user_id,
        date_from: filters.date_from,
        date_to: filters.date_to,
        amount_min: filters.amount_min,
        amount_max: filters.amount_max,
        expected_close_from: filters.expected_close_from,
        expected_close_to: filters.expected_close_to,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const steps = (response.data?.steps ?? []).map((step) => ({
            ...step,
            negotiations: step.negotiations ?? [],
          }));
          this.kanbanSteps.set(steps);
          this.isKanbanLoading.set(false);
        },
        error: () => {
          this.isKanbanLoading.set(false);
          this.showError('Não foi possível carregar o Kanban.');
        },
      });
  }

  private loadUsers(): void {
    this.userService
      .list({ is_active: true, per_page: 100 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.users.set(response.data ?? []),
        error: () => this.users.set([]),
      });
  }

  private loadContacts(): void {
    this.contactService
      .list({ per_page: 100, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.contacts.set(response.data),
        error: () => this.showError('Não foi possível carregar os contatos.'),
      });
  }

  private loadCompanies(): void {
    this.crmCompanyService
      .list({ per_page: 100, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.companies.set(response.data),
        error: () => this.showError('Não foi possível carregar as empresas.'),
      });
  }

  private showError(message: string): void {
    toast.error(message);
  }

  private showSuccess(message: string): void {
    toast.success(message);
  }

  private normalizeId(value: string | number | null): string | number | null {
    if (value === null || value === '') return null;
    if (typeof value === 'string') {
      const numeric = Number(value);
      return Number.isNaN(numeric) ? value : numeric;
    }
    return value;
  }

  private getCurrentFilters(): NegotiationFilters {
    return {
      search: this.searchControl.value.trim() || undefined,
      status:
        this.negotiationStatusControl.value === 'all'
          ? undefined
          : this.negotiationStatusControl.value,
      funnel_id: this.normalizeId(this.funnelSelectControl.value),
      step_id: this.normalizeId(this.stepFilterControl.value),
      crm_company_id: this.normalizeId(this.companyFilterControl.value),
      contact_id: this.normalizeId(this.contactFilterControl.value),
      user_id: this.normalizeId(this.userFilterControl.value),
      date_from: this.dateFromControl.value || undefined,
      date_to: this.dateToControl.value || undefined,
      amount_min: this.amountMinControl.value ?? undefined,
      amount_max: this.amountMaxControl.value ?? undefined,
      expected_close_from: this.expectedCloseFromControl.value || undefined,
      expected_close_to: this.expectedCloseToControl.value || undefined,
    };
  }

  private applyCurrentFilters(): void {
    this.syncFiltersToQueryParams();
    if (this.viewMode() === 'list') {
      this.listMeta.update((meta) => ({ ...meta, current_page: 1 }));
    }

    this.reloadCurrentView();
  }

  private reloadCurrentView(): void {
    if (this.viewMode() === 'kanban') {
      this.loadKanban();
      return;
    }

    this.loadList();
  }

  private clearAllFilters(): void {
    this.searchControl.setValue('');
    this.funnelSelectControl.setValue(null);
    this.stepFilterControl.setValue(null);
    this.negotiationStatusControl.setValue('all');
    this.userFilterControl.setValue(null);
    this.dateFromControl.setValue(null);
    this.dateToControl.setValue(null);
    this.companyFilterControl.setValue(null);
    this.contactFilterControl.setValue(null);
    this.amountMinControl.setValue(null);
    this.amountMaxControl.setValue(null);
    this.expectedCloseFromControl.setValue(null);
    this.expectedCloseToControl.setValue(null);
    this.applyCurrentFilters();
  }

  private hydrateFiltersFromQueryParams(): void {
    const query = this.route.snapshot.queryParamMap;

    this.searchControl.setValue(query.get('search') ?? '', { emitEvent: false });
    this.funnelSelectControl.setValue(query.get('funnel_id'), { emitEvent: false });
    this.stepFilterControl.setValue(query.get('step_id'), { emitEvent: false });
    this.negotiationStatusControl.setValue(
      (query.get('status') as NegotiationStatusFilter) ?? 'all',
      {
        emitEvent: false,
      },
    );
    this.userFilterControl.setValue(query.get('user_id'), { emitEvent: false });
    this.dateFromControl.setValue(query.get('date_from'), { emitEvent: false });
    this.dateToControl.setValue(query.get('date_to'), { emitEvent: false });
    this.companyFilterControl.setValue(query.get('crm_company_id'), { emitEvent: false });
    this.contactFilterControl.setValue(query.get('contact_id'), { emitEvent: false });
    this.amountMinControl.setValue(
      query.get('amount_min') ? Number(query.get('amount_min')) : null,
      {
        emitEvent: false,
      },
    );
    this.amountMaxControl.setValue(
      query.get('amount_max') ? Number(query.get('amount_max')) : null,
      {
        emitEvent: false,
      },
    );
    this.expectedCloseFromControl.setValue(query.get('expected_close_from'), { emitEvent: false });
    this.expectedCloseToControl.setValue(query.get('expected_close_to'), { emitEvent: false });

    this.funnelId.set(this.normalizeId(this.funnelSelectControl.value));
  }

  private syncFiltersToQueryParams(): void {
    const filters = this.getCurrentFilters();

    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: this.buildFilterQueryParams(filters),
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

  private resetFilterByKey(key: string): boolean {
    switch (key) {
      case 'search':
        this.searchControl.setValue('');
        return true;
      case 'funnel_id':
        this.funnelSelectControl.setValue(null);
        this.stepFilterControl.setValue(null);
        return true;
      case 'step_id':
        this.stepFilterControl.setValue(null);
        return true;
      case 'status':
        this.negotiationStatusControl.setValue('all');
        return true;
      case 'user_id':
        this.userFilterControl.setValue(null);
        return true;
      case 'period':
        this.dateFromControl.setValue(null);
        this.dateToControl.setValue(null);
        return true;
      case 'crm_company_id':
        this.companyFilterControl.setValue(null);
        return true;
      case 'contact_id':
        this.contactFilterControl.setValue(null);
        return true;
      case 'amount':
        this.amountMinControl.setValue(null);
        this.amountMaxControl.setValue(null);
        return true;
      case 'expected_close':
        this.expectedCloseFromControl.setValue(null);
        this.expectedCloseToControl.setValue(null);
        return true;
      default:
        return false;
    }
  }

  private buildFilterQueryParams(
    filters: NegotiationFilters,
  ): Record<string, string | number | null> {
    return {
      search: filters.search ?? null,
      status: filters.status ?? null,
      funnel_id: filters.funnel_id ? String(filters.funnel_id) : null,
      step_id: filters.step_id ? String(filters.step_id) : null,
      crm_company_id: filters.crm_company_id ? String(filters.crm_company_id) : null,
      contact_id: filters.contact_id ? String(filters.contact_id) : null,
      user_id: filters.user_id ? String(filters.user_id) : null,
      date_from: filters.date_from ?? null,
      date_to: filters.date_to ?? null,
      amount_min: filters.amount_min ?? null,
      amount_max: filters.amount_max ?? null,
      expected_close_from: filters.expected_close_from ?? null,
      expected_close_to: filters.expected_close_to ?? null,
    };
  }
}
