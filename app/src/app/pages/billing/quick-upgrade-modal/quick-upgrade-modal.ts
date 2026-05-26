import {
  ChangeDetectionStrategy,
  Component,
  OnInit,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { BillingSubscriptionService } from '@core/services/billing-subscription.service';
import { AsaasCheckoutService } from '@core/services/asaas-checkout.service';
import { PlanCardComponent } from '@app/pages/settings/my-plan/components/plan-card/plan-card';
import { AfAlertComponent } from '@shared/components';
import type { SubscriptionPlan, SubscriptionSummary } from '@shared/models/subscription.model';
import { environment } from '@env/environment';
import { HttpClient } from '@angular/common/http';

export type ModalStep = 'plan' | 'card' | 'processing' | 'success' | 'error';

/**
 * Modal de upgrade rápido trial → plano pago.
 *
 * Step 1: Escolha de plano (PlanCard × 3)
 * Step 2: Form de cartão (tokenizado via SDK Asaas)
 * Step 3: Processando (spinner)
 * Step 4: Sucesso (próxima fatura)
 * Step Erro: Cartão recusado (CTA "Trocar cartão")
 *
 * @remarks
 * PAN nunca trafega para o backend — apenas o token emitido pelo SDK Asaas.
 */
@Component({
  selector: 'app-quick-upgrade-modal',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    PlanCardComponent,
    AfAlertComponent,
  ],
  templateUrl: './quick-upgrade-modal.html',
  styleUrl: './quick-upgrade-modal.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class QuickUpgradeModalComponent implements OnInit {
  private readonly subscriptionService = inject(BillingSubscriptionService);
  private readonly asaasCheckout = inject(AsaasCheckoutService);
  private readonly fb = inject(FormBuilder);
  private readonly http = inject(HttpClient);

  readonly open = input(false);
  readonly closed = output<void>();
  readonly upgraded = output<void>();

  readonly step = signal<ModalStep>('plan');
  readonly plans = signal<SubscriptionPlan[]>([]);
  readonly currentSummary = signal<SubscriptionSummary | null>(null);
  readonly selectedPlan = signal<SubscriptionPlan | null>(null);
  readonly isLoadingPlans = signal(true);
  readonly isSubmitting = signal(false);
  readonly errorMessage = signal<string | null>(null);
  readonly nextBillingDate = signal<string | null>(null);

  readonly cardForm = this.fb.nonNullable.group({
    holderName: ['', [Validators.required]],
    number: ['', [Validators.required, Validators.minLength(16)]],
    expiryMonth: ['', [Validators.required, Validators.pattern(/^\d{2}$/)]],
    expiryYear: ['', [Validators.required, Validators.pattern(/^\d{4}$/)]],
    ccv: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(4)]],
    accept_terms: [false, [Validators.requiredTrue]],
  });

  /** Retorna o preço mensal do plano atual como número. */
  get currentPlanPrice(): number {
    return Number(this.currentSummary()?.current_plan?.price_monthly ?? 0);
  }

  /** Retorna a quantidade atual de mensagens IA consumidas no ciclo. */
  get messageCount(): number {
    return this.currentSummary()?.usage.ai_messages.current ?? 0;
  }

  /** Retorna o limite de mensagens IA do plano atual. */
  get messageLimit(): number {
    return this.currentSummary()?.usage.ai_messages.limit ?? 100;
  }

  ngOnInit(): void {
    this.subscriptionService.getSubscription().subscribe({
      next: (res) => {
        this.currentSummary.set(res.data);
      },
      error: (err: unknown) => console.error(err),
    });

    this.subscriptionService.getPlans().subscribe({
      next: (res) => {
        this.plans.set(res.data.filter((p) => !p.is_trial));
        this.isLoadingPlans.set(false);
      },
      error: () => {
        this.isLoadingPlans.set(false);
      },
    });
  }

  /**
   * Seleciona o plano desejado e avança para o passo de informações do cartão.
   * @param plan Plano escolhido pelo usuário
   */
  selectPlan(plan: SubscriptionPlan): void {
    this.selectedPlan.set(plan);
    this.step.set('card');
  }

  /** Volta para o passo de seleção de plano e limpa mensagem de erro. */
  goBackToPlan(): void {
    this.step.set('plan');
    this.errorMessage.set(null);
  }

  /** Emite evento de fechamento do modal. */
  close(): void {
    this.closed.emit();
  }

  /**
   * Tokeniza os dados do cartão via SDK Asaas e realiza o upgrade de plano.
   * O PAN nunca é enviado ao backend — somente o token gerado pelo SDK.
   */
  async submitCard(): Promise<void> {
    if (this.cardForm.invalid || this.isSubmitting()) return;

    this.isSubmitting.set(true);
    this.errorMessage.set(null);
    this.step.set('processing');

    const { holderName, number, expiryMonth, expiryYear, ccv } = this.cardForm.getRawValue();

    try {
      const tokenResult = await this.asaasCheckout.tokenizeCard({
        holderName,
        number,
        expiryMonth,
        expiryYear,
        ccv,
      });

      const plan = this.selectedPlan();
      if (!plan) throw new Error('Plano não selecionado.');

      this.http.post<{ data: { next_billing_date: string } }>(
        `${environment.apiUrl}/billing/upgrade-from-trial`,
        { plan_id: plan.id, card_token: tokenResult.token },
      ).subscribe({
        next: (res) => {
          this.nextBillingDate.set(res.data?.next_billing_date ?? null);
          this.isSubmitting.set(false);
          this.step.set('success');
        },
        error: (err: { error?: { message?: string } }) => {
          this.isSubmitting.set(false);
          this.errorMessage.set(err.error?.message ?? 'Erro ao processar pagamento.');
          this.step.set('error');
        },
      });
    } catch (err: unknown) {
      this.isSubmitting.set(false);
      this.errorMessage.set(err instanceof Error ? err.message : 'Erro ao tokenizar cartão.');
      this.step.set('error');
    }
  }

  /** Volta para o formulário de cartão após um erro de pagamento. */
  retryCard(): void {
    this.step.set('card');
    this.errorMessage.set(null);
  }

  /** Emite eventos de upgrade concluído e fecha o modal após tela de sucesso. */
  closeAfterSuccess(): void {
    this.upgraded.emit();
    this.closed.emit();
  }
}
