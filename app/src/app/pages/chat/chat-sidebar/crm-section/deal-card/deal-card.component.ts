import { ChangeDetectionStrategy, Component, computed, input, output, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { provideIcons } from '@ng-icons/core';
import { CurrencyPipe } from '@shared/pipes/currency.pipe';
import {
  lucideCheck,
  lucideChevronLeft,
  lucideChevronRight,
  lucidePencil,
  lucideX,
} from '@ng-icons/lucide';
import { type CRMNegotiation } from '../crm-negotiation.model';
import { IconButtonComponent } from 'src/app/shared/components/buttons';

/**
 * Card visual para exibicao de negociacao.
 *
 * @remarks
 * Inclui acoes de editar, marcar como ganha/perdida e navegar entre etapas.
 * Exibe badge de status com cores diferenciadas para cada estado.
 *
 * @example
 * ```html
 * <app-deal-card
 *   [deal]="negotiation"
 *   (edit)="onEdit()"
 *   (markLost)="onMarkLost()"
 *   (markWon)="onMarkWon()"
 *   (stageChanged)="onStageChanged($event)" />
 * ```
 */
@Component({
  selector: 'app-deal-card',
  templateUrl: './deal-card.component.html',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [DatePipe, IconButtonComponent, CurrencyPipe],
  host: { class: 'block' },
  viewProviders: [
    provideIcons({ lucideChevronLeft, lucideChevronRight, lucidePencil, lucideX, lucideCheck }),
  ],
})
export class DealCardComponent {
  readonly deal = input.required<CRMNegotiation>();
  readonly edit = output<void>();
  readonly markLost = output<void>();
  readonly markWon = output<void>();
  readonly stageChanged = output<'previous' | 'next'>();

  readonly isUpdating = signal(false);

  /**
   * Classe CSS por status da negociação.
   */
  readonly statusClass = computed(() => {
    const status = this.deal().status;
    return {
      open: 'border-l-4 border-l-blue-500',
      won: 'border-l-4 border-l-green-500',
      lost: 'border-l-4 border-l-gray-400',
    }[status];
  });

  /**
   * Badge de status visual.
   */
  readonly statusBadge = computed(() => {
    const status = this.deal().status;
    return {
      open: { text: 'Em Andamento', class: 'bg-blue-500/10 text-blue-600 dark:text-blue-400' },
      won: { text: 'Ganha', class: 'bg-green-500/10 text-green-600 dark:text-green-400' },
      lost: { text: 'Perdida', class: 'bg-neutral-500/10 text-neutral-600 dark:text-neutral-400' },
    }[status];
  });

  /**
   * Mudar para etapa anterior.
   */
  moveToPreviousStage(): void {
    if (this.deal().status !== 'open') return;
    this.isUpdating.set(true);
    // Lógica simplificada: apenas emite evento, quem consome busca a etapa anterior
    this.stageChanged.emit('previous');
  }

  /**
   * Mudar para próxima etapa.
   */
  moveToNextStage(): void {
    if (this.deal().status !== 'open') return;
    this.isUpdating.set(true);
    this.stageChanged.emit('next');
  }

  /**
   * Emitir evento de edição.
   */
  onEdit(): void {
    this.edit.emit();
  }

  /**
   * Emitir evento de marcar como perdida.
   */
  onMarkLost(): void {
    if (this.deal().status !== 'open') return;
    this.markLost.emit();
  }

  /**
   * Emitir evento de marcar como ganha.
   */
  onMarkWon(): void {
    if (this.deal().status !== 'open') return;
    this.markWon.emit();
  }
}
