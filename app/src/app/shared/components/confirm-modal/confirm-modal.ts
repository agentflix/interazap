import { Component, ChangeDetectionStrategy, computed, input, output } from '@angular/core';
import { AfModalComponent } from '../modal/modal';
import { AfButtonComponent } from '../button/button';

/**
 * Confirm modal component for InteraZap UI Kit.
 *
 * @description Pre-built confirmation dialog with icon, message, and action buttons.
 * Supports danger and warning variants for destructive actions.
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
  /** Whether the confirm modal is open */
  readonly open = input(false);
  readonly isOpen = input<boolean | undefined>(undefined);

  /** Title for the modal header */
  readonly title = input('Confirmar');

  /** Message body explaining the action */
  readonly message = input('Tem certeza que deseja realizar esta ação?');

  /** Confirm button label */
  readonly confirmLabel = input('Confirmar');

  /** Cancel button label */
  readonly cancelLabel = input('Cancelar');

  /** Visual variant: danger shows red, warning shows yellow */
  readonly variant = input<'danger' | 'warning' | 'default' | 'primary'>('default');

  readonly isLoading = input(false);

  /** Lucide icon name */
  readonly iconName = input<string>('alert-triangle');

  /** Emitted when user confirms */
  readonly confirmed = output<void>();

  /** Emitted when user cancels */
  readonly cancelled = output<void>();

  protected readonly resolvedOpen = computed(() => this.isOpen() ?? this.open());

  /** Icon background class based on variant */
  protected get iconBgClass(): string {
    const map: Record<string, string> = {
      danger: 'bg-red-100 dark:bg-red-900/30',
      warning: 'bg-amber-100 dark:bg-amber-900/30',
      primary: 'bg-accent-100 dark:bg-accent-900/30',
      default: 'bg-accent-100 dark:bg-accent-900/30',
    };
    return map[this.variant()];
  }

  /** Icon color class based on variant */
  protected get iconColorClass(): string {
    const map: Record<string, string> = {
      danger: 'text-red-600 dark:text-red-400',
      warning: 'text-amber-600 dark:text-amber-400',
      primary: 'text-accent-600 dark:text-accent-400',
      default: 'text-accent-600 dark:text-accent-400',
    };
    return map[this.variant()];
  }

  /** Emit confirm event */
  protected onConfirm(): void {
    this.confirmed.emit();
  }

  /** Emit cancel event */
  protected onCancel(): void {
    this.cancelled.emit();
  }
}

export const ConfirmModalComponent = AfConfirmModalComponent;
