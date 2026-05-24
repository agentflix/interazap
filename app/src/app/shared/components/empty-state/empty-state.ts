import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { NgIcon } from '@ng-icons/core';

/**
 * Empty state placeholder for pages/sections with no data.
 *
 * @example
 * ```html
 * <af-empty-state
 *   title="No contacts yet"
 *   description="Add your first contact to get started."
 * >
 *   <button af-button>+ New Contact</button>
 * </af-empty-state>
 * ```
 */
@Component({
  selector: 'af-empty-state',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './empty-state.html',
  imports: [NgIcon],
})
export class AfEmptyStateComponent {
  /** Empty state heading */
  readonly title = input.required<string>();

  /** Optional description text */
  readonly description = input<string | null>(null);

  /** Whether a custom icon is projected */
  readonly icon = input(false);

  /** Lucide icon name to render (e.g. "lucideUser"). Overrides default SVG when set. */
  readonly iconName = input<string | null>(null);
}
