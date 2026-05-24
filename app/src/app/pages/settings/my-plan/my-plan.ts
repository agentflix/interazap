import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { forkJoin } from 'rxjs';
import { AfButtonComponent, AfEmptyStateComponent, AfPageTitleComponent } from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { BillingSubscriptionService } from '@core/services/billing-subscription.service';
import { PlanCardComponent } from './components/plan-card/plan-card';
import { UsageStatsComponent } from './components/usage-stats/usage-stats';
import { UpgradeModalComponent } from './components/upgrade-modal/upgrade-modal';
import { DowngradeModalComponent } from './components/downgrade-modal/downgrade-modal';
import { BillingPrefsModalComponent } from './components/billing-prefs-modal/billing-prefs-modal';
import {
  type PlanChangePreview,
  type SubscriptionPlan,
  type SubscriptionSummary,
} from '@shared/models/subscription.model';

/**
 * Página Meu Plano para gestão self-service de assinatura do tenant.
 */
@Component({
  selector: 'app-my-plan',
  standalone: true,
  imports: [
    AfPageTitleComponent,
    AfEmptyStateComponent,
    AfButtonComponent,
    PlanCardComponent,
    UsageStatsComponent,
    UpgradeModalComponent,
    DowngradeModalComponent,
    BillingPrefsModalComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './my-plan.html',
})
export class MyPlanPage {
  private readonly currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  private readonly dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });

  private readonly service = inject(BillingSubscriptionService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly router = inject(Router);

  readonly loading = signal(true);
  readonly changingPlan = signal(false);
  readonly error = signal<string | null>(null);

  readonly subscription = signal<SubscriptionSummary | null>(null);
  readonly plans = signal<SubscriptionPlan[]>([]);

  readonly selectedPlan = signal<SubscriptionPlan | null>(null);
  readonly preview = signal<PlanChangePreview | null>(null);
  readonly modalOpen = signal(false);
  readonly billingPrefsOpen = signal(false);

  readonly isEmpty = computed(
    () => !this.loading() && this.error() === null && this.subscription() === null,
  );
  readonly currentPlanPrice = computed(() =>
    Number(this.subscription()?.current_plan?.price_monthly ?? 0),
  );

  constructor() {
    this.load();
  }

  /**
   * Recarrega dados da página.
   */
  protected load(): void {
    this.loading.set(true);
    this.error.set(null);

    forkJoin({
      subscription: this.service.getSubscription(),
      plans: this.service.getPlans(),
    })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: ({ subscription, plans }) => {
          this.subscription.set(subscription.data);
          this.plans.set(plans.data ?? []);
          this.loading.set(false);
        },
        error: () => {
          this.error.set('Não foi possível carregar dados do plano.');
          this.loading.set(false);
        },
      });
  }

  /**
   * Abre modal após consultar preview da troca.
   */
  protected openPlanChange(plan: SubscriptionPlan): void {
    this.selectedPlan.set(plan);
    this.preview.set(null);
    this.modalOpen.set(true);

    this.service
      .previewPlanChange(plan.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.preview.set(response.data);
        },
        error: () => {
          this.toast.error('Não foi possível gerar preview da troca de plano.');
          this.closeModal();
        },
      });
  }

  protected openBillingPrefs(): void {
    this.billingPrefsOpen.set(true);
  }

  protected onBillingPrefsSaved(): void {
    this.billingPrefsOpen.set(false);
    this.load();
  }

  /**
   * Fecha qualquer modal de troca.
   */
  protected closeModal(): void {
    this.modalOpen.set(false);
    this.preview.set(null);
    this.selectedPlan.set(null);
  }

  /**
   * Confirma alteração de plano com senha atual.
   */
  protected confirmPlanChange(currentPassword: string): void {
    const plan = this.selectedPlan();

    if (plan === null) {
      return;
    }

    this.changingPlan.set(true);

    this.service
      .changePlan(plan.id, currentPassword)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.toast.success(response.data.message);
          this.changingPlan.set(false);
          this.closeModal();
          this.load();

          if (response.data.type === 'upgrade') {
            void this.router.navigate(['/billing/invoices']);
          }
        },
        error: (err: {
          error?: { message?: string; errors?: { current_password?: string[] } };
        }) => {
          const validationMessage = err.error?.errors?.current_password?.[0];
          const fallbackMessage = err.error?.message ?? 'Não foi possível alterar o plano.';
          this.toast.error(validationMessage ?? fallbackMessage);
          this.changingPlan.set(false);
        },
      });
  }

  protected formatCurrencyBRL(value: string | number | null | undefined): string {
    const normalized = Number(value ?? 0);

    if (Number.isNaN(normalized)) {
      return this.currencyFormatter.format(0);
    }

    return this.currencyFormatter.format(normalized);
  }

  protected formatDatePtBr(value: string | null | undefined): string {
    if (value === null || value === undefined || value.trim().length === 0) {
      return '-';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
      return value;
    }

    return this.dateFormatter.format(parsed);
  }
}

export default MyPlanPage;
