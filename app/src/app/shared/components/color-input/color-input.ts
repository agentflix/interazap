import {
  type OnInit,
  type ElementRef,
  Component,
  ChangeDetectionStrategy,
  input,
  computed,
  signal,
  viewChild,
  DestroyRef,
  inject,
} from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfInputSize } from './color-input.model';
export * from './color-input.model';

/**
 * Color picker input for InteraZap UI Kit.
 *
 * @description Swatch circle + hex text input. The swatch opens the native
 * browser color picker. Both directions stay in sync via FormControl.
 *
 * @example
 * ```html
 * <af-color-input
 *   [control]="brandColorControl"
 *   label="Cor da marca"
 * />
 * ```
 */
@Component({
  selector: 'af-color-input, app-color-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './color-input.html',
})
export class AfColorInputComponent implements OnInit {
  /** FormControl for the hex color value */
  readonly control = input.required<FormControl<string>>();

  /** Label text */
  readonly label = input<string>();

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the input */
  readonly helpText = input<string>();

  /** Placeholder */
  readonly placeholder = input('#000000');

  /** Optional helper text (legacy compatibility). */
  readonly hint = input<string>();

  /** Required asterisk */
  readonly required = input(false);

  /** Error message */
  readonly errorMessage = input('Cor inválida.');

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Input size: sm or md */
  readonly size = input<AfInputSize>('sm');

  /** Current color for the swatch */
  protected readonly currentColor = signal('#000000');

  /** Unique ID */
  protected readonly inputId = `color-${Math.random().toString(36).slice(2, 9)}`;

  private readonly colorPickerRef = viewChild.required<ElementRef<HTMLInputElement>>('colorPicker');

  private readonly destroyRef = inject(DestroyRef);

  /** Whether to show error */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Dynamic CSS classes for color swatch based on size */
  protected readonly swatchClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'size-8' : 'size-10';
    return [
      'rounded-md border-2 border-neutral-300 dark:border-neutral-600',
      'cursor-pointer transition-shadow hover:ring-2 hover:ring-accent-500/30 shrink-0',
      sizeClasses,
    ].join(' ');
  });

  /** Dynamic CSS classes for hex input based on size */
  protected readonly inputClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'h-8 px-2.5 text-xs' : 'h-10 px-3 text-sm';
    return [
      'flex-1 rounded-md border border-neutral-300 dark:border-neutral-600',
      'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      'transition-colors font-mono uppercase',
      sizeClasses,
    ].join(' ');
  });

  /** Whether to show error */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);

  ngOnInit(): void {
    const ctrl = this.control();
    if (ctrl) {
      // Set initial color
      const initial = ctrl.value || '#000000';
      this.currentColor.set(initial);

      // Watch for value changes
      ctrl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((value) => {
        if (value && /^#[0-9a-fA-F]{6}$/.test(value)) {
          this.currentColor.set(value);
        }
      });
    }
  }

  /** Open native color picker */
  protected openPicker(): void {
    this.colorPickerRef().nativeElement.click();
  }

  /** Handle native color picker change */
  protected onNativeColorChange(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    this.currentColor.set(value);
    this.control().setValue(value);
    this.control().markAsTouched();
  }

  /** Handle text input change */
  protected onTextInput(): void {
    const value = this.control().value;
    if (value && /^#[0-9a-fA-F]{6}$/.test(value)) {
      this.currentColor.set(value);
    }
  }
}

export const ColorInputComponent = AfColorInputComponent;
