import {
  type OnChanges,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  output,
  signal,
  computed,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfConfirmModalComponent,
  AfDataTableComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { RoleService } from '@core/services/role.service';
import { UserService } from '@core/services/user.service';
import { type Role, type RoleUser } from '../../../../../core/models/role.model';

/**
 * Modal que lista os usuários de uma role específica,
 * permitindo editar, desvincular e inativar.
 */
@Component({
  selector: 'app-role-users-modal',
  standalone: true,
  imports: [
    LucideAngularModule,
    AfModalComponent,
    AfDataTableComponent,
    AfTableActionsComponent,
    AfStatusBadgeComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfConfirmModalComponent,
    AfAlertComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './role-users-modal.html',
})
export class RoleUsersModalComponent implements OnChanges {
  private readonly roleService = inject(RoleService);
  private readonly userService = inject(UserService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly role = input<Role | null>(null);
  readonly open = input(false);

  readonly closed = output<void>();
  readonly editUser = output<RoleUser>();
  readonly userUpdated = output<void>();

  // ── List state ───────────────────────────────────────────────────────────────
  readonly users = signal<RoleUser[]>([]);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isLoading = signal(false);
  readonly hasError = signal(false);
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.users().length === 0,
  );

  private currentPage = 1;
  private searchTerm = '';

  // ── Remove role modal ────────────────────────────────────────────────────────
  readonly showRemoveModal = signal(false);
  readonly userToRemoveRole = signal<RoleUser | null>(null);
  readonly isRemoving = signal(false);
  readonly removeRoleMessage = computed(() => {
    const user = this.userToRemoveRole();
    const roleName = this.role()?.name ?? '';
    return user
      ? `Tem certeza que deseja desvincular o perfil "${roleName}" do usuário "${user.name}"?`
      : '';
  });

  // ── Toggle status modal ──────────────────────────────────────────────────────
  readonly showToggleModal = signal(false);
  readonly userToToggle = signal<RoleUser | null>(null);
  readonly isToggling = signal(false);
  readonly toggleMessage = computed(() => {
    const user = this.userToToggle();
    if (!user) return '';
    const action = user.is_active ? 'inativar' : 'ativar';
    return `Tem certeza que deseja ${action} o usuário "${user.name}"?`;
  });

  // ── Open watcher ─────────────────────────────────────────────────────────────
  private prevOpen = false;

  ngOnChanges(): void {
    if (this.open() && !this.prevOpen && this.role()) {
      this.currentPage = 1;
      this.searchTerm = '';
      this.loadUsers();
    }
    this.prevOpen = this.open();
  }

  // ── Data loading ─────────────────────────────────────────────────────────────
  loadUsers(): void {
    const roleId = this.role()?.id;
    if (!roleId) return;

    this.isLoading.set(true);
    this.hasError.set(false);
    this.roleService
      .listUsersByRole(roleId, { search: this.searchTerm || undefined, page: this.currentPage, per_page: 15 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.users.set(response.data);
          this.meta.set(response.meta);
          this.isLoading.set(false);
        },
        error: () => {
          this.hasError.set(true);
          this.isLoading.set(false);
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

  // ── Actions ──────────────────────────────────────────────────────────────────
  handleEdit(user: RoleUser): void {
    this.editUser.emit(user);
    this.handleClosed();
  }

  openRemoveRole(user: RoleUser): void {
    this.userToRemoveRole.set(user);
    this.showRemoveModal.set(true);
  }

  openToggleStatus(user: RoleUser): void {
    this.userToToggle.set(user);
    this.showToggleModal.set(true);
  }

  handleRemoveConfirmed(): void {
    const user = this.userToRemoveRole();
    const roleName = this.role()?.name;
    if (!user || !roleName || this.isRemoving()) return;

    this.isRemoving.set(true);
    this.userService
      .removeRole(user.id, roleName)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isRemoving.set(false);
          this.showRemoveModal.set(false);
          this.userToRemoveRole.set(null);
          this.toast.success('Role removida do usuário com sucesso.');
          this.loadUsers();
          this.userUpdated.emit();
        },
        error: (err) => {
          this.isRemoving.set(false);
          const msg = err?.error?.message || 'Não foi possível remover a role do usuário.';
          this.toast.error(msg);
        },
      });
  }

  handleToggleConfirmed(): void {
    const user = this.userToToggle();
    if (!user || this.isToggling()) return;

    this.isToggling.set(true);
    this.userService
      .toggleActive(user.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isToggling.set(false);
          this.showToggleModal.set(false);
          this.userToToggle.set(null);
          const action = user.is_active ? 'inativado' : 'ativado';
          this.toast.success(`Usuário ${action} com sucesso.`);
          this.loadUsers();
          this.userUpdated.emit();
        },
        error: (err) => {
          this.isToggling.set(false);
          const msg = err?.error?.message || 'Não foi possível alterar o status do usuário.';
          this.toast.error(msg);
        },
      });
  }

  // ── Close ────────────────────────────────────────────────────────────────────
  handleClosed(): void {
    this.closed.emit();
  }

  // ── Helpers ──────────────────────────────────────────────────────────────────
  getInitials(name: string): string {
    return name
      .split(' ')
      .slice(0, 2)
      .map((n) => n.charAt(0).toUpperCase())
      .join('');
  }

  formatAvatarColor(name: string): string {
    const colors = [
      'bg-blue-500',
      'bg-green-500',
      'bg-purple-500',
      'bg-orange-500',
      'bg-pink-500',
      'bg-teal-500',
      'bg-indigo-500',
      'bg-red-500',
    ];
    const index = name.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
    return colors[index % colors.length];
  }
}
