import { Injectable } from '@nestjs/common';
import { Counter } from 'prom-client';
import { MetricsService } from './metrics.service';

/**
 * BillingTrialMetrics
 *
 * Métricas Prometheus para tracking de signup, trial e conversões.
 *
 * Counters:
 * - `auth_signup_total{source, status}` — tentativas de signup (email | google)
 * - `billing_trial_conversions_total{plan, outcome}` — conversões trial → pago
 * - `auth_google_callback_failures_total{reason}` — falhas no callback OAuth Google
 */
@Injectable()
export class BillingTrialMetrics {
  public readonly authSignupTotal: Counter<'source' | 'status'>;
  public readonly billingTrialConversionsTotal: Counter<'plan' | 'outcome'>;
  public readonly authGoogleCallbackFailuresTotal: Counter<'reason'>;

  constructor(private readonly metricsService: MetricsService) {
    const registry = this.metricsService.getRegistry();

    this.authSignupTotal = new Counter({
      name: 'auth_signup_total',
      help: 'Total signup attempts grouped by source (email, google) and status (success, failed, duplicate)',
      labelNames: ['source', 'status'],
      registers: [registry],
    });

    this.billingTrialConversionsTotal = new Counter({
      name: 'billing_trial_conversions_total',
      help: 'Total trial to paid plan conversions grouped by plan and outcome (success, declined, error)',
      labelNames: ['plan', 'outcome'],
      registers: [registry],
    });

    this.authGoogleCallbackFailuresTotal = new Counter({
      name: 'auth_google_callback_failures_total',
      help: 'Total Google OAuth callback failures grouped by reason (oauth_error, signup_failed, token_error)',
      labelNames: ['reason'],
      registers: [registry],
    });
  }

  /**
   * Registra tentativa de signup.
   *
   * @param source - Origem: 'email' | 'google'
   * @param status - Resultado: 'success' | 'failed' | 'duplicate'
   */
  recordSignup(source: 'email' | 'google', status: 'success' | 'failed' | 'duplicate'): void {
    this.authSignupTotal.inc({ source, status });
  }

  /**
   * Registra tentativa de conversão trial → plano pago.
   *
   * @param plan    - Slug do plano (starter, professional, business)
   * @param outcome - Resultado: 'success' | 'declined' | 'error'
   */
  recordTrialConversion(plan: string, outcome: 'success' | 'declined' | 'error'): void {
    this.billingTrialConversionsTotal.inc({ plan, outcome });
  }

  /**
   * Registra falha no callback OAuth do Google.
   *
   * @param reason - Motivo: 'oauth_error' | 'signup_failed' | 'token_error'
   */
  recordGoogleCallbackFailure(reason: 'oauth_error' | 'signup_failed' | 'token_error'): void {
    this.authGoogleCallbackFailuresTotal.inc({ reason });
  }
}
