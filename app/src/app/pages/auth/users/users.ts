import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime, distinctUntilChanged, forkJoin } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfAvatarComponent,
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfDrawerComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfIconButtonComponent,
  AfSelectInputComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  type AfSelectOption,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import {
  type SettingsUser,
  type SettingsUserStatus,
  SettingsUsersService,
} from '@core/services/settings-users.service';
import { SettingsUserFormComponent } from './components/settings-user-form/settings-user-form';
import { getInitials } from '@shared/utils/string.utils';

/**
 * Página de usuários — CRUD completo com seleção em massa e alternância de status dos usuários do tenant.
 */
@Component({
  selector: 'app-settings-users',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfIconButtonComponent,
    AfCheckboxInputComponent,
    AfLoadingButtonComponent,
    AfSelectInputComponent,
    AfDataTableComponent,
    AfDrawerComponent,
    AfAvatarComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    SettingsUserFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './users.html',
})
export class SettingsUsers implements OnInit {
  private readonly service = inject(SettingsUsersService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly route = inject(ActivatedRoute);

  readonly userFormRef = viewChild<SettingsUserFormComponent>('userForm');

  // ── List state ───────────────────────────────────────────────────────────────
  readonly users = signal<SettingsUser[]>([]);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isLoading = signal(false);
  readonly hasError = signal(false);
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.users().length === 0,
  );

  private currentPage = 1;
  private searchTerm = '';
  private filterStatus = signal<SettingsUserStatus>('all');
  private pendingEditId: string | null = null;

  readonly searchControl = new FormControl<string>('', { nonNullable: true });

  // ─── Filter drawer ─────────────────────────────────────────────────────────
  readonly isFilterOpen = signal(false);

  readonly activeFiltersCount = computed(() =>
    this.filterStatusControl.value !== 'all' ? 1 : 0,
  );

  /** Abre o drawer de filtros. */
  openFilter(): void { this.isFilterOpen.set(true); }
  /** Fecha o drawer de filtros. */
  closeFilter(): void { this.isFilterOpen.set(false); }

  /** Limpa todos os filtros ativos. */
  clearFilter(): void {
    this.filterStatusControl.setValue('all');
  }

  /** Aplica os filtros e fecha o drawer. */
  applyFilter(): void {
    this.closeFilter();
  }

  readonly filterStatusControl = new FormControl<string>('all', { nonNullable: true });
  readonly filterStatusOptions: AfSelectOption[] = [
    { label: 'Todos', value: 'all' },
    { label: 'Ativo', value: 'active' },
    { label: 'Inativo', value: 'inactive' },
  ];

  // ── Bulk selection ───────────────────────────────────────────────────────────
  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedUserIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedUserIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  // ── Form modal ───────────────────────────────────────────────────────────────
  readonly showFormModal = signal(false);
  readonly selectedUser = signal<SettingsUser | null>(null);
  readonly isFormSaving = computed(() => this.userFormRef()?.isSaving() ?? false);
  readonly formModalTitle = computed(() =>
    this.selectedUser() ? 'Editar usuário' : 'Novo usuário',
  );

  // ── Delete modal ─────────────────────────────────────────────────────────────
  readonly showDeleteModal = signal(false);
  readonly userToDelete = signal<SettingsUser | null>(null);
  readonly isDeleting = signal(false);
  readonly deleteModalTitle = computed(() =>
    this.userToDelete() ? 'Excluir usuário' : 'Excluir usuários',
  );
  readonly deleteMessage = computed(() => {
    const single = this.userToDelete();
    if (single) {
      return `Tem certeza que deseja excluir o usuário "${single.name}"? Esta ação não pode ser desfeita.`;
    }
    return `Tem certeza que deseja excluir os ${this.selectedCount()} usuários selecionados? Esta ação não pode ser desfeita.`;
  });
  readonly deleteConfirmLabel = computed(() =>
    this.userToDelete() ? 'Excluir' : 'Excluir selecionados',
  );

