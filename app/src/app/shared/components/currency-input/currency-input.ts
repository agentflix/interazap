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
  signal,
  DestroyRef,
} from '@angular/core';
import {
  type ControlValueAccessor,
  NG_VALUE_ACCESSOR,
  ReactiveFormsModule,
  FormControl,
  Validators,
} from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfInputSize } from './currency-input.model';
export * from './currency-input.model';

/**
 * Campo monetário com máscara para valores em BRL (R$).
 *
 * Implementa ControlValueAccessor. O valor é armazenado como número (centavos/bruto).
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

  /** FormControl do campo */
  readonly control = input<FormControl<number | null>>();

  /** Rótulo do campo */
  readonly label = input('');

  /** Texto placeholder */
  readonly placeholder = input('0,00');

  /** Indica se o campo é obrigatório */
  readonly required = input(false);

  /** Mensagem de erro */
  readonly errorMessage = input('Valor inválido.');

  /** Atributo data-test para testes E2E */
  readonly dataTest = input<string | null>(null);

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa o espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
  readonly helpText = input<string>();

  /** Prefixo da moeda */
  readonly prefix = input('R$');

  /** Separador decimal */
  readonly decimalSeparator = input<'.' | ','>(',');

  /** Separador de milhar */
  readonly thousandsSeparator = input<'.' | ','>('.');

  /** Tamanho do campo: sm para compacto, md para o padrão */
  readonly size = input<AfInputSize>('md');

  /** Classes CSS do contêiner */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Classes CSS dinâmicas do wrapper baseadas no tamanho */
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

  /** Classes CSS dinâmicas do prefixo baseadas no tamanho */
  protected readonly prefixClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'px-2 text-xs' : 'px-3 text-sm';
    return [
      sizeClasses,
      'text-neutral-500 dark:text-neutral-400 select-none border-r',
      'border-neutral-300 dark:border-neutral-600',
    ].join(' ');
  });

  /** Classes CSS dinâmicas do input baseadas no tamanho */
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

  private readonly controlRevision = signal(0);

  /** Indica se o erro deve ser exibido */
  protected readonly showError = computed(() => {
    this.controlRevision();
    const ctrl = this.control();
    return !!ctrl && ctrl.invalid && ctrl.touched;
  });

  /** Verdadeiro quando o controle possui Validators.required ou o input required está ativo */
  protected readonly isRequired = computed(() => {
    this.controlRevision();
    return this.required() || !!this.control()?.hasValidator(Validators.required);
  });

  /** Mensagem de erro: erro do servidor tem precedência sobre o input estático */
  protected readonly resolvedErrorMessage = computed(() => {
    this.controlRevision();
    const serverMsg = this.control()?.errors?.['server'];
    return typeof serverMsg === 'string' ? serverMsg : this.errorMessage();
  });

  protected readonly innerControl = new FormControl('', { nonNullable: true });
  protected readonly inputRef = viewChild<ElementRef<HTMLInputElement>>('inputEl');

  private onChange: (val: number | null) => void = () => {};
  protected onTouched: () => void = () => {};

  constructor() {
    effect((onCleanup) => {
      const ctrl = this.control();
      if (!ctrl) return;

      ctrl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((value) => {
        const formatted = value !== null ? this.formatValue(value) : '';
        if (this.innerControl.value !== formatted) {
          this.innerControl.setValue(formatted, { emitEvent: false });
        }
      });

      const eventsSub = ctrl.events.subscribe(() => {
        this.controlRevision.update((n) => n + 1);
      });
      onCleanup(() => eventsSub.unsubscribe());
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
