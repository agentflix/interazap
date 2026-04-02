import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { UserService, type User } from 'src/app/core/services/user.service';
import {
  type SelectOption,
  SelectInputComponent,
  TextareaInputComponent,
} from 'src/app/shared/components/inputs';
import { ModalComponent } from 'src/app/shared/components/modal/modal';
import { ButtonComponent } from 'src/app/shared/components/buttons';
import { type Called } from 'src/app/core/services/called.service';

@Component({
  selector: 'app-chat-transfer-modal',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    ModalComponent,
    SelectInputComponent,
    TextareaInputComponent,
    ButtonComponent,
  ],
  templateUrl: './chat-transfer-modal.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    class: 'block',
  },
})
export class ChatTransferModalComponent {
  private readonly userService = inject(UserService);
  private readonly destroyRef = inject(DestroyRef);

  /** Ticket selecionado para transferência. */
  readonly ticket = input<Called | null>(null);

  /** Indica se o modal está aberto. */
  readonly isOpen = input<boolean>(false);

  /** Emitido quando o modal é fechado (cancelado ou confirmado). */
  readonly closed = output<void>();

  /** Estado de submissão vindo do container. */
  readonly isSubmitting = input(false);

  /** Erro de submissão vindo do container. */
  readonly submitError = input<string | null>(null);

  /** Emitido quando a transferência é confirmada com { ticketId, toUserId, reason }. */
  readonly confirmed = output<{ ticketId: string; toUserId: string; reason: string }>();

  /** Usuários carregados para seleção. */
  readonly transferUsers = signal<User[]>([]);

  /** Se a lista de usuários está sendo carregada. */
  readonly isTransferUsersLoading = signal(false);

  /** Erro ao carregar usuários. */
  readonly transferUsersLoadError = signal(false);

  /** Controlador do select de usuário. */
  readonly transferUserControl = new FormControl<string>('', { nonNullable: true });

  /** Controlador do motivo da transferência. */
  readonly transferReasonControl = new FormControl<string>('', { nonNullable: true });

  /** Estado derivado para habilitar/desabilitar ação primária. */
  canSubmit(): boolean {
    if (this.isSubmitting()) {
      return false;
    }

    const hasUser = this.transferUserControl.value.trim().length > 0;
    const hasReason = this.transferReasonControl.value.trim().length > 0;

    return hasUser && hasReason;
  }

  /** Opções de usuários disponíveis para transferência (exceto o atual). */
  readonly transferUserOptions = computed<readonly SelectOption[]>(() => {
    const currentAssignedUserId = String(
      this.ticket()?.assigned_user?.id ?? this.ticket()?.user?.id ?? '',
    );

    return this.transferUsers()
      .filter((user) => user.is_active)
      .filter((user) => String(user.id) !== currentAssignedUserId)
      .map((user) => ({
        value: String(user.id),
        label: user.name,
      }));
  });

  constructor() {
    // Carrega usuários quando o modal abre
    effect(
      () => {
        if (this.isOpen()) {
          this.loadTransferUsers();
        }
      },
      { allowSignalWrites: true },
    );
  }

  /** Fecha o modal sem confirmar transferência. */
  close(): void {
    if (this.isSubmitting()) {
      return;
    }

    this.closed.emit();
  }

  /** Confirma a transferência do ticket para o usuário selecionado. */
  confirm(): void {
    const ticket = this.ticket();
    const selectedUserId = this.transferUserControl.value;
    const reason = this.transferReasonControl.value.trim();

    if (!ticket || !selectedUserId || reason.length === 0 || this.isSubmitting()) {
      return;
    }

    this.confirmed.emit({
      ticketId: String(ticket.id),
      toUserId: selectedUserId,
      reason,
    });
  }

  private loadTransferUsers(): void {
    this.isTransferUsersLoading.set(true);
    this.transferUsersLoadError.set(false);
    this.transferUserControl.setValue('', { emitEvent: false });
    this.transferReasonControl.setValue('', { emitEvent: false });

    this.userService
      .list({ per_page: 100, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.transferUsers.set(response.data);
          this.isTransferUsersLoading.set(false);
        },
        error: () => {
          this.transferUsers.set([]);
          this.transferUsersLoadError.set(true);
          this.isTransferUsersLoading.set(false);
        },
      });
  }
}
