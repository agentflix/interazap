import {
  type ElementRef,
  type AfterViewInit,
  Component,
  ChangeDetectionStrategy,
  computed,
  input,
  viewChild,
  forwardRef,
  effect,
  inject,
  DestroyRef,
} from '@angular/core';
import {
  type ControlValueAccessor,
  NG_VALUE_ACCESSOR,
  ReactiveFormsModule,
  FormControl,
} from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfInputSize } from './currency-input.model';
export * from './currency-input.model';

/**
 * AfCurrencyInputComponent — Masked currency input for BRL (R$) values.
 * Implements ControlValueAccessor. Value is stored as number (cents / raw).
 *
 * @example
 * ```html
 * <af-currency-input [control]="priceCtrl" label="Preço" />
 * ```
 */
@Component({
  selector: 'af-currency-input, app-currency-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [
    {
      provide: NG_VALUE_ACCESSOR,
      useExisting: forwardRef(() => AfCurrencyInputComponent),
      multi: true,
    },
  ],
  templateUrl: './currency-input.html',
})
export class AfCurrencyInputComponent implements ControlValueAccessor, AfterViewInit {
  private readonly destroyRef = inject(DestroyRef);

  /** FormControl binding */
  readonly control = input<FormControl<number | null>>();

  /** Field label */
  readonly label = input('');

  /** Placeholder text */
  readonly placeholder = input('0,00');

  /** Whether field is required */
  readonly required = input(false);

  /** Error message */
  readonly errorMessage = input('Valor inválido.');

  /** data-test attribute for E2E tests */
  readonly dataTest = input<string | null>(null);

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  /** Currency prefix */
  readonly prefix = input('R$');

  /** Decimal separator */
  readonly decimalSeparator = input<'.' | ','>(',');

  /** Thousands separator */
  readonly thousandsSeparator = input<'.' | ','>('.');

  /** Input size: sm for compact fields, md for the default comfortable field */
  readonly size = input<AfInputSize>('md');

  /** Whether to show error */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Dynamic CSS classes for wrapper based on size */
  protected readonly wrapperClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'h-8' : 'h-10';
    return [
      'flex items-center rounded-md border border-neutral-300 dark:border-neutral-600',
      'bg-white dark:bg-neutral-900',
      'focus-within:ring-2 focus-within:ring-accent-500/30 focus-within:border-accent-500',
      'transition-colors',
      sizeClasses,
    ].join(' ');
  });

  /** Dynamic CSS classes for prefix based on size */
  protected readonly prefixClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'px-2 text-xs' : 'px-3 text-sm';
    return [
      sizeClasses,
      'text-neutral-500 dark:text-neutral-400 select-none border-r',
      'border-neutral-300 dark:border-neutral-600',
    ].join(' ');
  });

  /** Dynamic CSS classes for input based on size */
  protected readonly inputClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'px-2 py-1.5 text-xs' : 'px-3 py-2 text-sm';
    return [
      'flex-1 bg-transparent border-0 text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'focus:outline-none focus:ring-0',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      sizeClasses,
    ].join(' ');
  });

  /** Whether to show error */
  protected readonly showError = computed(() => {
    const ctrl = this.control();
    return !!ctrl && ctrl.invalid && ctrl.touched;
  });

  protected readonly innerControl = new FormControl('', { nonNullable: true });
  protected readonly inputRef = viewChild<ElementRef<HTMLInputElement>>('inputEl');

  private onChange: (val: number | null) => void = () => {};
  protected onTouched: () => void = () => {};

  constructor() {
    effect(() => {
      const ctrl = this.control();
      if (!ctrl) return;

      ctrl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((value) => {
        const formatted = value !== null ? this.formatValue(value) : '';
        if (this.innerControl.value !== formatted) {
          this.innerControl.setValue(formatted, { emitEvent: false });
        }
      });
    });
  }

  ngAfterViewInit(): void {
    const ctrl = this.control();
    if (ctrl && ctrl.value !== null) {
      this.innerControl.setValue(this.formatValue(ctrl.value));
    }
  }

  writeValue(value: number | null): void {
    this.innerControl.setValue(value !== null ? this.formatValue(value) : '');
  }

  registerOnChange(fn: (val: number | null) => void): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    if (isDisabled) {
      this.innerControl.disable();
    } else {
      this.innerControl.enable();
    }
  }

  protected onInput(event: Event): void {
    const raw = (event.target as HTMLInputElement).value.replace(/[^\d]/g, '');
    const numeric = raw.length > 0 ? parseInt(raw, 10) / 100 : null;

    if (numeric !== null) {
      const formatted = this.formatValue(numeric);
      this.innerControl.setValue(formatted, { emitEvent: false });

      const el = this.inputRef()?.nativeElement;
      if (el) {
        el.value = formatted;
        el.setSelectionRange(formatted.length, formatted.length);
      }
    }

    this.onChange(numeric);
    const ctrl = this.control();
    if (ctrl) {
      ctrl.setValue(numeric, { emitEvent: true });
    }
  }

  private formatValue(value: number): string {
    const dec = this.decimalSeparator();
    const thou = this.thousandsSeparator();
    const [intPart, decPart] = value.toFixed(2).split('.');
    const formatted = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thou);
    return `${formatted}${dec}${decPart}`;
  }
}

export const CurrencyInputComponent = AfCurrencyInputComponent;
