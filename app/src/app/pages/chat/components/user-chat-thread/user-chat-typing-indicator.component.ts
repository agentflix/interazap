import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Indicador de digitação na thread de chat.
 *
 * Exibe animação de "Digitando..." quando o contato está
 * redigindo uma mensagem em tempo real via WebSocket.
 */
@Component({
  selector: 'app-user-chat-typing-indicator',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './user-chat-typing-indicator.component.html',
})
export class UserChatTypingIndicatorComponent {
  protected readonly label = 'Digitando...';
}
