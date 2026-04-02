import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  Input,
  computed,
  inject,
  signal,
} from '@angular/core';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { Router } from '@angular/router';
import { FormControl } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import { type Called, CalledService } from 'src/app/core/services/called.service';
import { type Department, DepartmentService } from 'src/app/core/services/department.service';
import { type User, UserService } from 'src/app/core/services/user.service';
import { toast } from 'ngx-sonner';
import {
  lucideArrowRightLeft,
  lucideX,
  lucideSearch,
  lucideCheck,
  lucideCopy,
} from '@ng-icons/lucide';
import { ModalComponent } from 'src/app/shared/components/modal/modal';
import {
  SelectInputComponent,
  TextareaInputComponent,
  type SelectOption,
} from 'src/app/shared/components/inputs';
import {
  ButtonComponent,
  IconButtonComponent,
  LoadingButtonComponent,
} from 'src/app/shared/components/buttons';

/**
 * Componente da barra superior do chat.
 *
 * Exibe informações do contato atual, status do atendimento e gerencia
 * ações de transferência e encerramento de tickets.
 *
 * @class ChatNavbar
 * @description Barra de ferramentas e informações do atendimento ativo.
 */
@Component({
  selector: 'app-chat-navbar',
  standalone: true,
  imports: [
    NgIcon,
    ModalComponent,
    ButtonComponent,
    IconButtonComponent,
    LoadingButtonComponent,
    SelectInputComponent,
    TextareaInputComponent,
  ],
  templateUrl: './chat-navbar.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  viewProviders: [
    provideIcons({ lucideArrowRightLeft, lucideX, lucideSearch, lucideCheck, lucideCopy }),
  ],
})
export class ChatNavbar {
  private readonly router = inject(Router);
  private readonly authStore = inject(AuthStoreService);
  private readonly calledService = inject(CalledService);
  private readonly departmentService = inject(DepartmentService);
  private readonly userService = inject(UserService);
  private readonly destroyRef = inject(DestroyRef);

  readonly departmentControl = new FormControl<string>('', { nonNullable: true });
  readonly userControl = new FormControl<string>('', { nonNullable: true });
  readonly transferReasonControl = new FormControl<string>('', { nonNullable: true });

  /**
   * Dados do usuário autenticado obtidos do store global.
   * @type {Signal<User | null>}
   */
  readonly user = computed(() => this.authStore.user());

  /**
   * Controla a visibilidade do modal de transferência.
   * @type {WritableSignal<boolean>}
   */
  readonly isTransferOpen = signal(false);

  /**
   * Indica se um processo de transferência está sendo processado.
   * @type {WritableSignal<boolean>}
   */
  readonly isTransferLoading = signal(false);

  /**
   * Controla a visibilidade do modal de encerramento de ticket.
   * @type {WritableSignal<boolean>}
   */
  readonly isCloseModalOpen = signal(false);

  /**
   * Indica se o encerramento do ticket está sendo processado.
   * @type {WritableSignal<boolean>}
   */
  readonly isClosing = signal(false);

  /**
   * Lista de departamentos disponíveis para transferência.
   * @type {WritableSignal<Department[]>}
   */
  readonly departments = signal<Department[]>([]);

  /**
   * Lista completa de usuários disponíveis para transferência.
   * @type {WritableSignal<User[]>}
   */
  readonly users = signal<User[]>([]);

  /**
   * ID do departamento selecionado no formulário de transferência.
   * @type {WritableSignal<string | null>}
   */
  readonly selectedDepartmentId = signal<string | null>(null);

  /**
   * ID do atendente selecionado no formulário de transferência.
   * @type {WritableSignal<string | null>}
   */
  readonly selectedUserId = signal<string | null>(null);

  /**
   * Lista de usuários filtrada pelo departamento selecionado.
   * @type {Signal<User[]>}
   */
  readonly filteredUsers = computed(() => {
    const deptId = this.selectedDepartmentId();
    if (!deptId) return this.users();
    return this.users().filter((u) => String(u.department_id ?? '') === deptId);
  });

