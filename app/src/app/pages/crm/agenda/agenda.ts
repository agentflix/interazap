import { CommonModule, DatePipe } from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { FormControl } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime, distinctUntilChanged, map, startWith } from 'rxjs/operators';
import { type Observable, merge } from 'rxjs';
import {
  type CalendarOptions,
  type EventApi,
  type EventClickArg,
  type EventInput,
} from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin, { type DateClickArg } from '@fullcalendar/interaction';
import ptBrLocale from '@fullcalendar/core/locales/pt-br';
import { LucideAngularModule } from 'lucide-angular';
import { CrudPageComponent } from '@shared/components/crud-page';
import {
  type CrudActionDef,
  type CrudColumnDef,
  type CrudConfig,
  type CrudItemResponse,
  type CrudPaginatedResponse,
  type CrudService,
} from '@shared/components/crud-page/models';
import { AfDrawerComponent } from '@shared/components/drawer/drawer';
import {
  ButtonComponent,
  ButtonGroupComponent,
  LoadingButtonComponent,
} from '@shared/components/buttons';
import { PageTitle } from '@shared/components/page-title/page-title';
import {
  SearchInputComponent,
  type SelectOption,
  SelectInputComponent,
  TextInputComponent,
} from '@shared/components/inputs';
import { AfStatusBadgeComponent } from '@shared/components/status-badge/status-badge';
import {
  type Event as CRMEvent,
  type EventFilters,
  type EventRecurrence,
  type EventStatus,
  type EventType,
  EventService,
} from 'src/app/core/services/event.service';
import { type Contact } from 'src/app/core/models/contact.model';
import { ContactService } from 'src/app/core/services/contact.service';
import { type CRMCompany, CRMCompanyService } from 'src/app/core/services/crm-company.service';
import { type Negotiation, NegotiationService } from 'src/app/core/services/negotiation.service';
import { type User, UserService } from 'src/app/core/services/user.service';
import { AgendaFormComponent } from './components/agenda-form/agenda-form';
import { AgendaKpiCardsComponent } from './components/agenda-kpi-cards/agenda-kpi-cards';
import { AgendaDayViewComponent } from './components/agenda-day-view/agenda-day-view';
import { AgendaWeekViewComponent } from './components/agenda-week-view/agenda-week-view';
import { AgendaMonthViewComponent } from './components/agenda-month-view/agenda-month-view';
import { AgendaEventEditorComponent } from './components/agenda-event-editor/agenda-event-editor';
import { type AgendaActiveFilterChip } from './components/agenda-filter-bar/agenda-filter-bar';
import { type AfReportFilterPayload } from '@shared/components/report-filters/report-filters';
import { type AfReportExportPayload } from '@shared/components/report-export/report-export';

type ViewMode = 'list' | 'calendar';
type CalendarDisplayMode = 'month' | 'week' | 'day';
type ToggleFilterValue = 'all' | 'yes' | 'no';
type LinkableTypeValue = 'all' | 'deal' | 'contact' | 'company';

interface CalendarFilterSnapshot {
  search: string;
  type: EventType | 'all';
  status: EventStatus | 'all';
  userId: string;
  startDate: string;
  endDate: string;
  participantId: string;
  linkableType: LinkableTypeValue;
  linkableId: string;
  isAllDay: ToggleFilterValue;
  recurrence: EventRecurrence | 'all';
  hasReminders: ToggleFilterValue;
  location: string;
}

