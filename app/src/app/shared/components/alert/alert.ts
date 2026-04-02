import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfAlertComponent — Contextual alert/notification banner.
 *
 * @example
 * ```html
 * <af-alert variant="warning" title="Atenção" message="Suas credenciais expiram em 3 dias." [dismissible]="true" />
 * ```
 */
@Component({
  selector: 'af-alert',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './alert.html',
})
export class AfAlertComponent {
  /** Alert variant */
  readonly variant = input<'info' | 'success' | 'warning' | 'danger'>('info');

  /** Title text */
  readonly title = input('');

  /** Message text */
  readonly message = input('');

  /** Show dismiss button */
  readonly dismissible = input(false);

  /** Emitted on dismiss */
  readonly dismissed = output<void>();

  protected readonly iconName = computed(() => {
    const map: Record<string, string> = {
      info: 'circle-info',
      success: 'circle-check',
      warning: 'alert-triangle',
      danger: 'circle-alert',
    };
    return map[this.variant()];
  });

  protected readonly alertClasses = computed(() => {
    const base = 'rounded-lg px-4 py-3 border';
    const variants: Record<string, string> = {
      info: 'bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300',
      success:
        'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800 text-green-800 dark:text-green-300',
      warning:
        'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300',
      danger:
        'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-800 text-red-800 dark:text-red-300',
    };
    return `${base} ${variants[this.variant()]}`;
  });
}
