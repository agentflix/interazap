import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfBannerComponent — Full-width banner notification (top of page).
 *
 * @example
 * ```html
 * <af-banner variant="info" message="Nova versão disponível." [dismissible]="true" />
 * ```
 */
@Component({
  selector: 'af-banner',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './banner.html',
})
export class AfBannerComponent {
  /** Variant */
  readonly variant = input<'info' | 'success' | 'warning' | 'danger'>('info');

  /** Banner message */
  readonly message = input('');

  /** Optional action button label */
  readonly actionLabel = input('');

  /** Dismissible */
  readonly dismissible = input(false);

  /** Action button clicked */
  readonly actionClicked = output<void>();

  /** Dismiss clicked */
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

  protected readonly bannerClasses = computed(() => {
    const variants: Record<string, string> = {
      info: 'bg-blue-600 text-white',
      success: 'bg-accent-600 text-white',
      warning: 'bg-amber-500 text-white',
      danger: 'bg-red-600 text-white',
    };
    return `w-full ${variants[this.variant()]}`;
  });
}
