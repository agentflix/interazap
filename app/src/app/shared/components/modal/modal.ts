import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { A11yModule } from '@angular/cdk/a11y';
import { AfScrollAreaComponent } from '@shared/components/scroll-area/scroll-area';

/**
 * Modal dialog component for AgentFlix UI Kit.
 *
 * @description Full-featured modal with backdrop, header, body, and footer slots.
 * Supports multiple sizes and closes on Escape key and backdrop click.
 *
 * @example
 * ```html
 * <af-modal [open]="showModal" title="Novo contato" (closed)="showModal = false">
 *   <p>Modal body content here...</p>
 *
 *   <div footer class="flex justify-end gap-2">
 *     <af-button variant="ghost" (click)="showModal = false">Cancelar</af-button>
 *     <af-button variant="primary" (click)="save()">Salvar</af-button>
 *   </div>
 * </af-modal>
 * ```
 */
@Component({
  selector: 'af-modal, app-modal',
  standalone: true,
  imports: [A11yModule, AfScrollAreaComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    '(document:keydown.escape)': 'onEscape()',
  },
  templateUrl: './modal.html',
  styleUrl: './modal.scss',
})
export class AfModalComponent {
  /** Whether the modal is open */
  readonly open = input(false);

  /** Legacy alias for open state */
  readonly isOpen = input<boolean | undefined>(undefined);

  /** Modal title displayed in the header */
  readonly title = input<string>();

  /** Modal size */
  readonly size = input<'sm' | 'md' | 'lg' | 'xl'>('md');

  /** Legacy alias for size */
  readonly maxWidth = input<'sm' | 'md' | 'lg' | 'xl' | undefined>(undefined);

  /** Whether to show the close (X) button */
  readonly showClose = input(true);

  /** Whether clicking the backdrop closes the modal */
  readonly closeOnBackdrop = input(true);

  /** Whether the modal body should scroll when content overflows (default: true).
   *  Set to false for modals with select dropdowns that need to overflow */
  readonly scrollBody = input(true);

  /** Emitted when the modal requests to close */
  readonly closed = output<void>();

  protected readonly resolvedOpen = computed(() => this.isOpen() ?? this.open());

  /** Panel width classes based on size */
  protected readonly panelClasses = computed(() => {
    const base = [
      'relative z-10 w-full',
      'bg-white dark:bg-neutral-900',
      'rounded-lg shadow-xl',
      'border border-neutral-200 dark:border-neutral-700',
      'animate-in fade-in zoom-in-95 duration-200',
    ];

    const sizes: Record<string, string> = {
      sm: 'max-w-sm',
      md: 'max-w-lg',
      lg: 'max-w-2xl',
      xl: 'max-w-4xl',
    };

    return [...base, sizes[this.maxWidth() ?? this.size()]].join(' ');
  });

  /** Close the modal */
  protected close(): void {
    this.closed.emit();
  }

  /** Handle backdrop click */
  protected onBackdropClick(): void {
    if (this.closeOnBackdrop()) {
      this.close();
    }
  }

  /** Handle Escape key */
  protected onEscape(): void {
    if (this.resolvedOpen()) {
      this.close();
    }
  }
}

export const ModalComponent = AfModalComponent;
