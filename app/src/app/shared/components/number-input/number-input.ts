import { Component, ChangeDetectionStrategy, computed, input, output, signal, effect } from '@angular/core';
import { type FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { AfIconButtonComponent } from '../icon-button/icon-button';
import { LucideAngularModule } from 'lucide-angular';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * Campo numérico com botões de incremento e decremento.
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
  /** FormControl do campo */
  readonly control = input.required<FormControl<number>>();

  /** Rótulo do campo */
  readonly label = input('');

  /** Valor mínimo */
  readonly min = input<number | null>(null);

  /** Valor máximo */
  readonly max = input<number | null>(null);

  /** Incremento por passo */
  readonly step = input(1);

  /** Campo obrigatório */
  readonly required = input(false);

  /** Mensagem de erro */
  readonly errorMessage = input('');

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa o espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
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

  protected readonly isRequired = computed(() => {
    this.controlRevision();
    return this.required() || !!this.control()?.hasValidator(Validators.required);
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
