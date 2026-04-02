import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfNavbarComponent — Horizontal navigation bar for secondary navigation.
 *
 * @example
 * ```html
 * <af-navbar [title]="'Settings'" [showBack]="true" (back)="goBack()">
 *   <ng-content /> <!-- right-side actions -->
 * </af-navbar>
 * ```
 */
@Component({
  selector: 'af-navbar',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './navbar.html',
})
export class AfNavbarComponent {
  /** Navbar title */
  readonly title = input('');

  /** Show back arrow */
  readonly showBack = input(false);

  /** Back navigation event */
  readonly back = output<void>();
}
