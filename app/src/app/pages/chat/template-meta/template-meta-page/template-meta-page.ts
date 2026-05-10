import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
  type OnInit,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import { toast } from 'ngx-sonner';
import {
  AfAlertComponent,
  AfBadgeComponent,
  AfButtonComponent,
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfDrawerComponent,
  AfTableActionsComponent,
  AfTooltipComponent,
} from '@shared/components';
import { ButtonComponent } from '@shared/components/buttons';
import { type SelectOption, SelectInputComponent } from '@shared/components/inputs';
import { type PaginationMeta } from '@core/models/pagination.model';
import {
  type ChatMessageTemplate,
  type ChatMessageTemplateFilters,
  type ChatMessageTemplateStatus,
} from '@core/models/chat-message-template.model';
import { IntegrationService, type Integration } from '@core/services/integration.service';
import { ChatMessageTemplateService } from '../../services/chat-message-template.service';
import {
  templateStatusLabel,
  templateStatusVariant,
  type TemplateBadgeVariant,
} from '../helpers/template-status';

@Component({
  selector: 'app-chat-template-meta-page',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfBadgeComponent,
    AfButtonComponent,
    AfAlertComponent,
    AfConfirmModalComponent,
    AfTableActionsComponent,
    AfTooltipComponent,
    AfDrawerComponent,
    ButtonComponent,
    SelectInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './template-meta-page.html',
})
export class TemplateMetaPageComponent implements OnInit {
  private readonly templateService = inject(ChatMessageTemplateService);
  private readonly integrationService = inject(IntegrationService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  // ── State ───────────────────────────────────────────────────────────
  readonly items = signal<ChatMessageTemplate[]>([]);
  readonly meta = signal<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  });
  readonly isLoading = signal(true);
  readonly isSyncing = signal(false);
  readonly hasError = signal(false);
  readonly integrations = signal<Integration[]>([]);

  readonly searchTerm = signal('');
  readonly statusFilter = signal<'' | ChatMessageTemplateStatus>('');
  readonly chatInstanceFilter = signal<string>('');

  readonly toDelete = signal<ChatMessageTemplate | null>(null);
  readonly isDeleting = signal(false);

  readonly isFilterOpen = signal(false);

  readonly chatInstanceControl = new FormControl<string | number | null>('', { nonNullable: true });
  readonly statusControl = new FormControl<string | number | null>('', { nonNullable: true });

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.items().length === 0,
  );

  readonly chatInstanceOptions = computed<SelectOption[]>(() => [
    { value: '', label: 'Todos os canais' },
    ...this.integrations().map((i) => ({ value: i.id, label: i.name })),
  ]);

  readonly statusOptions: SelectOption[] = [
    { value: '', label: 'Todos os status' },
    { value: 'approved', label: 'Aprovado' },
    { value: 'pending', label: 'Em aprovação' },
    { value: 'rejected', label: 'Rejeitado' },
    { value: 'paused', label: 'Pausado' },
    { value: 'disabled', label: 'Desativado' },
  ];

  readonly canSync = computed(() => this.chatInstanceFilter().length > 0);

  readonly activeFiltersCount = computed(() => {
    let count = 0;
    if (this.chatInstanceFilter()) count++;
    if (this.statusFilter()) count++;
    return count;
  });

  ngOnInit(): void {
    this.loadIntegrations();
    this.loadTemplates();

    this.chatInstanceControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        this.chatInstanceFilter.set(value ? String(value) : '');
        this.loadTemplates(1);
      });

    this.statusControl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((value) => {
      const next = value ? (String(value) as ChatMessageTemplateStatus) : '';
      this.statusFilter.set(next);
      this.loadTemplates(1);
    });
  }

  // ── Data ────────────────────────────────────────────────────────────
  loadIntegrations(): void {
    this.integrationService
      .list({ per_page: 100 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (resp) => this.integrations.set(resp.data),
        error: () => this.integrations.set([]),
      });
  }

  loadTemplates(page = this.meta().current_page): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const filters: ChatMessageTemplateFilters = {
      page,
      per_page: this.meta().per_page,
      search: this.searchTerm() || undefined,
      status: this.statusFilter() || undefined,
      chat_instance_id: this.chatInstanceFilter() || undefined,
    };

    this.templateService
      .list(filters)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.items.set(response.data);
          this.meta.set(response.meta);
          this.isLoading.set(false);
        },
        error: () => {
          this.hasError.set(true);
          this.isLoading.set(false);
        },
      });
  }

  onSearch(value: string): void {
    this.searchTerm.set(value);
    this.loadTemplates(1);
  }

  onPageChange(page: number): void {
    this.loadTemplates(page);
  }

  // ── Filter drawer ────────────────────────────────────────────────────
  openFilter(): void {
    this.isFilterOpen.set(true);
  }

  closeFilter(): void {
    this.isFilterOpen.set(false);
  }

  applyFilter(): void {
    this.loadTemplates(1);
    this.closeFilter();
  }

  clearFilter(): void {
    this.chatInstanceControl.setValue('');
    this.statusControl.setValue('');
  }

  // ── Actions ─────────────────────────────────────────────────────────
  openCreate(): void {
    void this.router.navigate(['/chat/template-meta/new']);
  }

  openEdit(item: ChatMessageTemplate): void {
    void this.router.navigate(['/chat/template-meta', item.id, 'edit']);
  }

  openView(item: ChatMessageTemplate): void {
    void this.router.navigate(['/chat/template-meta', item.id, 'edit']);
  }

  syncNow(): void {
    const id = this.chatInstanceFilter();
    if (!id) {
      toast.warning('Selecione um canal Meta antes de sincronizar.');
      return;
    }
    this.isSyncing.set(true);
    this.templateService
      .sync(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (resp) => {
          this.isSyncing.set(false);
          toast.success(`${resp.count} template(s) sincronizado(s) com sucesso.`);
          this.loadTemplates(1);
        },
        error: () => {
          this.isSyncing.set(false);
          toast.error('Não foi possível sincronizar com a Meta.');
        },
      });
  }

  askDelete(item: ChatMessageTemplate): void {
    this.toDelete.set(item);
  }

  cancelDelete(): void {
    this.toDelete.set(null);
  }

  confirmDelete(): void {
    const target = this.toDelete();
    if (!target) {
      return;
    }
    this.isDeleting.set(true);
    this.templateService
      .delete(target.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.toDelete.set(null);
          toast.success(`Template "${target.name}" excluído.`);
          this.loadTemplates();
        },
        error: () => {
          this.isDeleting.set(false);
          toast.error('Falha ao excluir template.');
        },
      });
  }

  // ── View helpers ────────────────────────────────────────────────────
  variantOf(status: string): TemplateBadgeVariant {
    return templateStatusVariant(status);
  }

  labelOf(status: string): string {
    return templateStatusLabel(status);
  }

  formatDate(value: string | null | undefined): string {
    if (!value) return '—';
    try {
      return new Date(value).toLocaleString('pt-BR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return '—';
    }
  }

  channelName(id: string | null): string {
    if (!id) return '—';
    return this.integrations().find((i) => i.id === id)?.name ?? '—';
  }

  deleteMessage(): string {
    const target = this.toDelete();
    if (!target) return '';
    const meta = target.provider === 'meta';
    return meta
      ? `Excluir o template "${target.name}"? Ele será removido também na Meta WhatsApp.`
      : `Excluir o template "${target.name}"? Esta ação não pode ser desfeita.`;
  }
}
