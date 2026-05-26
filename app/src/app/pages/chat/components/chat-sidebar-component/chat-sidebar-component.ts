import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { IconButtonComponent } from 'src/app/shared/components/buttons';

/**
 * Painel lateral direito com abas de ação extraído da página de chat.
 *
 * @remarks
 * Renderiza os botões de alternância entre as abas de conversa,
 * contato e negociação. Estado de aba ativa recebido via input signal.
 */
@Component({
  selector: 'app-chat-page-sidebar',
  standalone: true,
  imports: [IconButtonComponent],
  templateUrl: './chat-sidebar-component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChatSidebarComponent {
  readonly activeTab = input.required<'chat' | 'contact' | 'negotiation'>();
  readonly setTab = output<'chat' | 'contact' | 'negotiation'>();
}
