import { Component, ChangeDetectionStrategy, computed, input, output } from '@angular/core';
import { AfModalComponent } from '../modal/modal';
import { AfButtonComponent } from '../button/button';

/**
 * Modal de confirmação com ícone, mensagem e botões de ação.
 *
 * Suporta variantes danger e warning para ações destrutivas.
 *
 * @example
 * ```html
 * <af-confirm-modal
 *   [open]="showConfirm"
 *   title="Excluir Contato"
 *   message="Tem certeza que deseja excluir este contato? Esta ação não pode ser desfeita."
 *   confirmLabel="Excluir"
 *   variant="danger"
 *   (confirmed)="onDelete()"
 *   (cancelled)="showConfirm = false"
 * />
 * ```
 */
@Component({
  selector: 'af-confirm-modal, app-confirm-modal',
  standalone: true,
  imports: [AfModalComponent, AfButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './confirm-modal.html',
})
export class AfConfirmModalComponent {
  /** Indica se o modal está aberto */
  readonly open = input(false);
  readonly isOpen = input<boolean | undefined>(undefined);

  /** Título exibido no cabeçalho do modal */
  readonly title = input('Confirmar');

  /** Corpo da mensagem explicando a ação */
  readonly message = input('Tem certeza que deseja realizar esta ação?');

  /** Rótulo do botão de confirmação */
  readonly confirmLabel = input('Confirmar');

  /** Rótulo do botão de cancelamento */
  readonly cancelLabel = input('Cancelar');

  /** Variante visual: danger exibe vermelho, warning exibe amarelo */
  readonly variant = input<'danger' | 'warning' | 'default' | 'primary'>('default');

  /** Indica estado de carregamento do botão de confirmação */
  readonly isLoading = input(false);

  /** Nome do ícone Lucide */
  readonly iconName = input<string>('alert-triangle');

  /** Emitido quando o usuário confirma */
  readonly confirmed = output<void>();

  /** Emitido quando o usuário cancela */
  readonly cancelled = output<void>();

  protected readonly resolvedOpen = computed(() => this.isOpen() ?? this.open());

  /** Classe de fundo do ícone baseada na variante */
  protected get iconBgClass(): string {
    const map: Record<string, string> = {
      danger: 'bg-red-100 dark:bg-red-900/30',
      warning: 'bg-amber-100 dark:bg-amber-900/30',
      primary: 'bg-accent-100 dark:bg-accent-900/30',
      default: 'bg-accent-100 dark:bg-accent-900/30',
    };
    return map[this.variant()];
  }

  /** Classe de cor do ícone baseada na variante */
  protected get iconColorClass(): string {
    const map: Record<string, string> = {
      danger: 'text-red-600 dark:text-red-400',
      warning: 'text-amber-600 dark:text-amber-400',
      primary: 'text-accent-600 dark:text-accent-400',
      default: 'text-accent-600 dark:text-accent-400',
    };
    return map[this.variant()];
  }

  /** Emite o evento de confirmação */
  protected onConfirm(): void {
    this.confirmed.emit();
  }

  /** Emite o evento de cancelamento */
  protected onCancel(): void {
    this.cancelled.emit();
  }
}

export const ConfirmModalComponent = AfConfirmModalComponent;
