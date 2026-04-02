import { ChangeDetectionStrategy, Component, computed, input, output, signal } from '@angular/core';
import { type CalledMessage } from 'src/app/core/services/called-message.service';
import { ChatMessageMediaComponent } from '../chat-message-media/chat-message-media.component';
import { MessageBubbleComponent } from '../message-bubble/message-bubble.component';

/**
 * User chat message bubble component for the Chat module.
 * @selector app-user-chat-message-bubble
 */
@Component({
  selector: 'app-user-chat-message-bubble',
  standalone: true,
  imports: [MessageBubbleComponent, ChatMessageMediaComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block w-full' },
  templateUrl: './user-chat-message-bubble.component.html',
})
export class UserChatMessageBubbleComponent {
  readonly message = input.required<CalledMessage>();
  readonly readOnlyMode = input(false);

  readonly reply = output<CalledMessage>();

  readonly emojiPickerOpen = signal(false);

  protected readonly quickReactions = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

  private static readonly MEDIA_TYPES = new Set([
    'image',
    'video',
    'audio',
    'document',
    'file',
    'ptt',
    'ptv',
    'sticker',
  ]);

  readonly isOutgoing = computed(() => this.message().direction === 'outgoing');

  readonly isInternalNote = computed(() => this.message().type === 'internal_note');

  readonly isMedia = computed(() => {
    const type = this.message().type;
    return typeof type === 'string' && UserChatMessageBubbleComponent.MEDIA_TYPES.has(type);
  });

  readonly content = computed(() => {
    const message = this.message();
    if (typeof message.content === 'string' && message.content.trim().length > 0) {
      const text = message.content.trim();
      if (this.isMedia() && /^https?:\/\//.test(text)) {
        return null;
      }
      return text;
    }

    if (
      !this.isMedia() &&
      typeof message.file_name === 'string' &&
      message.file_name.trim().length > 0
    ) {
      return message.file_name;
    }

    return this.isMedia() ? null : 'Mensagem sem texto';
  });

  readonly caption = computed(() => {
    if (!this.isMedia()) return null;
    const message = this.message();
    if (typeof message.content === 'string' && message.content.trim().length > 0) {
      const text = message.content.trim();
      if (/^https?:\/\//.test(text)) return null;
      return text;
    }
    return null;
  });

  readonly timeLabel = computed(() => {
    const source = this.message().created_at ?? this.message().sent_at;
    if (!source) return '';
    const parsed = new Date(source);
    if (Number.isNaN(parsed.getTime())) return '';
    return parsed.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  });

  readonly messageStatus = computed<'read' | 'delivered' | 'sent' | 'pending' | 'none'>(() => {
    const msg = this.message();
    if (msg.direction !== 'outgoing') return 'none';
    if (msg.read_at) return 'read';
    if (msg.delivered_at) return 'delivered';
    if (msg.sent_at || msg.status === 'sent') return 'sent';
    return 'pending';
  });

  toggleEmojiPicker(): void {
    this.emojiPickerOpen.update((v) => !v);
  }

  selectReaction(emoji: string): void {
    this.emojiPickerOpen.set(false);
    // Reação selecionada — emit pode ser adicionado quando backend suportar
    void emoji;
  }

  onReply(): void {
    this.reply.emit(this.message());
  }
}
