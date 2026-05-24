import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { type SubscriptionUsage } from '@shared/models/subscription.model';

/**
 * Bloco de métricas de uso atual do tenant.
 */
@Component({
  selector: 'app-usage-stats',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './usage-stats.html',
})
export class UsageStatsComponent {
  readonly usage = input.required<SubscriptionUsage>();

  readonly aiMsgBarColor = computed(() => {
    const pct = this.usage().ai_messages?.percentage ?? 0;
    if (pct < 80) return 'bg-primary-500';
    if (pct < 100) return 'bg-warning';
    return 'bg-error';
  });

  readonly aiMsgBarWidth = computed(() => {
    const pct = this.usage().ai_messages?.percentage ?? 0;
    return Math.min(pct, 100);
  });

  protected formatDateShort(isoDate: string): string {
    const [year, month, day] = isoDate.split('-').map(Number);
    const months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    return `${day}/${months[(month ?? 1) - 1]}`;
  }

  protected formatCurrencyBRL(value: number | null | undefined): string {
    if (value === null || value === undefined) return '';
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value);
  }
}
