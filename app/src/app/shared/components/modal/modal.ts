import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { A11yModule } from '@angular/cdk/a11y';
import { AfScrollAreaComponent } from '@shared/components/scroll-area/scroll-area';

/**
 * Modal de diálogo completo com backdrop, slots de cabeçalho, corpo e rodapé.
 *
 * Suporta múltiplos tamanhos e fecha ao pressionar Escape ou clicar no backdrop.
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
  /** Indica se o modal está aberto */
  readonly open = input(false);

  /** Alias legado para o estado aberto */
  readonly isOpen = input<boolean | undefined>(undefined);

  /** Título exibido no cabeçalho do modal */
  readonly title = input<string>();

  /** Tamanho do modal */
  readonly size = input<'sm' | 'md' | 'lg' | 'xl'>('md');

  /** Alias legado para o tamanho */
  readonly maxWidth = input<'sm' | 'md' | 'lg' | 'xl' | undefined>(undefined);

  /** Exibe o botão de fechar (X) */
  readonly showClose = input(true);

  /** Clicar no backdrop fecha o modal */
  readonly closeOnBackdrop = input(true);

  /** Habilita rolagem do corpo quando o conteúdo excede a altura (padrão: true).
   *  Definir como false para modais com select dropdowns que precisam transbordar. */
  readonly scrollBody = input(true);

  /** Emitido quando o modal solicita fechamento */
  readonly closed = output<void>();

  protected readonly resolvedOpen = computed(() => this.isOpen() ?? this.open());

  /** Classes de largura do painel baseadas no tamanho */
  protected readonly panelClasses = computed(() => {
    const base = [
      'relative z-10 w-full',
      'bg-white dark:bg-neutral-900',
      'rounded-lg shadow-xl',
      'border border-neutral-200 dark:border-white/10',
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

  /** Fecha o modal */
  protected close(): void {
    this.closed.emit();
  }

  /** Trata clique no backdrop */
  protected onBackdropClick(): void {
    if (this.closeOnBackdrop()) {
      this.close();
    }
  }

  /** Trata tecla Escape */
  protected onEscape(): void {
    if (this.resolvedOpen()) {
      this.close();
    }
  }
}

export const ModalComponent = AfModalComponent;
