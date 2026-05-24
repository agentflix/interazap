import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { AfButtonComponent, AfStatusBadgeComponent } from '@shared/components';
import { type SubscriptionPlan } from '@shared/models/subscription.model';

/**
 * Card de plano para comparação e seleção de upgrade/downgrade.
 */
@Component({
  selector: 'app-plan-card',
  standalone: true,
  imports: [AfButtonComponent, AfStatusBadgeComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './plan-card.html',
})
export class PlanCardComponent {
  private readonly currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  readonly plan = input.required<SubscriptionPlan>();
  readonly currentPlanPrice = input.required<number>();

  readonly selected = output<SubscriptionPlan>();

  protected actionLabel(): string {
    const selectedPrice = Number(this.plan().price_monthly);

    return selectedPrice >= this.currentPlanPrice() ? 'Upgrade' : 'Downgrade';
  }

  protected formatCurrencyBRL(value: string | number): string {
    const normalized = Number(value);

    if (Number.isNaN(normalized)) {
      return this.currencyFormatter.format(0);
    }

    return this.currencyFormatter.format(normalized);
  }

  protected formatNumber(value: number): string {
    return new Intl.NumberFormat('pt-BR').format(value);
  }
}
