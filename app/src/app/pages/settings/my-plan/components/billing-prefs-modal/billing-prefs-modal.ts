import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AfButtonComponent, AfLoadingButtonComponent, AfModalComponent } from '@shared/components';
import {
  BillingPrefsService,
  type PlatformTenantBillingPrefs,
} from '@core/services/billing-prefs.service';
import { type SubscriptionPlan } from '@shared/models/subscription.model';

@Component({
  selector: 'app-billing-prefs-modal',
  standalone: true,
  imports: [AfModalComponent, AfButtonComponent, AfLoadingButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './billing-prefs-modal.html',
})
export class BillingPrefsModalComponent {
  private readonly service = inject(BillingPrefsService);
  private readonly destroyRef = inject(DestroyRef);

  readonly open = input(false);
  readonly plan = input<SubscriptionPlan | null>(null);
  readonly currentOverride = input<'stop' | 'overage' | null>(null);

  readonly closed = output<void>();
  readonly saved = output<PlatformTenantBillingPrefs>();

  readonly loading = signal(false);
  readonly errorMessage = signal<string | null>(null);
  readonly selectedMode = signal<'stop' | 'overage'>('stop');

  constructor() {
    effect(() => {
      if (this.open()) {
        const override = this.currentOverride();
        const planMode = this.plan()?.overage_mode ?? 'stop';
        this.selectedMode.set(override ?? planMode);
        this.errorMessage.set(null);
      }
    });
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

  protected save(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.service
      .updateOverageMode(this.selectedMode())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.loading.set(false);
          this.saved.emit(response.data);
          this.closed.emit();
        },
        error: (err: { error?: { message?: string } }) => {
          this.loading.set(false);
          this.errorMessage.set(err.error?.message ?? 'Não foi possível salvar as preferências.');
        },
      });
  }
}