@Component({
  selector: 'app-agenda',
  standalone: true,
  imports: [
    CommonModule,
    DatePipe,
    LucideAngularModule,
    CrudPageComponent,
    AgendaFormComponent,
    AgendaKpiCardsComponent,
    AgendaDayViewComponent,
    AgendaWeekViewComponent,
    AgendaMonthViewComponent,
    AgendaEventEditorComponent,
    AfDrawerComponent,
    ButtonComponent,
    ButtonGroupComponent,
    LoadingButtonComponent,
    PageTitle,
    SearchInputComponent,
    SelectInputComponent,
    TextInputComponent,
    AfStatusBadgeComponent,
/**
 * Agenda page component for the Crm module.
 * @selector app-agenda
 */
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agenda.html',
})
export class Agenda {
  private readonly eventService = inject(EventService);
  private readonly userService = inject(UserService);
  private readonly contactService = inject(ContactService);
  private readonly crmCompanyService = inject(CRMCompanyService);
  private readonly negotiationService = inject(NegotiationService);
  private readonly destroyRef = inject(DestroyRef);

  // --- State ---
  readonly viewMode = signal<ViewMode>('calendar');
  readonly calendarDisplayMode = signal<CalendarDisplayMode>('month');
  readonly allEvents = signal<CRMEvent[]>([]);
  readonly isCalendarModalOpen = signal(false);
  readonly isCalendarFiltersModalOpen = signal(false);
  readonly calendarEditingItem = signal<CRMEvent | null>(null);
  readonly calendarInitialData = signal<Partial<CRMEvent> | null>(null);
  private calendarFiltersSnapshot: CalendarFilterSnapshot | null = null;

  readonly users = signal<User[]>([]);
  readonly contacts = signal<Contact[]>([]);
  readonly companies = signal<CRMCompany[]>([]);
  readonly negotiations = signal<Negotiation[]>([]);

  // --- Form Controls ---
  readonly calendarSearchControl = new FormControl('', { nonNullable: true });
  readonly typeFilterControl = new FormControl<EventType | 'all'>('all', { nonNullable: true });
  readonly statusFilterControl = new FormControl<EventStatus | 'all'>('all', { nonNullable: true });
  readonly userIdFilterControl = new FormControl('', { nonNullable: true });
  readonly startDateFilterControl = new FormControl('', { nonNullable: true });
  readonly endDateFilterControl = new FormControl('', { nonNullable: true });
  readonly participantIdFilterControl = new FormControl('', { nonNullable: true });
  readonly linkableTypeFilterControl = new FormControl<LinkableTypeValue>('all', {
    nonNullable: true,
  });
  readonly linkableIdFilterControl = new FormControl('', { nonNullable: true });
  readonly isAllDayFilterControl = new FormControl<ToggleFilterValue>('all', { nonNullable: true });
  readonly recurrenceFilterControl = new FormControl<EventRecurrence | 'all'>('all', {
    nonNullable: true,
  });
  readonly hasRemindersFilterControl = new FormControl<ToggleFilterValue>('all', {
    nonNullable: true,
  });
  readonly locationFilterControl = new FormControl('', { nonNullable: true });
  readonly dropRemoveControl = new FormControl(false, { nonNullable: true });

  // --- ViewChild refs ---
  readonly crudPage = viewChild<CrudPageComponent<CRMEvent>>('crudPage');
  readonly agendaForm = viewChild<AgendaFormComponent>('agendaForm');
  readonly monthView = viewChild<AgendaMonthViewComponent>('monthView');
  readonly weekView = viewChild<AgendaWeekViewComponent>('weekView');
  readonly dayView = viewChild<AgendaDayViewComponent>('dayView');

  // --- Static options ---
  readonly statusOptions: { id: EventStatus; label: string }[] = [
    { id: 'scheduled', label: 'Agendado' },
    { id: 'completed', label: 'Concluído' },
    { id: 'cancelled', label: 'Cancelado' },
  ];

  readonly typeOptions: { id: EventType; label: string }[] = [
    { id: 'meeting', label: 'Reunião' },
    { id: 'call', label: 'Ligação' },
    { id: 'task', label: 'Tarefa' },
    { id: 'deadline', label: 'Prazo' },
    { id: 'reminder', label: 'Lembrete' },
    { id: 'other', label: 'Outro' },
  ];

  readonly filterTypeOptions: SelectOption[] = [
    { label: 'Todos os tipos', value: 'all' },
    ...this.typeOptions.map((o) => ({ label: o.label, value: o.id })),
  ];

  readonly filterStatusOptions: SelectOption[] = [
    { label: 'Todos os status', value: 'all' },
    ...this.statusOptions.map((o) => ({ label: o.label, value: o.id })),
  ];

  readonly linkableTypeOptions: SelectOption[] = [
    { label: 'Todos os vínculos', value: 'all' },
    { label: 'Negociação', value: 'deal' },
    { label: 'Contato', value: 'contact' },
    { label: 'Empresa', value: 'company' },
  ];

  readonly isAllDayOptions: SelectOption[] = [
    { label: 'Dia inteiro: todos', value: 'all' },
    { label: 'Somente dia inteiro', value: 'yes' },
    { label: 'Sem dia inteiro', value: 'no' },
  ];

  readonly recurrenceOptions: SelectOption[] = [
    { label: 'Recorrência: todas', value: 'all' },
    { label: 'Sem recorrência', value: 'none' },
    { label: 'Diária', value: 'daily' },
    { label: 'Semanal', value: 'weekly' },
    { label: 'Mensal', value: 'monthly' },
    { label: 'Anual', value: 'yearly' },
  ];

  readonly hasRemindersOptions: SelectOption[] = [
    { label: 'Lembretes: todos', value: 'all' },
    { label: 'Com lembretes', value: 'yes' },
    { label: 'Sem lembretes', value: 'no' },
  ];

  // --- Computed options ---
  readonly responsibleSelectOptions = computed((): SelectOption[] => [
    { label: 'Todos os responsáveis', value: '' },
    ...this.users().map((u) => ({ label: u.name, value: u.id })),
  ]);

  readonly participantSelectOptions = computed((): SelectOption[] => [
    { label: 'Todos os participantes', value: '' },
    ...this.users().map((u) => ({ label: u.name, value: u.id })),
  ]);

  readonly linkableEntitySelectOptions = computed((): SelectOption[] => {
    const lt = this.linkableTypeFilterControl.value;
    if (lt === 'deal') {
      return [
        { label: 'Todas as negociações', value: '' },
        ...this.negotiations().map((n) => ({ label: n.title, value: String(n.id) })),
      ];
    }
    if (lt === 'contact') {
      return [
        { label: 'Todos os contatos', value: '' },
        ...this.contacts().map((c) => ({ label: c.name, value: String(c.id) })),
      ];
    }
    if (lt === 'company') {
      return [
        { label: 'Todas as empresas', value: '' },
        ...this.companies().map((c) => ({ label: c.name, value: String(c.id) })),
      ];
    }
    return [{ label: 'Selecione o vínculo', value: '' }];
  });

  readonly activeFilterChips = computed((): AgendaActiveFilterChip[] => {
    const chips = this.getBaseFilterChips();
    if (this.participantIdFilterControl.value.length > 0) {
      chips.push({ key: 'participantId', label: 'Participante' });
    }
    if (this.linkableTypeFilterControl.value !== 'all') {
      chips.push({ key: 'linkableType', label: 'Vínculo' });
    }
    if (this.isAllDayFilterControl.value !== 'all') {
      chips.push({ key: 'isAllDay', label: 'Dia inteiro' });
    }
    if (this.recurrenceFilterControl.value !== 'all') {
      chips.push({ key: 'recurrence', label: 'Recorrência' });
    }
    if (this.hasRemindersFilterControl.value !== 'all') {
      chips.push({ key: 'hasReminders', label: 'Lembretes' });
    }
    const location = this.locationFilterControl.value.trim();
    if (location.length > 0) {
      chips.push({ key: 'location', label: `Local: ${location}` });
    }
    return chips;
  });

  readonly advancedFilterCount = computed((): number => {
    return this.activeFilterChips().length;
  });

  // --- CRUD (list view) ---
  readonly crudService: CrudService<CRMEvent> = {
    list: (params) =>
      this.eventService
        .list(
          this.buildFilters({
            page: params.page,
            perPage: params.per_page,
            search: this.calendarSearchControl.value,
          }),
        )
        .pipe(map((r) => ({ data: r.data, meta: r.meta }))) as Observable<
        CrudPaginatedResponse<CRMEvent>
      >,
    find: (id) =>
      this.eventService
        .list({ per_page: 200 })
        .pipe(
          map((r) => ({ data: r.data.find((i) => String(i.id) === String(id)) as CRMEvent })),
        ) as Observable<CrudItemResponse<CRMEvent>>,
    create: (data) =>
      this.eventService.create(data).pipe(map((r) => ({ data: r }))) as Observable<
        CrudItemResponse<CRMEvent>
      >,
    update: (id, data) =>
      this.eventService.update(String(id), data).pipe(map((r) => ({ data: r }))) as Observable<
        CrudItemResponse<CRMEvent>
      >,
    delete: (id) => this.eventService.delete(String(id)),
  };

  readonly columns: CrudColumnDef[] = [
    { field: 'title', label: 'Evento', type: 'text' },
    { field: 'type', label: 'Tipo' },
    { field: 'starts_at', label: 'Início' },
    { field: 'ends_at', label: 'Término' },
    { field: 'status', label: 'Status' },
  ];

  readonly actions: CrudActionDef[] = [
    { type: 'edit', icon: 'lucidePencil', label: 'Editar', variant: 'ghost' },
    { type: 'delete', icon: 'lucideTrash2', label: 'Excluir', variant: 'danger' },
  ];

  readonly crudConfig: CrudConfig = {
    labels: {
      singular: 'Evento',
      plural: 'Agenda',
      createButton: 'Novo Evento',
      modalCreate: 'Novo Evento',
      modalEdit: 'Editar evento',
    },
    subtitle: 'CRM',
    searchPlaceholder: 'Buscar eventos...',
    emptyState: {
      icon: 'lucideCalendar',
      title: 'Nenhum evento encontrado',
      description: 'Comece adicionando seu primeiro evento.',
    },
    enableSelection: true,
    enableSearch: true,
    enableFilters: true,
    enableBulkDelete: true,
  };

  // --- Calendar Options ---
  calendarOptions: CalendarOptions = {
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
    initialView: 'dayGridMonth',
    locale: 'pt-br',
    locales: [ptBrLocale],
    themeSystem: 'standard',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
    },
    editable: true,
    droppable: true,
    selectable: true,
    events: [],
    dateClick: this.handleDateClick.bind(this),
    eventClick: this.handleEventClick.bind(this),
    eventDrop: this.handleEventDrop.bind(this),
    drop: (info) => {
      if (this.dropRemoveControl.value && info.draggedEl?.parentNode) {
        info.draggedEl.parentNode.removeChild(info.draggedEl);
      }
      const type = info.draggedEl.getAttribute('data-type') as EventType | null;
      this.openCalendarCreate(info.date, type || undefined);
    },
    eventClassNames: (arg) => {
      const classNames = arg.event.extendedProps?.['classNames'];
      return Array.isArray(classNames) ? classNames : [];
    },
  };

  constructor() {
    this.loadFilterDependencies();
    this.watchFilterChanges();
    this.watchCalendarSearch();

    this.linkableTypeFilterControl.valueChanges
      .pipe(startWith(this.linkableTypeFilterControl.value), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.linkableIdFilterControl.setValue(''));
  }

  // --- View management ---
  setView(view: ViewMode): void {
    this.viewMode.set(view);
    if (view === 'calendar') {
      this.loadCalendarEvents();
      return;
    }
    const crud = this.crudPage();
    if (crud) {
      crud.goToPage(1);
      crud.reloadList();
    }
  }

  setCalendarDisplayMode(mode: CalendarDisplayMode): void {
    this.calendarDisplayMode.set(mode);
  }

  // --- Calendar events ---
  loadCalendarEvents(): void {
    this.eventService
      .list(this.buildFilters({ perPage: 200, search: this.calendarSearchControl.value }))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.updateCalendarEvents(response.data ?? []),
        error: () => {
          /* silently fail */
        },
      });
  }

  private updateCalendarEvents(items: CRMEvent[]): void {
    this.allEvents.set(items);

    const calendarEvents: EventInput[] = items.map((item) => ({
      id: item.id,
      title: item.title,
      start: item.starts_at,
      end: item.ends_at ?? undefined,
      allDay: item.is_all_day,
      classNames: [this.getTypeClass(item.type)],
      extendedProps: { ...item },
    }));

    this.calendarOptions = { ...this.calendarOptions, events: calendarEvents };

    const api = this.getActiveCalendarApi();
    if (api !== undefined && api !== null) {
      api.removeAllEvents();
      api.addEventSource(calendarEvents);
    }
  }

  getTypeClass(type: EventType): string {
    const map: Record<EventType, string> = {
      meeting: '!bg-info-500 !border-info-500 !text-white',
      call: '!bg-primary-600 !border-primary-600 !text-white',
      task: '!bg-success-500 !border-success-500 !text-white',
      deadline: '!bg-danger-500 !border-danger-500 !text-white',
      reminder: '!bg-primary-400 !border-primary-400 !text-white',
      other: '!bg-neutral-500 !border-neutral-500 !text-white',
    };
    return map[type] ?? '!bg-neutral-500 !border-neutral-500 !text-white';
  }

  // --- Calendar handlers ---
  handleDateClick(arg: DateClickArg): void {
    this.openCalendarCreate(new Date(arg.date));
  }

  handleEventClick(arg: EventClickArg): void {
    const found = arg.event.extendedProps as CRMEvent | undefined;
    if (found) {
      this.openCalendarEdit(found);
      return;
    }
    this.eventService
      .list(this.buildFilters({ perPage: 200 }))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const item = response.data.find((e) => e.id === arg.event.id);
          if (item) this.openCalendarEdit(item);
        },
      });
  }

  handleEventDrop(arg: { event: EventApi; revert: () => void }): void {
    const payload: Partial<CRMEvent> = {
      starts_at: arg.event.start?.toISOString() ?? '',
      ends_at: arg.event.end ? arg.event.end.toISOString() : undefined,
      is_all_day: arg.event.allDay,
    };
    this.eventService
      .update(arg.event.id, payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.loadCalendarEvents(),
        error: () => arg.revert(),
      });
  }

  // --- Modal management ---
  openCalendarCreate(date?: Date, type: EventType = 'meeting'): void {
    this.calendarEditingItem.set(null);
    this.calendarInitialData.set({
      type,
      starts_at: date ? this.toInputDateTime(date) : this.toInputDateTime(new Date()),
    });
    this.isCalendarModalOpen.set(true);
  }

  openCalendarEdit(item: CRMEvent): void {
    this.calendarInitialData.set(null);
    this.calendarEditingItem.set(item);
    this.isCalendarModalOpen.set(true);
  }

  closeCalendarModal(): void {
    this.isCalendarModalOpen.set(false);
    this.calendarEditingItem.set(null);
    this.calendarInitialData.set(null);
  }

  handleCalendarSaved(_item: CRMEvent): void {
    this.closeCalendarModal();
    this.loadCalendarEvents();
  }

  handleFormSaved(item: CRMEvent): void {
    this.crudPage()?.handleFormSaved(item);
    this.loadCalendarEvents();
  }

  handleFormCancelled(): void {
    this.crudPage()?.closeModal();
  }

  // --- Filter management ---
  openCalendarFiltersModal(): void {
    this.calendarFiltersSnapshot = this.captureCalendarFilters();
    this.isCalendarFiltersModalOpen.set(true);
  }

  cancelCalendarFiltersModal(): void {
    if (this.calendarFiltersSnapshot) this.restoreCalendarFilters(this.calendarFiltersSnapshot);
    this.calendarFiltersSnapshot = null;
    this.isCalendarFiltersModalOpen.set(false);
  }

  applyCalendarFiltersModal(): void {
    this.calendarFiltersSnapshot = null;
    this.isCalendarFiltersModalOpen.set(false);
  }

  clearFilters(): void {
    this.calendarSearchControl.setValue('');
    this.typeFilterControl.setValue('all');
    this.statusFilterControl.setValue('all');
    this.userIdFilterControl.setValue('');
    this.startDateFilterControl.setValue('');
    this.endDateFilterControl.setValue('');
    this.participantIdFilterControl.setValue('');
    this.linkableTypeFilterControl.setValue('all');
    this.linkableIdFilterControl.setValue('');
    this.isAllDayFilterControl.setValue('all');
    this.recurrenceFilterControl.setValue('all');
    this.hasRemindersFilterControl.setValue('all');
    this.locationFilterControl.setValue('');
  }

  removeFilterChip(key: string): void {
    const resetMap: Record<string, () => void> = {
      search: () => this.calendarSearchControl.setValue(''),
      type: () => this.typeFilterControl.setValue('all'),
      status: () => this.statusFilterControl.setValue('all'),
      userId: () => this.userIdFilterControl.setValue(''),
      startDate: () => this.startDateFilterControl.setValue(''),
      endDate: () => this.endDateFilterControl.setValue(''),
      participantId: () => this.participantIdFilterControl.setValue(''),
      linkableType: () => this.linkableTypeFilterControl.setValue('all'),
      isAllDay: () => this.isAllDayFilterControl.setValue('all'),
      recurrence: () => this.recurrenceFilterControl.setValue('all'),
      hasReminders: () => this.hasRemindersFilterControl.setValue('all'),
      location: () => this.locationFilterControl.setValue(''),
    };
    resetMap[key]?.();
  }

  onFiltersApplied(payload: AfReportFilterPayload): void {
    this.startDateFilterControl.setValue(payload.startDate || '');
    this.endDateFilterControl.setValue(payload.endDate || '');
    this.statusFilterControl.setValue((payload.status as EventStatus | 'all') || 'all');
  }

  onExport(payload: AfReportExportPayload): void {
    console.warn('Export requested:', payload.format);
  }

  // --- Label helpers ---
  statusLabel(status: EventStatus): string {
    return this.statusOptions.find((o) => o.id === status)?.label ?? status;
  }

  typeLabel(type: EventType): string {
    return this.typeOptions.find((o) => o.id === type)?.label ?? type;
  }

  eventStatusToBadge(status: EventStatus): 'online' | 'offline' | 'warning' | 'error' | 'idle' {
    const map: Record<EventStatus, 'online' | 'error' | 'idle'> = {
      scheduled: 'idle',
      completed: 'online',
      cancelled: 'error',
    };
    return map[status] ?? 'offline';
  }

  // --- Private watchers ---
  private watchFilterChanges(): void {
    merge(
      this.typeFilterControl.valueChanges,
      this.statusFilterControl.valueChanges,
      this.userIdFilterControl.valueChanges,
      this.startDateFilterControl.valueChanges,
      this.endDateFilterControl.valueChanges,
      this.participantIdFilterControl.valueChanges,
      this.linkableTypeFilterControl.valueChanges,
      this.linkableIdFilterControl.valueChanges,
      this.isAllDayFilterControl.valueChanges,
      this.recurrenceFilterControl.valueChanges,
      this.hasRemindersFilterControl.valueChanges,
      this.locationFilterControl.valueChanges.pipe(debounceTime(300), distinctUntilChanged()),
    )
      .pipe(startWith(null), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        if (this.viewMode() === 'calendar') this.loadCalendarEvents();
        const crud = this.crudPage();
        if (crud) {
          crud.goToPage(1);
          crud.reloadList();
        }
      });
  }

  private watchCalendarSearch(): void {
    this.calendarSearchControl.valueChanges
      .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        if (this.viewMode() === 'calendar') this.loadCalendarEvents();
      });
  }

  private loadFilterDependencies(): void {
    this.userService
      .list({ per_page: 200, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({ next: (r) => this.users.set(r.data ?? []), error: () => this.users.set([]) });

    this.contactService
      .list({ per_page: 200, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => this.contacts.set(r.data ?? []),
        error: () => this.contacts.set([]),
      });

    this.crmCompanyService
      .all()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({ next: (c) => this.companies.set(c ?? []), error: () => this.companies.set([]) });

    this.negotiationService
      .list({ per_page: 200 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r) => this.negotiations.set(r.data ?? []),
        error: () => this.negotiations.set([]),
      });
  }

  private buildFilters(params?: {
    page?: number;
    perPage?: number;
    search?: string;
  }): EventFilters {
    const search = this.normalizeText(params?.search);
    const userId = this.normalizeText(this.userIdFilterControl.value);
    const startDate = this.normalizeText(this.startDateFilterControl.value);
    const endDate = this.normalizeText(this.endDateFilterControl.value);
    const participantId = this.normalizeText(this.participantIdFilterControl.value);
    const linkableId = this.normalizeText(this.linkableIdFilterControl.value);
    const location = this.normalizeText(this.locationFilterControl.value);

    const filters: EventFilters = {
      search,
      type: this.typeFilterControl.value === 'all' ? undefined : this.typeFilterControl.value,
      status: this.statusFilterControl.value === 'all' ? undefined : this.statusFilterControl.value,
      user_id: userId,
      start_date: startDate,
      end_date: endDate,
      participant_id: participantId,
      linkable_type:
        this.linkableTypeFilterControl.value === 'all'
          ? undefined
          : this.linkableTypeFilterControl.value,
      linkable_id: linkableId,
      recurrence:
        this.recurrenceFilterControl.value === 'all'
          ? undefined
          : this.recurrenceFilterControl.value,
      location,
      page: params?.page,
      per_page: params?.perPage,
      sort_by: 'starts_at',
      sort_dir: 'asc',
    };
    this.applyToggleBooleanFilters(filters);
    return filters;
  }

  private toInputDateTime(value: string | Date | undefined): string {
    if (value === undefined) {
      return '';
    }
    const date = typeof value === 'string' ? new Date(value) : value;
    if (Number.isNaN(date.getTime())) return '';
    const pad = (num: number): string => String(num).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }

  private getBaseFilterChips(): AgendaActiveFilterChip[] {
    const chips: AgendaActiveFilterChip[] = [];
    const search = this.calendarSearchControl.value.trim();
    if (search.length > 0) {
      chips.push({ key: 'search', label: `Busca: ${search}` });
    }
    if (this.typeFilterControl.value !== 'all') {
      chips.push({ key: 'type', label: this.typeLabel(this.typeFilterControl.value as EventType) });
    }
    if (this.statusFilterControl.value !== 'all') {
      chips.push({
        key: 'status',
        label: this.statusLabel(this.statusFilterControl.value as EventStatus),
      });
    }
    const userId = this.userIdFilterControl.value;
    if (userId.length > 0) {
      const user = this.users().find((item) => item.id === userId);
      chips.push({ key: 'userId', label: user?.name ?? 'Responsável' });
    }
    const startDate = this.startDateFilterControl.value;
    if (startDate.length > 0) {
      chips.push({ key: 'startDate', label: `De: ${startDate}` });
    }
    const endDate = this.endDateFilterControl.value;
    if (endDate.length > 0) {
      chips.push({ key: 'endDate', label: `Até: ${endDate}` });
    }
    return chips;
  }

  private normalizeText(value: string | undefined): string | undefined {
    if (value === undefined) {
      return undefined;
    }
    const trimmed = value.trim();
    return trimmed.length > 0 ? trimmed : undefined;
  }

  private applyToggleBooleanFilters(filters: EventFilters): void {
    if (this.isAllDayFilterControl.value === 'yes') {
      filters.is_all_day = true;
    } else if (this.isAllDayFilterControl.value === 'no') {
      filters.is_all_day = false;
    }

    if (this.hasRemindersFilterControl.value === 'yes') {
      filters.has_reminders = true;
    } else if (this.hasRemindersFilterControl.value === 'no') {
      filters.has_reminders = false;
    }
  }

  private captureCalendarFilters(): CalendarFilterSnapshot {
    return {
      search: this.calendarSearchControl.value,
      type: this.typeFilterControl.value,
      status: this.statusFilterControl.value,
      userId: this.userIdFilterControl.value,
      startDate: this.startDateFilterControl.value,
      endDate: this.endDateFilterControl.value,
      participantId: this.participantIdFilterControl.value,
      linkableType: this.linkableTypeFilterControl.value,
      linkableId: this.linkableIdFilterControl.value,
      isAllDay: this.isAllDayFilterControl.value,
      recurrence: this.recurrenceFilterControl.value,
      hasReminders: this.hasRemindersFilterControl.value,
      location: this.locationFilterControl.value,
    };
  }

  private restoreCalendarFilters(s: CalendarFilterSnapshot): void {
    this.calendarSearchControl.setValue(s.search);
    this.typeFilterControl.setValue(s.type);
    this.statusFilterControl.setValue(s.status);
    this.userIdFilterControl.setValue(s.userId);
    this.startDateFilterControl.setValue(s.startDate);
    this.endDateFilterControl.setValue(s.endDate);
    this.participantIdFilterControl.setValue(s.participantId);
    this.linkableTypeFilterControl.setValue(s.linkableType);
    this.linkableIdFilterControl.setValue(s.linkableId);
    this.isAllDayFilterControl.setValue(s.isAllDay);
    this.recurrenceFilterControl.setValue(s.recurrence);
    this.hasRemindersFilterControl.setValue(s.hasReminders);
    this.locationFilterControl.setValue(s.location);
  }

  private getActiveCalendarApi(): ReturnType<AgendaMonthViewComponent['getApi']> | undefined {
    if (this.calendarDisplayMode() === 'week') {
      return this.weekView()?.getApi();
    }
    if (this.calendarDisplayMode() === 'day') {
      return this.dayView()?.getApi();
    }
    return this.monthView()?.getApi();
  }
}
