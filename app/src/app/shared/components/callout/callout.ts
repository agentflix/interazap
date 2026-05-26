import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Bloco de destaque para notas e avisos importantes.
 *
 * Contexto: utilizado em documentação inline, telas de configuração e
 * qualquer área que exija chamar atenção para informações críticas.
 *
 * @example
 * ```html
 * <af-callout variant="info" title="Nota">
 *   <p>Este recurso está em fase beta.</p>
 * </af-callout>
 * ```
 */
@Component({
  selector: 'af-callout',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './callout.html',
})
export class AfCalloutComponent {
  /** Variante visual do callout */
  readonly variant = input<'info' | 'success' | 'warning' | 'danger'>('info');

  /** Título exibido no cabeçalho do callout */
  readonly title = input('');

  protected readonly iconName = computed(() => {
    const map: Record<string, string> = {
      info: 'lightbulb',
      success: 'check',
      warning: 'alert-triangle',
      danger: 'shield-alert',
    };
    return map[this.variant()];
  });

  protected readonly calloutClasses = computed(() => {
    const base = 'rounded-lg px-4 py-3 border-l-4';
    const variants: Record<string, string> = {
      info: 'bg-blue-50 dark:bg-blue-950/20 border-blue-500 text-blue-800 dark:text-blue-300',
      success:
        'bg-green-50 dark:bg-green-950/20 border-green-500 text-green-800 dark:text-green-300',
      warning:
        'bg-amber-50 dark:bg-amber-950/20 border-amber-500 text-amber-800 dark:text-amber-300',
      danger: 'bg-red-50 dark:bg-red-950/20 border-red-500 text-red-800 dark:text-red-300',
    };
    return `${base} ${variants[this.variant()]}`;
  });
}
