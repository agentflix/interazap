import { Component, ChangeDetectionStrategy, computed, input, output, signal } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * Campo de data nativo com estilos do design system.
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
  /** FormControl do campo */
  readonly control = input.required<FormControl<string>>();

  /** Rótulo do campo */
  readonly label = input('');

  /** Indica se o campo é obrigatório */
  readonly required = input(false);

  /** Data mínima (AAAA-MM-DD) */
  readonly min = input('');

  /** Data máxima (AAAA-MM-DD) */
  readonly max = input('');

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa o espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
  readonly helpText = input<string>();

  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );
}
