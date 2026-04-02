import { Component, ChangeDetectionStrategy, computed, input, output, signal } from '@angular/core';
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
