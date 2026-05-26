import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BillingSubscriptionService } from '@core/services/billing-subscription.service';
import type { SubscriptionSummary } from '@shared/models/subscription.model';

/**
 * Estado visual do banner de trial.
 *
 * - `normal`: azul — trial ativo com folga
 * - `alert`: amarelo — menos de 48h ou uso >= 80 mensagens
 * - `expired`: vermelho — IA pausada por expiração ou limite atingido
 * - `null`: invisível — não é trial ou carregando
 */
export type TrialBannerState = 'normal' | 'alert' | 'expired' | null;

/**
 * Banner persistente de trial para o MainLayoutComponent.
 * Exibido em todas as telas autenticadas quando o plano corrente é trial.
 *
 * Estados:
 * - `normal`:  azul — trial ativo com folga
 * - `alert`:   amarelo — <48h ou >=80 msgs
 * - `expired`: vermelho — IA pausada
 * - `null`:    invisível — não é trial ou loading
 *
 * @example
 * ```html
 * <app-trial-banner />
 * ```
 */
@Component({
  selector: 'app-trial-banner',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './trial-banner.html',
  styleUrl: './trial-banner.scss',
})
export class TrialBannerComponent implements OnInit {
  private readonly subscriptionService = inject(BillingSubscriptionService);

  subscription: SubscriptionSummary | null = null;
  isLoading = true;
  bannerState: TrialBannerState = null;

  /** Dias restantes até o fim do ciclo de trial. */
  get daysLeft(): number {
    if (!this.subscription) return 0;
    const cycleEnd = new Date(this.subscription.usage.ai_messages.cycle_end);
    const now = new Date();
    const diffMs = cycleEnd.getTime() - now.getTime();
    return Math.max(0, Math.ceil(diffMs / (1000 * 60 * 60 * 24)));
  }

  /** Quantidade de mensagens IA consumidas no ciclo atual. */
  get messageCount(): number {
    return this.subscription?.usage.ai_messages.current ?? 0;
  }

  /** Limite de mensagens IA do plano trial. */
  get messageLimit(): number {
    return this.subscription?.usage.ai_messages.limit ?? 100;
  }

  /** Percentual de uso das mensagens IA (0–100). */
  get messagePercentage(): number {
    return this.subscription?.usage.ai_messages.percentage ?? 0;
  }

  /** Data de término do ciclo de trial (ISO 8601). */
  get cycleEnd(): string {
    return this.subscription?.usage.ai_messages.cycle_end ?? '';
  }

  ngOnInit(): void {
    this.subscriptionService.getSubscription().subscribe({
      next: (response) => {
        this.subscription = response.data;
        this.isLoading = false;
        this.computeBannerState();
      },
      error: (err: unknown) => {
        this.isLoading = false;
        console.error('[TrialBanner] Failed to load subscription:', err);
      },
    });
  }

  /** Dispara evento global para abrir o modal de upgrade de plano. */
  openUpgradeModal(): void {
    // QuickUpgradeModal será injetado no template via MatDialog quando disponível
    // Por ora, emite evento para o layout abrir o modal
    document.dispatchEvent(new CustomEvent('open-quick-upgrade-modal'));
  }

  private computeBannerState(): void {
    if (!this.subscription?.current_plan?.is_trial) {
      this.bannerState = null;
      return;
    }

    const hoursLeft = this.daysLeft * 24;
    const isNearExpiry = hoursLeft <= 48;
    const isNearLimit = this.messageCount >= 80;
    const isExpired = this.daysLeft === 0 || this.messageCount >= this.messageLimit;

    if (isExpired) {
      this.bannerState = 'expired';
    } else if (isNearExpiry || isNearLimit) {
      this.bannerState = 'alert';
    } else {
      this.bannerState = 'normal';
    }
  }
}
