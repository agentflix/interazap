import { Component, ChangeDetectionStrategy, inject } from '@angular/core';
import { ThemeService } from '../../../core/services/theme.service';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Dark/Light theme toggler button with smooth icon rotation animation.
 *
 * @example
 * ```html
 * <af-theme-toggler />
 * ```
 */
@Component({
  selector: 'af-theme-toggler',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './theme-toggler.html',
})
export class ThemeTogglerComponent {
  protected readonly theme = inject(ThemeService);
}
