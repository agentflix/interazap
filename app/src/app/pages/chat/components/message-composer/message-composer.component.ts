import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Componente compositor de mensagens do módulo de Chat.
 *
 * Stub reservado para implementação futura do compositor de texto,
 * mídia e outros tipos de mensagem.
 */
@Component({
  selector: 'app-message-composer',
  standalone: true,
  templateUrl: './message-composer.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MessageComposerComponent {
  protected readonly isComposerReady = true;
}
