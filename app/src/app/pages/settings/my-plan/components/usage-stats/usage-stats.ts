import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { type SubscriptionUsage, type SubscriptionPlan } from '@shared/models/subscription.model';

/**
 * Bloco de métricas de uso atual do tenant.
 * Exibe card especial de trial quando `currentPlan?.is_trial` é verdadeiro.
 */
@Component({
  selector: 'app-usage-stats',
  standalone: true,
  imports: [CommonModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './usage-stats.html',
})
export class UsageStatsComponent {
  readonly usage = input.required<SubscriptionUsage>();
  readonly currentPlan = input<SubscriptionPlan | null>(null);

  /** Indica se o plano atual é um trial. */
  get isTrial(): boolean {
    return this.currentPlan()?.is_trial === true;
  }

  /** Retorna a quantidade de dias restantes do trial, ou 0 se já expirou. */
  get trialDaysLeft(): number {
    const cycleEnd = this.usage().ai_messages?.cycle_end;
    if (!cycleEnd) return 0;
    const end = new Date(cycleEnd);
    const now = new Date();
    return Math.max(0, Math.ceil((end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24)));
  }

  /** Retorna a data de término do trial formatada (ex.: "30/jun/2026"). */
  get trialEndFormatted(): string {
    const cycleEnd = this.usage().ai_messages?.cycle_end;
    if (!cycleEnd) return '';
    const [year, month, day] = cycleEnd.split('-').map(Number);
    const months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    const monthName = months[(month ?? 1) - 1];
    return `${day}/${monthName}/${year}`;
  }

  /** Despacha evento global para abrir o modal de upgrade rápido. */
  openUpgradeModal(): void {
    document.dispatchEvent(new CustomEvent('open-quick-upgrade-modal'));
  }

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

  /**
   * Formata uma data ISO para formato curto (ex.: "30/jun").
   * @param isoDate Data no formato ISO 8601
   * @returns Data formatada como "dia/mês"
   */
  protected formatDateShort(isoDate: string): string {
    const [, month, day] = isoDate.split('-').map(Number);
    const months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    const monthName = months[(month ?? 1) - 1];
    return `${day}/${monthName}`;
  }

  /**
   * Formata um valor numérico como moeda BRL (R$).
   * @param value Valor a formatar
   * @returns String formatada em reais ou string vazia para valores nulos
   */
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
