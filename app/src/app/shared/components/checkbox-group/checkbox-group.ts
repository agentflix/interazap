import { Component, ChangeDetectionStrategy, input, effect } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';

import type { AfCheckboxOption } from './checkbox-group.model';
export * from './checkbox-group.model';



/**
 * Grupo de checkboxes para seleção múltipla com array de valores.
 *
 * Renderiza múltiplos checkboxes onde cada seleção adiciona ou remove
 * o valor do array mantido pelo FormControl.
 *
 * @example
 * ```html
 * <af-checkbox-group
 *   [control]="form.controls.roles"
 *   [options]="roleOptions"
 *   label="Perfis"
 * />
 * ```
 */
@Component({
  selector: 'af-checkbox-group, app-checkbox-group',
  standalone: true,
  imports: [ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './checkbox-group.html',
})
export class AfCheckboxGroupComponent {
  /** FormControl que armazena o array de valores selecionados */
  readonly control = input.required<FormControl<string[]>>();

  /** Opções de checkbox disponíveis */
  readonly options = input.required<readonly AfCheckboxOption[]>();

  /** Prefixo data-test para cada checkbox */
  readonly dataTest = input<string>('checkbox-group');

  /** Rótulo do grupo */
  readonly label = input<string>();

  /** Verifica se um valor está selecionado */
  protected isSelected(value: string): boolean {
    const selectedValues = this.control().value ?? [];
    return selectedValues.includes(value);
  }

  /** Alterna um valor no array ao clicar */
  protected toggle(value: string, event: Event): void {
    event.stopPropagation();
    this.setValue(value);
  }

  /** Alterna via tecla Space — previne rolagem da página */
  protected toggleSpace(value: string): void {
    this.setValue(value);
  }

  private setValue(value: string): void {
    const current = this.control().value ?? [];
    if (current.includes(value)) {
      this.control().setValue(current.filter((v) => v !== value));
    } else {
      this.control().setValue([...current, value]);
    }
    this.control().markAsTouched();
  }

  /** Classes dinâmicas do checkbox baseadas no estado marcado */
  protected boxClasses(value: string): string {
    const isChecked = this.isSelected(value);
    const base = [
      'mt-0.5 size-4 shrink-0 rounded border cursor-pointer',
      'flex items-center justify-center',
      'transition-colors duration-150',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:ring-offset-0',
    ];
    const state = isChecked
      ? 'bg-accent-500 border-accent-500'
      : 'bg-white dark:bg-neutral-900 border-neutral-300 dark:border-neutral-600';
    return [...base, state].join(' ');
  }
}
