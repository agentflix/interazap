import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfSidebarItemComponent — Single navigation item for sidebars.
 *
 * @example
 * ```html
 * <af-sidebar-item icon="home" label="Dashboard" [active]="true" href="/dashboard" />
 * ```
 */
@Component({
  selector: 'af-sidebar-item',
  standalone: true,
  imports: [LucideAngularModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './sidebar-item.html',
})
export class AfSidebarItemComponent {
  /** Lucide icon name */
  readonly icon = input<string>();

  /** Item label */
  readonly label = input('');

  /** Navigation href */
  readonly href = input('#');

  /** Whether this item is active */
  readonly active = input(false);

  /** Whether sidebar is collapsed (icon-only mode) */
  readonly collapsed = input(false);

  /** Optional badge count */
  readonly badge = input<number | string>();
}
