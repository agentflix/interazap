import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfChatBubbleComponent — Renders a single chat message bubble with
 * directional styling (incoming vs outgoing), timestamp, and read receipts.
 *
 * @example
 * ```html
 * <af-chat-bubble
 *   message="Olá, como posso ajudar?"
 *   timestamp="14:32"
 *   senderName="Maria"
 *   direction="in"
 * />
 * ```
 */
@Component({
  selector: 'af-chat-bubble',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './chat-bubble.html',
})
export class AfChatBubbleComponent {
  /** Message text */
  readonly message = input.required<string>();

  /** Formatted timestamp string */
  readonly timestamp = input.required<string>();

  /** Sender display name */
  readonly senderName = input<string>();

  /** Message direction: incoming or outgoing */
  readonly direction = input.required<'in' | 'out'>();

  /** Delivery status (outgoing only) */
  readonly status = input<'sent' | 'delivered' | 'read'>('sent');

  /** Wrapper alignment */
  protected readonly wrapperClasses = computed(() => {
    const base = 'flex flex-col max-w-[75%]';
    return this.direction() === 'out'
      ? `${base} items-end self-end ml-auto`
      : `${base} items-start self-start`;
  });

  /** Bubble styling varies by direction */
  protected readonly bubbleClasses = computed(() => {
    const base = 'px-3.5 py-2 rounded-2xl max-w-full';
    return this.direction() === 'out'
      ? `${base} bg-accent-500 text-white rounded-br-md`
      : `${base} bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-neutral-50 rounded-bl-md`;
  });
}
