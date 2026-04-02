import { Component, ChangeDetectionStrategy, input, output, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfAccordionItemComponent — Collapsible panel with header and content.
 *
 * @example
 * ```html
 * <af-accordion-item title="FAQ #1" [open]="true">
 *   <p>Answer content here.</p>
 * </af-accordion-item>
 * ```
 */
@Component({
  selector: 'af-accordion-item',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './accordion.html',
})
export class AfAccordionItemComponent {
  /** Panel title */
  readonly title = input.required<string>();

  /** Initial open state */
  readonly open = input(false);

  protected readonly isOpen = signal(false);

  constructor() {
    // defer reading input to init
    queueMicrotask(() => this.isOpen.set(this.open()));
  }

  protected toggle(): void {
    this.isOpen.update((v) => !v);
  }
}
