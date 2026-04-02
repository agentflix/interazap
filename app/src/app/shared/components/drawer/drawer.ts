import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { AfIconButtonComponent } from '../icon-button/icon-button';
import { LucideAngularModule } from 'lucide-angular';
import { AfScrollAreaComponent } from '../scroll-area/scroll-area';

/**
 * AfDrawerComponent — Slide-out panel from the side of the screen.
 *
 * @example
 * ```html
 * <af-drawer [open]="drawerOpen()" (closed)="drawerOpen.set(false)" title="Filters">
 *   <p>Drawer content</p>
 * </af-drawer>
 * ```
 */
@Component({
  selector: 'af-drawer',
  standalone: true,
  imports: [AfIconButtonComponent, LucideAngularModule, AfScrollAreaComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './drawer.html',
})
export class AfDrawerComponent {
  /** Whether the drawer is open */
  readonly open = input(false);

  /** Drawer title */
  readonly title = input('');

  /** Which side the drawer opens from */
  readonly side = input<'left' | 'right'>('right');

  /** Drawer width */
  readonly size = input<'sm' | 'md' | 'lg'>('md');

  /** Emitted when the drawer should close */
  readonly closed = output<void>();
}
