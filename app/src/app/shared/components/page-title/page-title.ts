import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Page title with optional subtitle and breadcrumb area.
 *
 * @example
 * ```html
 * <af-page-title title="Contacts" subtitle="Manage your CRM contacts">
 *   <button>+ New Contact</button>
 * </af-page-title>
 * ```
 */
@Component({
  selector: 'af-page-title, app-page-title',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './page-title.html',
})
export class AfPageTitleComponent {
  /** Page heading text */
  readonly title = input.required<string>();

  /** Optional description below the title */
  readonly subtitle = input<string | null>(null);

  /** Heading visual weight */
  readonly size = input<'sm' | 'md' | 'lg'>('md');

  protected readonly titleClasses = computed(() => {
    const base = 'font-bold tracking-tight text-neutral-900 dark:text-neutral-50';
    const sizes: Record<string, string> = {
      sm: 'text-lg',
      md: 'text-xl',
      lg: 'text-2xl',
    };
    return `${base} ${sizes[this.size()]}`;
  });
}

export const PageTitle = AfPageTitleComponent;
