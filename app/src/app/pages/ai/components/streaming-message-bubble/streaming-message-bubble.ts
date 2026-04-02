import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Streaming message bubble — renders progressively assembled text with a typing indicator.
 * Used in chat and simulator to show AI responses as they stream in.
 */
@Component({
  selector: 'app-streaming-message-bubble',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './streaming-message-bubble.html',
})
export class StreamingMessageBubbleComponent {
  /** Full or accumulated text to display. */
  readonly text = input<string>('');

  /** Whether this message is from the user (vs. AI). */
  readonly isUser = input(false);

  /** Whether text is still streaming in. */
  readonly isStreaming = input(false);

  /** Whether the stream is final (last chunk received). */
  readonly isFinal = input(false);

  /** Optional audio URL for voice responses. */
  readonly audioUrl = input<string | null>(null);

  readonly displayText = computed(() => this.text() || '');
}
