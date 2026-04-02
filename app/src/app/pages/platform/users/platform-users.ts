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
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { forkJoin } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfAvatarComponent,
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  type AfSelectOption,
  type AfCheckboxOption,
} from '@shared/components';
import { CompanyService } from '@core/services/company.service';
import { type PlatformUser, PlatformUserService } from '@core/services/platform-user.service';
import { RoleService } from '@core/services/role.service';
import { ToastService } from '@core/services/toast.service';
import { PlatformUserFormComponent } from './components/platform-user-form/platform-user-form';
import { getInitials } from '@shared/utils/string.utils';

@Component({
  selector: 'app-platform-users',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfCheckboxInputComponent,
    AfLoadingButtonComponent,
    AfDataTableComponent,
    AfAvatarComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    PlatformUserFormComponent,
/**
 * Platform users page component for the Platform module.
 * @selector app-platform-users
 */
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './platform-users.html',
})
export class PlatformUsers implements OnInit {
  private readonly userService = inject(PlatformUserService);
  private readonly companyService = inject(CompanyService);
  private readonly roleService = inject(RoleService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly userFormRef = viewChild<PlatformUserFormComponent>('userForm');

  readonly users = signal<PlatformUser[]>([]);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isLoading = signal(false);
  readonly hasError = signal(false);
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.users().length === 0,
  );

  readonly companies = signal<{ id: string | number; name: string }[]>([]);
  readonly companyOptions = computed<AfSelectOption[]>(() =>
    this.companies().map((c) => ({ label: c.name, value: String(c.id) })),
  );

  readonly roles = signal<{ id: string; name: string }[]>([]);
  readonly roleOptions = computed<readonly AfCheckboxOption[]>(() =>
    this.roles().map((r) => ({ label: r.name, value: r.name })),
  );

  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedUserIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedUserIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  readonly showFormModal = signal(false);
  readonly selectedUser = signal<PlatformUser | null>(null);
  readonly isFormSaving = computed(() => this.userFormRef()?.isSaving() ?? false);
  readonly formModalTitle = computed(() =>
    this.selectedUser() ? 'Editar usuário' : 'Novo usuário',
  );

  readonly showDeleteModal = signal(false);
  readonly userToDelete = signal<PlatformUser | null>(null);
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

  private currentPage = 1;
  private searchTerm = '';

  constructor() {
    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const selectable = this.users().filter((u) => !u.is_primary_tenant_user);
        const ids = checked ? selectable.map((u) => u.id) : [];
        this.selectedUserIds.set(ids);
        selectable.forEach((u) => {
          this.rowSelectionControls.get(u.id)?.setValue(checked, { emitEvent: false });
        });
      });
  }

  ngOnInit(): void {
    this.loadCompanies();
    this.loadRoles();
    this.loadUsers();
  }

  openCreate(): void {
    this.selectedUser.set(null);
    this.showFormModal.set(true);
  }

  openEdit(user: PlatformUser): void {
    this.selectedUser.set(user);
    this.showFormModal.set(true);
  }

  openDelete(user: PlatformUser): void {
    this.userToDelete.set(user);
    this.showDeleteModal.set(true);
  }

  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.userToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  handleFormSaved(_user: PlatformUser): void {
    this.showFormModal.set(false);
    this.selectedUser.set(null);
    this.toast.success('Usuário salvo com sucesso.');
    this.loadUsers();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedUser.set(null);
  }

  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;

    const single = this.userToDelete();
    const ids = single ? [single.id] : this.selectedUserIds();
    if (ids.length === 0) return;

    this.isDeleting.set(true);
    forkJoin(ids.map((id) => this.userService.delete(id))).subscribe({
      next: () => {
        this.isDeleting.set(false);
        this.showDeleteModal.set(false);
        this.userToDelete.set(null);
        this.clearSelection();
        this.toast.success(
          ids.length > 1 ? 'Usuários excluídos com sucesso.' : 'Usuário excluído com sucesso.',
        );
        this.loadUsers();
      },
      error: () => {
        this.isDeleting.set(false);
        this.showDeleteModal.set(false);
      },
    });
  }

  onSearch(term: string): void {
    this.searchTerm = term;
    this.currentPage = 1;
    this.loadUsers();
  }

  loadPage(page: number): void {
    this.currentPage = page;
    this.loadUsers();
  }

  retry(): void {
    this.loadUsers();
  }

  getInitials(name: string): string {
    return getInitials(name);
  }

  formatDocument(value: string | undefined): string {
    if (value === undefined || value === null || value.trim() === '') return '—';
    const digits = value.replace(/\D/g, '');
    if (digits.length === 11) return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    if (digits.length === 14)
      return digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    return value;
  }

  getRowSelectionControl(id: string): FormControl<boolean> {
    const existing = this.rowSelectionControls.get(id);
    if (existing) return existing;

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
      const allSelected = selectable.length > 0 && selectable.every((u) => selected.has(u.id));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private loadUsers(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.userService
      .list({
        search: this.searchTerm || undefined,
        page: this.currentPage,
        per_page: 15,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.users.set(response.data);
          this.meta.set({
            current_page: response.meta?.current_page ?? 1,
            last_page: response.meta?.last_page ?? 1,
            per_page: response.meta?.per_page ?? 15,
            total: response.meta?.total ?? response.data.length,
          });
          this.syncSelectionControls(response.data);
          this.isLoading.set(false);
        },
        error: () => {
          this.hasError.set(true);
          this.isLoading.set(false);
        },
      });
  }

  private loadCompanies(): void {
    this.companyService
      .list({ per_page: 100, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.companies.set(
            response.data.map((company) => ({ id: company.id, name: company.name })),
          );
        },
        error: () => {
          this.companies.set([]);
        },
      });
  }

  private loadRoles(): void {
    this.roleService
      .listAll()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.roles.set(response.data.map((role) => ({ id: role.id, name: role.name })));
        },
        error: () => {
          this.roles.set([]);
        },
      });
  }

  private syncSelectionControls(users: PlatformUser[]): void {
    const activeIds = new Set(users.map((u) => u.id));
    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) {
        this.rowSelectionControls.delete(key);
      }
    }

    const selected = this.selectedUserIds().filter((id) => activeIds.has(id));
    this.selectedUserIds.set(selected);

    for (const user of users) {
      this.getRowSelectionControl(user.id).setValue(selected.includes(user.id));
    }

    const selectable = users.filter((u) => !u.is_primary_tenant_user);
    this.selectAllControl.setValue(selectable.length > 0 && selected.length === selectable.length, {
      emitEvent: false,
    });
  }

  private clearSelection(): void {
    this.selectedUserIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }
}