  constructor() {
    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const selectable = this.users().filter((u) => !u.is_primary_tenant_user);
        const ids = checked ? selectable.map((u) => u.id) : [];
        this.selectedUserIds.set(ids);
        selectable.forEach((u) => {
          const ctrl = this.rowSelectionControls.get(u.id);
          ctrl?.setValue(checked, { emitEvent: false });
        });
      });
  }

  ngOnInit(): void {
    this.filterStatusControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        this.filterStatus.set(value as SettingsUserStatus);
        this.currentPage = 1;
        this.loadUsers();
      });

    this.route.queryParams
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((params) => {
        const editId = params['edit'];
        if (editId) {
          this.pendingEditId = editId;
        }
      });

    this.loadUsers();
  }

  // ── Data loading ─────────────────────────────────────────────────────────────

  /** Carrega a lista paginada de usuários com filtros ativos aplicados. */
  loadUsers(): void {
    this.isLoading.set(true);
    this.hasError.set(false);
    this.service
      .list({
        search: this.searchTerm || undefined,
        status: this.filterStatus(),
        page: this.currentPage,
        per_page: 15,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const users = response.data.map((u) => this.withDerivedRole(u));
          this.users.set(users);
          this.meta.set(response.meta);
          this.syncSelectionControls(users);
          this.isLoading.set(false);

          if (this.pendingEditId) {
            const user = users.find((u) => u.id === this.pendingEditId);
            if (user) {
              this.openEdit(user);
              this.pendingEditId = null;
            }
          }
        },
        error: () => {
          this.hasError.set(true);
          this.isLoading.set(false);
        },
      });
  }

  /**
   * Filtra a listagem pelo termo de busca.
   * @param term Texto de busca
   */
  onSearch(term: string): void {
    this.searchTerm = term;
    this.currentPage = 1;
    this.loadUsers();
  }

  /**
   * Navega para a página especificada da listagem.
   * @param page Número da página
   */
  loadPage(page: number): void {
    this.currentPage = page;
    this.loadUsers();
  }

  /** Recarrega a listagem após erro. */
  retry(): void {
    this.loadUsers();
  }

  // ── CRUD actions ─────────────────────────────────────────────────────────────

  /** Abre o modal no modo de criação de novo usuário. */
  openCreate(): void {
    this.selectedUser.set(null);
    this.showFormModal.set(true);
  }

  /**
   * Abre o modal no modo de edição do usuário.
   * @param user Usuário a editar
   */
  openEdit(user: SettingsUser): void {
    this.selectedUser.set(user);
    this.showFormModal.set(true);
  }

  /**
   * Abre o modal de confirmação de exclusão individual.
   * @param user Usuário a excluir
   */
  openDelete(user: SettingsUser): void {
    this.userToDelete.set(user);
    this.showDeleteModal.set(true);
  }

  /** Abre o modal de confirmação de exclusão em massa dos usuários selecionados. */
  openBulkDelete(): void {
    this.userToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  /**
   * Fecha o modal e recarrega a lista após salvar um usuário.
   * @param user Usuário salvo
   */
  handleFormSaved(user: SettingsUser): void {
    this.showFormModal.set(false);
    this.toast.success('Usuário salvo com sucesso.');
    this.loadUsers();
    void user;
  }

  /** Fecha o modal de formulário sem salvar. */
  handleFormCancelled(): void {
    this.showFormModal.set(false);
  }

  /** Confirma e executa a exclusão individual ou em massa dos usuários selecionados. */
  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;
    const single = this.userToDelete();
    const idsToDelete = single ? [single.id] : this.selectedUserIds();
    if (idsToDelete.length === 0) return;

    this.isDeleting.set(true);
    forkJoin(idsToDelete.map((id) => this.service.delete(id)))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
          this.userToDelete.set(null);
          this.clearSelection();
          this.toast.success(
            idsToDelete.length > 1
              ? 'Usuários excluídos com sucesso.'
              : 'Usuário excluído com sucesso.',
          );
          this.loadUsers();
        },
        error: () => {
          this.isDeleting.set(false);
        },
      });
  }

  /**
   * Alterna o status ativo/inativo do usuário.
   * @param user Usuário a ter o status alternado
   */
  toggleStatus(user: SettingsUser): void {
    if (user.is_primary_tenant_user) return;
    this.service
      .toggleStatus(user.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.loadUsers(),
      });
  }

  // ── Extra table actions ───────────────────────────────────────────────────────

  /**
   * Retorna as ações extras de linha para o usuário (ativar/inativar).
   * @param user Usuário da linha
   * @returns Lista de ações ou vazio se for o usuário principal do tenant
   */
  getExtraActions(
    user: SettingsUser,
  ): { label: string; icon: string; action: () => void; variant?: string }[] {
    if (user.is_primary_tenant_user) return [];
    return [
      {
        label: user.is_active ? 'Inativar' : 'Ativar',
        icon: 'power',
        variant: user.is_active ? 'danger' : 'primary',
        action: () => this.toggleStatus(user),
      },
    ];
  }

  // ── Selection helpers ─────────────────────────────────────────────────────────

  getRowSelectionControl(id: string): FormControl<boolean> {
    if (this.rowSelectionControls.has(id)) return this.rowSelectionControls.get(id)!;
    const control = new FormControl<boolean>(false, { nonNullable: true });
    control.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((checked) => {
      const selected = new Set(this.selectedUserIds());
      if (checked) {
        selected.add(id);
      } else {
        selected.delete(id);
      }
      this.selectedUserIds.set([...selected]);
      const selectable = this.users().filter((u) => !u.is_primary_tenant_user);
      this.selectAllControl.setValue(
        selectable.length > 0 && selectable.every((u) => selected.has(u.id)),
        { emitEvent: false },
      );
    });
    this.rowSelectionControls.set(id, control);
    return control;
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────

  getInitials(name: string): string {
    return getInitials(name, 'US');
  }

  private withDerivedRole(user: SettingsUser): SettingsUser {
    const role = user.roles?.[0] ?? user.role ?? null;
    return { ...user, role };
  }

  private syncSelectionControls(users: SettingsUser[]): void {
    const validIds = new Set(users.map((u) => u.id));
    for (const id of this.rowSelectionControls.keys()) {
      if (!validIds.has(id)) this.rowSelectionControls.delete(id);
    }
    const remaining = this.selectedUserIds().filter((id) => validIds.has(id));
    if (remaining.length !== this.selectedUserIds().length) {
      this.selectedUserIds.set(remaining);
    }
  }

  private clearSelection(): void {
    this.selectedUserIds.set([]);
    this.rowSelectionControls.forEach((c) => c.setValue(false, { emitEvent: false }));
    this.selectAllControl.setValue(false, { emitEvent: false });
  }
}
