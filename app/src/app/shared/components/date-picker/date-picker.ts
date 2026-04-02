import { Component, ChangeDetectionStrategy, computed, input, output, signal } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * AfDatePickerComponent — Native date input with design system styling.
 *
 * @example
 * ```html
 * <af-date-picker [control]="dateCtrl" label="Data de início" />
 * ```
 */
@Component({
  selector: 'af-date-picker',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './date-picker.html',
})
export class AfDatePickerComponent {
  /** FormControl binding */
  readonly control = input.required<FormControl<string>>();

  /** Field label */
  readonly label = input('');

  /** Required field */
  readonly required = input(false);

  /** Minimum date (YYYY-MM-DD) */
  readonly min = input('');

  /** Maximum date (YYYY-MM-DD) */
  readonly max = input('');

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );
}
