import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { AfAvatarComponent } from '../avatar/avatar';
import { AfBadgeComponent } from '../badge/badge';

/**
 * AfChatListItemComponent — A single conversation item in the chat inbox list.
 * Displays avatar, name, last message preview, timestamp, and unread count.
 *
 * @example
 * ```html
 * <af-chat-list-item
 *   name="Maria Silva"
 *   lastMessage="Obrigada pela ajuda!"
 *   timestamp="14:32"
 *   [unreadCount]="3"
 *   [online]="true"
 *   (selected)="openChat(contact.id)"
 * />
 * ```
 */
@Component({
  selector: 'af-chat-list-item',
  standalone: true,
  imports: [AfAvatarComponent, AfBadgeComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './chat-list-item.html',
})
export class AfChatListItemComponent {
  /** Contact/conversation name */
  readonly name = input.required<string>();

  /** Avatar image URL */
  readonly avatarUrl = input<string | null>(null);

  /** Preview of the last message */
  readonly lastMessage = input('');

  /** Timestamp string */
  readonly timestamp = input('');

  /** Number of unread messages */
  readonly unreadCount = input(0);

  /** Whether the contact is online */
  readonly online = input(false);

  /** Whether this item is the active/selected conversation */
  readonly active = input(false);

  /** Emitted when the item is clicked */
  readonly selected = output<void>();

  /** Generate initials from name */
  protected readonly initials = computed(() => {
    const parts = this.name().trim().split(' ');
    if (parts.length >= 2) {
      return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
    }
    return this.name().slice(0, 2).toUpperCase();
  });

  /** Dynamic classes based on active state */
  protected readonly itemClasses = computed(() => {
    const base =
      'w-full flex items-center gap-3 px-4 py-3 text-left transition-colors cursor-pointer border-b border-neutral-100 dark:border-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent-500/50';
    const activeClass = this.active()
      ? 'bg-accent-50 dark:bg-accent-950/30'
      : 'hover:bg-neutral-50 dark:hover:bg-neutral-800/50';
    return `${base} ${activeClass}`;
  });
}