  readonly departmentOptions = computed<readonly SelectOption[]>(() =>
    this.departments().map((dept) => ({
      value: String(dept.id),
      label: dept.name,
    })),
  );

  readonly userOptions = computed<readonly SelectOption[]>(() =>
    this.filteredUsers().map((user) => ({
      value: String(user.id),
      label: user.name,
    })),
  );

  /**
   * Dados do atendimento (ticket) ativo no momento.
   * @type {Input}
   */
  @Input() called: Called | null = null;

  constructor() {
    this.departmentControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.onDepartmentChange(value));

    this.userControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.onUserChange(value));
  }

  /**
   * Obtém o nome do contato associado ao atendimento ativo.
   * @returns {string}
   */
  get contactName(): string {
    return this.called?.contact?.name || 'Contato sem nome';
  }

  /**
   * Obtém o telefone ou WhatsApp do contato.
   * @returns {string}
   */
  get contactPhone(): string {
    return this.called?.contact?.phone || this.called?.contact?.whatsapp || 'Sem telefone';
  }

  /**
   * Obtém o rótulo amigável do status do chamado.
   * @returns {string}
   */
  get statusLabel(): string {
    switch (this.called?.status) {
      case 'pending':
        return 'Pendente';
      case 'open':
        return 'Aberto';
      case 'in_progress':
        return 'Em atendimento';
      case 'closed':
        return 'Encerrado';
      default:
        return 'Sem status';
    }
  }

  /**
   * Obtém a classe de cor CSS para o badge de status.
   * @returns {string} Classes Tailwind.
   */
  get statusClass(): string {
    switch (this.called?.status) {
      case 'pending':
        return 'bg-warning/10 text-warning';
      case 'open':
        return 'bg-info/10 text-info';
      case 'in_progress':
        return 'bg-success/10 text-success';
      case 'closed':
        return 'bg-neutral-200 text-neutral-600';
      default:
        return 'bg-neutral-100 text-neutral-600';
    }
  }

  /**
   * Obtém as iniciais do contato ou protocolo para exibição no avatar.
   * @returns {string}
   */
  get contactInitials(): string {
    const name = this.called?.contact?.name ?? '';
    const parts = name.trim().split(' ').filter(Boolean);
    if (parts.length === 0) {
      const protocol = this.called?.protocol ?? '';
      return protocol ? protocol.slice(0, 2).toUpperCase() : 'CT';
    }
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
  }

  /**
   * Copia o número do protocolo/ticket para a área de transferência.
   */
  copyProtocol(): void {
    const protocol = this.called?.protocol;
    if (!protocol) return;
    void navigator.clipboard
      .writeText(protocol)
      .then(() => {
        toast.success(`Protocolo #${protocol} copiado!`);
      })
      .catch(() => {
        toast.error('Nao foi possivel copiar o protocolo.');
      });
  }

  /**
   * Navegar de volta para a tela inicial do chat (fechar visualização do ticket).
   */
  closeChat(): void {
    void this.router.navigate(['/chat']);
  }

  // Transfer Actions
  /**
   * Preparar e abrir o diálogo de transferência de atendimento.
   */
  openTransfer(): void {
    this.isTransferOpen.set(true);
    this.loadTransferData();
    this.departmentControl.setValue(this.selectedDepartmentId() ?? '', { emitEvent: false });
    this.userControl.setValue(this.selectedUserId() ?? '', { emitEvent: false });
  }

  /**
   * Fechar o diálogo de transferência e resetar o estado interno.
   */
  closeTransfer(): void {
    this.isTransferOpen.set(false);
    this.resetTransferState();
  }

  /**
   * Tratar a mudança de departamento selecionado no formulário.
   * @param value O evento de alteração do select.
   */
  onDepartmentChange(value: string): void {
    const deptId = value || null;
    this.selectedDepartmentId.set(deptId);

    // Check if selected user belongs to new department
    const userId = this.selectedUserId();
    if (userId && deptId) {
      const user = this.users().find((u) => String(u.id) === userId);
      if (user && String(user.department_id ?? '') !== deptId) {
        this.selectedUserId.set(null);
        this.userControl.setValue('', { emitEvent: false });
      }
    }
  }

  /**
   * Tratar a mudança de usuário (atendente) selecionado no formulário.
   * @param value O evento de alteração do select.
   */
  onUserChange(value: string): void {
    const userId = value || null;
    this.selectedUserId.set(userId);

    // Auto-select department if user has one
    if (userId) {
      const user = this.users().find((u) => String(u.id) === userId);
      if (user && user.department_id) {
        const deptId = String(user.department_id);
        this.selectedDepartmentId.set(deptId);
        if (this.departmentControl.value !== deptId) {
          this.departmentControl.setValue(deptId, { emitEvent: false });
        }
      }
    }
  }

  // Close Ticket
  /**
   * Abrir o diálogo de confirmação para encerramento do ticket.
   */
  openCloseModal(): void {
    this.isCloseModalOpen.set(true);
  }

  /**
   * Fechar o diálogo de confirmação de encerramento.
   */
  closeCloseModal(): void {
    this.isCloseModalOpen.set(false);
  }

  /**
   * Confirmar e processar o encerramento do atendimento no backend.
   */
  confirmClose(): void {
    const calledId = this.called?.id;
    if (!calledId) return;

    this.isClosing.set(true);
    this.calledService.close(calledId).subscribe({
      next: () => {
        toast.success('Atendimento encerrado com sucesso!');
        this.closeCloseModal();
        this.isClosing.set(false);
        this.closeChat();
      },
      error: () => {
        toast.error('Erro ao encerrar atendimento.');
        this.isClosing.set(false);
      },
    });
  }

  /**
   * Confirmar e processar a transferência do atendimento para outro departamento ou usuário.
   */
  confirmTransfer(): void {
    const calledId = this.called?.id;
    const deptId = this.selectedDepartmentId();
    const userId = this.selectedUserId();
    const transferReason = this.transferReasonControl.value.trim();

    if (!calledId || (!deptId && !userId)) return;

    if (userId && transferReason.length === 0) {
      toast.error('Informe o motivo da transferência.');

      return;
    }

    this.isTransferLoading.set(true);

    if (userId) {
      this.calledService
        .transferToUser(calledId, {
          to_user_id: userId,
          reason: transferReason,
        })
        .subscribe({
          next: () => {
            toast.success('Transferência realizada com sucesso!');
            this.closeTransfer();
          },
          error: () => {
            toast.error('Erro ao realizar transferência.');
            this.isTransferLoading.set(false);
          },
        });

      return;
    }

    this.calledService.transfer(calledId, { department_id: deptId || undefined }).subscribe({
      next: () => {
        toast.success('Transferência realizada com sucesso!');
        this.closeTransfer();
      },
      error: () => {
        toast.error('Erro ao realizar transferência.');
        this.isTransferLoading.set(false);
      },
    });
  }

  /**
   * Carregar dados de departamentos e usuários para popular os campos de transferência.
   * @private
   */
  private loadTransferData(): void {
    // Load Departments
    this.departmentService.list({ per_page: 100 }).subscribe((response) => {
      if (response.success) {
        this.departments.set(response.data);
      }
    });

    // Load Users
    this.userService.list({ per_page: 100 }).subscribe((response) => {
      this.users.set(response.data);
    });
  }

  /**
   * Limpar o estado do formulário de transferência.
   * @private
   */
  private resetTransferState(): void {
    this.selectedDepartmentId.set(null);
    this.selectedUserId.set(null);
    this.departmentControl.setValue('', { emitEvent: false });
    this.userControl.setValue('', { emitEvent: false });
    this.transferReasonControl.setValue('', { emitEvent: false });
    this.isTransferLoading.set(false);
  }
}
