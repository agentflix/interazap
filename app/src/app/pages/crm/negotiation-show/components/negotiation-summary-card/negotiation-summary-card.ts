import {
  type OnChanges,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  input,
  output,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { type Negotiation } from 'src/app/core/services/negotiation.service';
import { type Funnel, type FunnelStep } from 'src/app/core/services/funnel.service';
import { ButtonComponent } from '@shared/components/buttons';
import {
  type SelectOption,
  SelectInputComponent,
  TextInputComponent,
} from '@shared/components/inputs';
import { formatCurrency } from '@shared/utils/currency';
import { isSameId } from '../../negotiation-show.utils';

/**
 * Cartão resumo na sidebar com seletores inline para edição dos detalhes da negociação.
 */
@Component({
  selector: 'app-negotiation-summary-card',
  standalone: true,
  imports: [ReactiveFormsModule, ButtonComponent, SelectInputComponent, TextInputComponent],
  templateUrl: './negotiation-summary-card.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'flex' },
})
export class NegotiationSummaryCardComponent implements OnChanges {
  private readonly destroyRef = inject(DestroyRef);

  readonly negotiation = input<Negotiation | null>(null);
  readonly funnels = input<Funnel[]>([]);
  readonly steps = input<FunnelStep[]>([]);
  readonly isUpdatingDetails = input(false);

  readonly funnelChanged = output<string | number>();
  readonly stepChanged = output<string | number>();
  readonly expectedCloseDateChanged = output<string>();
  readonly editRequested = output<void>();

  readonly expectedCloseControl = new FormControl('', { nonNullable: true });
  readonly funnelControl = new FormControl('', { nonNullable: true });
  readonly stepControl = new FormControl('', { nonNullable: true });

  readonly hasNegotiation = computed(() => !!this.negotiation());
  readonly funnelOptions = computed<SelectOption[]>(() =>
    this.funnels().map((funnel) => ({ label: funnel.name, value: String(funnel.id) })),
  );
  readonly stepOptions = computed<SelectOption[]>(() =>
    this.steps().map((step) => ({ label: step.name, value: String(step.id) })),
  );

  constructor() {
    this.expectedCloseControl.valueChanges
      .pipe(debounceTime(1000), distinctUntilChanged(), takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.expectedCloseDateChanged.emit(value || ''));

    this.funnelControl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((value) => {
      if (value) {
        this.funnelChanged.emit(value);
      }
    });

    this.stepControl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((value) => {
      if (value) {
        this.stepChanged.emit(value);
      }
    });
  }

  ngOnChanges(): void {
    const current = this.negotiation();
    this.expectedCloseControl.setValue(
      current?.expected_close_date ? this.formatDateInput(current.expected_close_date) : '',
      {
        emitEvent: false,
      },
    );

    this.funnelControl.setValue(current?.funnel_id ? String(current.funnel_id) : '', {
      emitEvent: false,
    });

    this.stepControl.setValue(current?.step_id ? String(current.step_id) : '', {
      emitEvent: false,
    });
  }

  onFunnelChange(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    if (!target || !target.value) return;
    this.funnelChanged.emit(target.value);
  }

  onStepChange(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    if (!target || !target.value) return;
    this.stepChanged.emit(target.value);
  }

  formatCurrency(value?: number | null): string {
    return formatCurrency(value);
  }

  isSameId(left?: string | number | null, right?: string | number | null): boolean {
    return isSameId(left, right);
  }

  private formatDateInput(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toISOString().split('T')[0];
  }
}
