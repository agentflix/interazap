import { Injectable, computed, signal } from '@angular/core';
import type { BillingLockoutData } from '@core/models/billing-status.model';
export type { BillingLockoutData, BillingLockoutInvoice } from '@core/models/billing-status.model';


/**
 * Armazena o estado de bloqueio de cobrança, utilizado pelo interceptor e pela UI de lockout.
 *
 * Mantém os dados de lockout retornados pela API quando o tenant possui faturas em atraso,
 * bloqueando acesso e exibindo prompts de pagamento.
 */
@Injectable({ providedIn: 'root' })
export class BillingStatusService {
  private readonly lockoutState = signal<BillingLockoutData | null>(null);

  readonly lockoutData = this.lockoutState.asReadonly();
  readonly isLocked = computed(() => this.lockoutState() !== null);

  /** Define os dados de bloqueio do tenant a partir da resposta da API. */
  setLocked(data: BillingLockoutData): void {
    this.lockoutState.set(data);
  }

  /** Limpa o estado de bloqueio quando o usuário faz logout ou o tenant é desbloqueado. */
  clearLockout(): void {
    this.lockoutState.set(null);
  }
}
