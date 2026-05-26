import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BillingSubscriptionService } from '@core/services/billing-subscription.service';

/**
 * Banner inline "IA pausada" exibido no histórico de conversa quando
 * o tenant em trial atingiu o limite de mensagens ou o prazo expirou.
 *
 * Inserido pelo `ChatConversationComponent` após a última mensagem
 * quando `is_trial && allowed=false`.
 *
 * Variantes:
 * - `time`: trial expirou por prazo (cycle_end <= now)
 * - `messages`: trial expirou por cota (message_count >= limit)
 *
 * @example
 * ```html
 * <app-chat-ai-paused-banner variant="time" />
 * ```
 */
@Component({
  selector: 'app-chat-ai-paused-banner',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './chat-ai-paused-banner.html',
  styleUrl: './chat-ai-paused-banner.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChatAiPausedBannerComponent {
  variant: 'time' | 'messages' = 'time';

  get title(): string {
    return this.variant === 'time'
      ? 'Período trial encerrado'
      : 'Limite de mensagens atingido';
  }

  get description(): string {
    return this.variant === 'time'
      ? 'Seu trial de 7 dias expirou. A IA está pausada.'
      : 'Você utilizou todas as 100 mensagens IA do trial. A IA está pausada.';
  }

  openUpgradeModal(): void {
    document.dispatchEvent(new CustomEvent('open-quick-upgrade-modal'));
  }
}
