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
 * Campo de seleção de cor com amostra circular e input de hex.
 *
 * A amostra abre o seletor de cor nativo do navegador.
 * Ambas as direções permanecem sincronizadas via FormControl.
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
  /** FormControl para o valor hexadecimal da cor */
  readonly control = input.required<FormControl<string>>();

  /** Texto do rótulo */
  readonly label = input<string>();

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa o espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
  readonly helpText = input<string>();

  /** Placeholder do campo de texto */
  readonly placeholder = input('#000000');

  /** Texto auxiliar (compatibilidade legada) */
  readonly hint = input<string>();

  /** Exibe asterisco de campo obrigatório */
  readonly required = input(false);

  /** Mensagem de erro */
  readonly errorMessage = input('Cor inválida.');

  /** Atributo data-test */
  readonly dataTest = input<string>();

  /** Tamanho do campo: sm para compacto, md para o padrão */
  readonly size = input<AfInputSize>('md');

  /** Cor atual exibida na amostra */
  protected readonly currentColor = signal('#000000');

  /** ID único do campo */
  protected readonly inputId = `color-${Math.random().toString(36).slice(2, 9)}`;

  private readonly colorPickerRef = viewChild.required<ElementRef<HTMLInputElement>>('colorPicker');

  private readonly destroyRef = inject(DestroyRef);

  /** Classes CSS do contêiner */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Classes CSS dinâmicas da amostra de cor baseadas no tamanho */
  protected readonly swatchClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'size-8' : 'size-10';
    return [
      'rounded-md border-2 border-neutral-300 dark:border-neutral-600',
      'cursor-pointer transition-shadow hover:ring-2 hover:ring-accent-500/30 shrink-0',
      sizeClasses,
    ].join(' ');
  });

  /** Classes CSS dinâmicas do campo hex baseadas no tamanho */
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

  /** Indica se o erro deve ser exibido */
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

  /** Abre o seletor de cor nativo do navegador */
  protected openPicker(): void {
    this.colorPickerRef().nativeElement.click();
  }

  /** Trata mudança no seletor de cor nativo */
  protected onNativeColorChange(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    this.currentColor.set(value);
    this.control().setValue(value);
    this.control().markAsTouched();
  }

  /** Trata mudança no campo de texto hexadecimal */
  protected onTextInput(): void {
    const value = this.control().value;
    if (value && /^#[0-9a-fA-F]{6}$/.test(value)) {
      this.currentColor.set(value);
    }
  }
}

export const ColorInputComponent = AfColorInputComponent;
