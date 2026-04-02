import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Avatar component for user images or initials.
 *
 * @example
 * ```html
 * <af-avatar src="/img/user.jpg" alt="John Doe" size="md" />
 * <af-avatar initials="JD" size="lg" />
 * <af-avatar size="sm" />  <!-- Fallback icon -->
 * ```
 */
@Component({
  selector: 'af-avatar',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './avatar.html',
})
export class AfAvatarComponent {
  /** Image source URL */
  readonly src = input<string | null>(null);

  /** Image alt text */
  readonly alt = input('');

  /** Initials to show when no image */
  readonly initials = input<string | null>(null);

  /** Avatar diameter */
  readonly size = input<'xs' | 'sm' | 'md' | 'lg' | 'xl'>('md');

  protected readonly avatarClasses = computed(() => {
    const base = [
      'inline-flex items-center justify-center',
      'rounded-full overflow-hidden',
      'bg-primary-900/10 text-primary-900',
      'dark:bg-primary-400/20 dark:text-primary-300',
      'shrink-0',
    ].join(' ');

    const sizes: Record<string, string> = {
      xs: 'size-6',
      sm: 'size-8',
      md: 'size-10',
      lg: 'size-12',
      xl: 'size-16',
    };

    return `${base} ${sizes[this.size()]}`;
  });

  protected readonly textSizeClass = computed(() => {
    const sizes: Record<string, string> = {
      xs: 'text-[10px]',
      sm: 'text-xs',
      md: 'text-sm',
      lg: 'text-base',
      xl: 'text-lg',
    };
    return sizes[this.size()];
  });

  protected onImageError(event: Event): void {
    (event.target as HTMLImageElement).style.display = 'none';
  }
}
