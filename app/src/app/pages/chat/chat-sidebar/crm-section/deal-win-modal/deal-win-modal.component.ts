import { ChangeDetectionStrategy, Component, inject, input, output, signal } from '@angular/core';
import { CurrencyPipe } from '@shared/pipes/currency.pipe';
import { NegotiationService } from 'src/app/core/services/negotiation.service';
import { ButtonComponent, LoadingButtonComponent } from 'src/app/shared/components/buttons';
import { ModalComponent } from 'src/app/shared/components/modal/modal';
import { type CRMNegotiation } from '../crm-negotiation.model';

/**
 * Modal simples para confirmar venda ganha.
 *
 * @remarks
 * Exibe titulo e valor, sem formulario adicional.
 * Chama servico para marcar negociacao como ganho.
 *
 * @example
 * ```html
 * <app-deal-win-modal
 *   [isOpen]="isOpen"
 *   [deal]="deal"
 *   (closeModal)="onClose()"
 *   (confirmed)="onConfirmed()" />
 * ```
 */
@Component({
  selector: 'app-deal-win-modal',
  templateUrl: './deal-win-modal.component.html',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ModalComponent, ButtonComponent, LoadingButtonComponent, CurrencyPipe],
  host: { class: 'block' },
})
export class DealWinModalComponent {
  private readonly negotiationService = inject(NegotiationService);

  readonly isOpen = input.required<boolean>();
  readonly deal = input.required<CRMNegotiation>();
  readonly closeModal = output<void>();
  readonly confirmed = output<void>();

  readonly isConfirming = signal(false);

  /**
   * Confirmar venda ganha.
   */
  onConfirm(): void {
    this.isConfirming.set(true);

    this.negotiationService.markAsWon(this.deal().id).subscribe({
      next: () => {
        this.isConfirming.set(false);
        this.confirmed.emit();
      },
      error: () => {
        this.isConfirming.set(false);
      },
    });
  }

  /**
   * Fechar modal.
   */
  onClose(): void {
    this.closeModal.emit();
  }
}
