import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfChatBubbleComponent — Renders a single chat message bubble with
 * directional styling (incoming vs outgoing), timestamp, and read receipts.
 * Supports text, image, video, audio, and document/file types.
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

  /** Message type — controls how media is rendered */
  readonly type = input<'text' | 'image' | 'video' | 'audio' | 'file' | 'document'>('text');

  /** Media file URL (used when type is not 'text') */
  readonly fileUrl = input<string | undefined>(undefined);

  /** MIME type of the media (e.g. 'image/jpeg', 'video/mp4') */
  readonly mimeType = input<string | undefined>(undefined);

  /** Original file name (for file/document messages) */
  readonly fileName = input<string | undefined>(undefined);

  /** Display name for the file — uses fileName input, falls back to message content or URL basename */
  protected readonly displayFileName = computed(() => {
    const name = this.fileName()?.trim() || this.message()?.trim();
    if (name) return name;
    const url = this.fileUrl();
    if (url) {
      try {
        return url.split('/').pop() || 'Arquivo';
      } catch {
        return 'Arquivo';
      }
    }
    return 'Arquivo';
  });

  /** True when this bubble contains an image to render */
  protected readonly isImage = computed(() => this.type() === 'image');

  /** True when this bubble contains a video to render */
  protected readonly isVideo = computed(() => this.type() === 'video');

  /** True when this bubble contains audio to render */
  protected readonly isAudio = computed(() => this.type() === 'audio');

  /** True when this bubble contains a downloadable file/document */
  protected readonly isFile = computed(
    () => this.type() === 'file' || this.type() === 'document',
  );

  /** True when there is a media URL to show */
  protected readonly hasMedia = computed(
    () =>
      (this.isImage() || this.isVideo() || this.isAudio() || this.isFile()) &&
      !!this.fileUrl(),
  );

  /** Wrapper alignment */
  protected readonly wrapperClasses = computed(() => {
    const base = 'flex flex-col w-full';
    return this.direction() === 'out' ? `${base} items-end` : `${base} items-start`;
  });

  /** Bubble styling varies by direction */
  protected readonly bubbleClasses = computed(() => {
    const base = 'px-3.5 py-2 rounded-2xl max-w-[95%] overflow-hidden';
    return this.direction() === 'out'
      ? `${base} bg-accent-500 text-white rounded-br-md`
      : `${base} bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-neutral-50 rounded-bl-md`;
  });

  /**
   * True when the message content is a readable caption (not a URL or hex hash).
   * Prevents raw URLs / file hashes from appearing below images/videos.
   */
  protected readonly hasReadableCaption = computed(() => {
    const content = this.message()?.trim();
    if (!content) return false;
    if (/^https?:\/\//i.test(content)) return false;
    if (content.length > 40 && /^[0-9a-f]+$/i.test(content)) return false;
    return true;
  });

  /** Opens a URL in a new tab (used when clicking an image to view full-size) */
  protected openUrl(url: string): void {
    window.open(url, '_blank', 'noopener,noreferrer');
  }
}
