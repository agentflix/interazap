import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Painel de conversa do módulo de Chat.
 *
 * Stub reservado para implementação futura do painel
 * central de conversa de um atendimento selecionado.
 */
@Component({
  selector: 'app-conversation-panel',
  standalone: true,
  templateUrl: './conversation-panel.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConversationPanelComponent {
  protected readonly isConversationReady = true;
}
