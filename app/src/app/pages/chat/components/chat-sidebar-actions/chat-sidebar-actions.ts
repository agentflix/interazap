import {
  ChangeDetectionStrategy,
  Component,
  EventEmitter,
  Input,
  Output,
  computed,
  inject,
} from '@angular/core';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import { IconButtonComponent } from 'src/app/shared/components/buttons';

/**
 * Componente das ações rápidas e navegação por abas da barra lateral.
 *
 * Gerencia a alternância entre as visualizações de chat, detalhes do contato,
 * negociações e agenda do atendimento selecionado.
 *
 * @class ChatSidebarActions
 * @description Seletor de abas laterais para o contexto do atendimento.
 */
@Component({
  selector: 'app-chat-sidebar-actions',
  standalone: true,
  imports: [IconButtonComponent],
  templateUrl: './chat-sidebar-actions.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    class: 'block h-full',
  },
})
export class ChatSidebarActions {
  /**
   * Serviço de armazenamento e gestão de dados do usuário autenticado.
   * @private
   */
  private readonly authStore = inject(AuthStoreService);
  /**
   * Dados do usuário autenticado para exibição de perfil.
   * @type {Signal<User | null>}
   */
  readonly user = computed(() => this.authStore.user());

  /**
   * Identificador da aba que está ativa no momento.
   * @type {Input}
   */
  @Input() activeTab: 'chat' | 'contact' | 'negotiation' | 'agenda' = 'chat';

  /**
   * Emissor de evento disparado quando o usuário altera a aba ativa.
   * @type {Output}
   */
  @Output() tabChange = new EventEmitter<'chat' | 'contact' | 'negotiation' | 'agenda'>();

  /**
   * Definição das abas disponíveis no componente, incluindo ícones e identificadores.
   * @type {readonly {id: string, icon: string}[]}
   */
  readonly tabs = [
    { id: 'chat', icon: 'lucideMessagesSquare' },
    { id: 'contact', icon: 'lucideSquareUser' },
    { id: 'negotiation', icon: 'lucideHandshake' },
    { id: 'agenda', icon: 'lucideCalendar' },
  ] as const;
}
