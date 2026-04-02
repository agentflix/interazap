import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { NgIcon, provideIcons } from '@ng-icons/core';
import {
  lucideFile,
  lucideImage,
  lucideMapPin,
  lucideMic,
  lucideMusic,
  lucidePencil,
  lucideUser,
  lucideVideo,
} from '@ng-icons/lucide';

import type { QuotedMessage } from './chat-quoted-message.component.model';
export * from './chat-quoted-message.component.model';



/**
 * Componente de preview de mensagem citada (Quote/Reply).
 *
 * Exibe uma prévia da mensagem original em estilo WhatsApp,
 * com barra lateral colorida e informações do remetente.
 */
@Component({
  selector: 'app-chat-quoted-message',
  standalone: true,
  imports: [NgIcon],
  viewProviders: [
    provideIcons({
      lucideImage,
      lucideVideo,
      lucideMic,
      lucideMusic,
      lucidePencil,
      lucideFile,
      lucideUser,
      lucideMapPin,
    }),
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './chat-quoted-message.component.html',
})
export class ChatQuotedMessageComponent {
  /** Dados da mensagem citada */
  readonly quoted = input<QuotedMessage | null>(null);

  /** Direção da mensagem pai (outgoing = enviada por mim) */
  readonly parentDirection = input<'incoming' | 'outgoing'>('incoming');

  /** Verifica se a bolha pai é de saída (enviada por mim) */
  isParentOutgoing(): boolean {
    return this.parentDirection() === 'outgoing';
  }

  /** Nome do remetente formatado */
  senderName(): string {
    const quoted = this.quoted();
    if (!quoted) return '';

    if (quoted.direction === 'outgoing') {
      return 'Você';
    }

    const senderName = quoted.sender_name?.trim();
    return senderName !== undefined && senderName !== '' ? senderName : 'Contato';
  }
}
