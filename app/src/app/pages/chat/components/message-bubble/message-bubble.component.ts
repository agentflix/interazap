import { ChangeDetectionStrategy, Component, input } from '@angular/core';

/**
 * Message bubble component for the Chat module.
 * @selector app-message-bubble
 */
@Component({
  selector: 'app-message-bubble',
  standalone: true,
  templateUrl: './message-bubble.component.html',
  host: {
    class: 'block max-w-[80%] min-w-0',
  },
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MessageBubbleComponent {
  readonly direction = input<'incoming' | 'outgoing'>('incoming');
  readonly isInternal = input(false);
}
