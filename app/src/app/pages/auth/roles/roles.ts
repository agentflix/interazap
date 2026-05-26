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
import { Router } from '@angular/router';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfTableActionsComponent,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { RoleService } from '@core/services/role.service';
import { type Role, type RoleUser } from '../../../core/models/role.model';
import { RoleFormComponent } from './components/role-form/role-form';
import { RoleUsersModalComponent } from './components/role-users-modal/role-users-modal';

/**
 * UUID fixo da role Administrador que não pode ser excluída.
 */
const SYSTEM_ROLE_IDS = new Set([
  '00000000-0000-4000-8000-000000000001', // Administrador
]);

/**
 * Página de perfis de acesso — CRUD completo para gerenciar roles e suas permissões.
 */
@Component({
  selector: 'app-roles',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfDataTableComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    RoleFormComponent,
    RoleUsersModalComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './roles.html',
})
export class Roles implements OnInit {
  private readonly service = inject(RoleService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly router = inject(Router);

  readonly roleFormRef = viewChild<RoleFormComponent>('roleForm');

  // ── List state ───────────────────────────────────────────────────────────────
  readonly roles = signal<Role[]>([]);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isLoading = signal(false);
  readonly hasError = signal(false);
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.roles().length === 0,
  );

  private currentPage = 1;
  private searchTerm = '';

  // ── Form modal ───────────────────────────────────────────────────────────────
  readonly showFormModal = signal(false);
  readonly selectedRole = signal<Role | null>(null);
  readonly isFormSaving = computed(() => this.roleFormRef()?.isSaving() ?? false);
  readonly formModalTitle = computed(() =>
    this.selectedRole() ? 'Editar perfil de acesso' : 'Novo perfil de acesso',
  );

  // ── Delete modal ─────────────────────────────────────────────────────────────
  readonly showDeleteModal = signal(false);
  readonly roleToDelete = signal<Role | null>(null);
  readonly isDeleting = signal(false);
  readonly deleteMessage = computed(() => {
    const role = this.roleToDelete();
    return role
      ? `Tem certeza que deseja excluir o perfil "${role.name}"? Esta ação não pode ser desfeita.`
      : '';
  });

  /**
   * Verifica se a role é do sistema e não pode ser excluída.
   */
  isSystemRole(role: Role): boolean {
    return SYSTEM_ROLE_IDS.has(role.id);
  }

  // ── Users modal ──────────────────────────────────────────────────────────────
  readonly showUsersModal = signal(false);
  readonly selectedRoleForUsers = signal<Role | null>(null);

  /**
   * Abre o modal de usuários vinculados à role.
   * @param role Role selecionada
   */
  openUsersModal(role: Role): void {
    this.selectedRoleForUsers.set(role);
    this.showUsersModal.set(true);
  }

  /** Fecha o modal de usuários e recarrega a lista de roles. */
  closeUsersModal(): void {
    this.showUsersModal.set(false);
    this.selectedRoleForUsers.set(null);
    this.loadRoles();
  }

  /**
   * Fecha o modal e navega para a edição do usuário na página de usuários.
   * @param user Usuário a editar
   */
  onEditUserFromModal(user: RoleUser): void {
    this.closeUsersModal();
    void this.router.navigate(['/settings/users'], {
      queryParams: { edit: user.id },
    });
  }

  ngOnInit(): void {
    this.loadRoles();
  }

  // ── Data loading ─────────────────────────────────────────────────────────────

  /** Carrega a lista paginada de perfis de acesso. */
  loadRoles(): void {
    this.isLoading.set(true);
    this.hasError.set(false);
    this.service
      .list({ search: this.searchTerm || undefined, page: this.currentPage, per_page: 15 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.roles.set(response.data);
          this.meta.set(response.meta);
          this.isLoading.set(false);
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
    this.loadRoles();
  }

  /**
   * Navega para a página especificada da listagem.
   * @param page Número da página
   */
  loadPage(page: number): void {
    this.currentPage = page;
    this.loadRoles();
  }

  /** Recarrega a listagem após erro. */
  retry(): void {
    this.loadRoles();
  }

  // ── CRUD actions ─────────────────────────────────────────────────────────────

  /** Abre o modal no modo de criação de novo perfil. */
  openCreate(): void {
    this.selectedRole.set(null);
    this.showFormModal.set(true);
  }

  /**
   * Abre o modal no modo de edição, carregando permissões detalhadas da role.
   * @param role Role a editar
   */
  openEdit(role: Role): void {
    this.showFormModal.set(true);
    this.selectedRole.set(role);
    this.service
      .find(role.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.selectedRole.set(response.data);
        },
        error: () => {
          this.toast.error('Não foi possível carregar as permissões do perfil.');
        },
      });
  }

  /**
   * Abre o modal de confirmação de exclusão.
   * @param role Role a excluir
   */
  openDelete(role: Role): void {
    this.roleToDelete.set(role);
    this.showDeleteModal.set(true);
  }

  /**
   * Fecha o modal e recarrega a lista após salvar um perfil.
   * @param role Perfil salvo
   */
  handleFormSaved(role: Role): void {
    this.showFormModal.set(false);
    this.toast.success('Perfil salvo com sucesso.');
    this.loadRoles();
    void role;
  }

  /** Fecha o modal de formulário sem salvar. */
  handleFormCancelled(): void {
    this.showFormModal.set(false);
  }

  /** Confirma e executa a exclusão do perfil selecionado. */
  handleDeleteConfirmed(): void {
    const role = this.roleToDelete();
    if (!role || this.isDeleting()) return;
    this.isDeleting.set(true);
    this.service
      .delete(role.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
          this.roleToDelete.set(null);
          this.toast.success('Perfil excluído com sucesso.');
          this.loadRoles();
        },
        error: () => {
          this.isDeleting.set(false);
        },
      });
  }

  /**
   * Formata uma data ISO para o padrão brasileiro (dd/mm/aaaa).
   * @param dateValue String de data ISO
   * @returns Data formatada ou `—` se ausente/inválida
   */
  formatDate(dateValue?: string): string {
    if (!dateValue) return '—';
    const date = new Date(dateValue);
    if (Number.isNaN(date.getTime())) return dateValue;
    return new Intl.DateTimeFormat('pt-BR').format(date);
  }
}
