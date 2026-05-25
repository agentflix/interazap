import { Component, ChangeDetectionStrategy, computed, input, output, signal, effect } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { AfIconButtonComponent } from '../icon-button/icon-button';
import { LucideAngularModule } from 'lucide-angular';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * AfNumberInputComponent — Numeric input with increment/decrement buttons.
 *
 * @example
 * ```html
 * <af-number-input [control]="qtyCtrl" label="Quantidade" [min]="0" [max]="100" [step]="1" />
 * ```
 */
@Component({
  selector: 'af-number-input',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfFormLabelComponent,
    AfFormErrorComponent,
    AfIconButtonComponent,
    LucideAngularModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './number-input.html',
})
export class AfNumberInputComponent {
  /** FormControl binding */
  readonly control = input.required<FormControl<number>>();

  /** Field label */
  readonly label = input('');

  /** Minimum value */
  readonly min = input<number | null>(null);

  /** Maximum value */
  readonly max = input<number | null>(null);

  /** Step increment */
  readonly step = input(1);

  /** Required */
  readonly required = input(false);

  /** Error message */
  readonly errorMessage = input('');

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  private readonly controlRevision = signal(0);

  constructor() {
    effect((onCleanup) => {
      const subscription = this.control().events.subscribe(() => {
        this.controlRevision.update((n) => n + 1);
      });
      onCleanup(() => subscription.unsubscribe());
    });
  }

  protected readonly showError = computed(() => {
    this.controlRevision();
    return !!this.control()?.invalid && !!this.control()?.touched;
  });

  protected readonly resolvedErrorMessage = computed(() => {
    this.controlRevision();
    const serverMsg = this.control()?.errors?.['server'];
    return typeof serverMsg === 'string' ? serverMsg : this.errorMessage();
  });

  protected isAtMin(): boolean {
    const m = this.min();
    return m !== null && this.control().value <= m;
  }

  protected isAtMax(): boolean {
    const m = this.max();
    return m !== null && this.control().value >= m;
  }

  protected increment(): void {
    const val = this.control().value + this.step();
    const m = this.max();
    this.control().setValue(m !== null ? Math.min(val, m) : val);
  }

  protected decrement(): void {
    const val = this.control().value - this.step();
    const m = this.min();
    this.control().setValue(m !== null ? Math.max(val, m) : val);
  }
}
