import { ChangeDetectionStrategy, Component, inject, input, output } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import {
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfTextInputComponent,
} from '@shared/components';
import { type PlanChangePreview, type SubscriptionPlan } from '@shared/models/subscription.model';

/**
 * Modal de confirmação para downgrade de plano.
 */
@Component({
  selector: 'app-downgrade-modal',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfModalComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfTextInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './downgrade-modal.html',
})
export class DowngradeModalComponent {
  private readonly currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  private readonly fb = inject(NonNullableFormBuilder);

  readonly open = input(false);
  readonly loading = input(false);
  readonly plan = input<SubscriptionPlan | null>(null);
  readonly preview = input<PlanChangePreview | null>(null);

  readonly confirmed = output<string>();
  readonly closed = output<void>();

  readonly passwordControl = this.fb.control('');

  /** Emite o evento de confirmação com a senha atual digitada. */
  protected submit(): void {
    this.confirmed.emit(this.passwordControl.value.trim());
  }

  /**
   * Formata um valor numérico como moeda BRL (R$).
   * @param value Valor a formatar (string, número ou nulo)
   * @returns String formatada em reais
   */
  protected formatCurrencyBRL(value: string | number | null | undefined): string {
    const normalized = Number(value ?? 0);

    if (Number.isNaN(normalized)) {
      return this.currencyFormatter.format(0);
    }

    return this.currencyFormatter.format(normalized);
  }
}
