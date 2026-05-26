import { ChangeDetectionStrategy, Component, input } from '@angular/core';

/**
 * Componente de bolha de mensagem do módulo de Chat.
 *
 * Renderiza uma mensagem individual com direção configurável
 * (`incoming` ou `outgoing`) e suporte a mensagens internas.
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
