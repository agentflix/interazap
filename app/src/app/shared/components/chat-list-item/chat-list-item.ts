import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { AfAvatarComponent } from '../avatar/avatar';
import { AfBadgeComponent } from '../badge/badge';

/**
 * Item individual de conversa na lista de conversas do chat.
 * Exibe avatar, nome, prévia da última mensagem, horário e contador de não lidas.
 *
 * @example
 * ```html
 * <af-chat-list-item
 *   name="Maria Silva"
 *   lastMessage="Obrigada pela ajuda!"
 *   timestamp="14:32"
 *   [unreadCount]="3"
 *   [online]="true"
 *   (selected)="openChat(contact.id)"
 * />
 * ```
 */
@Component({
  selector: 'af-chat-list-item',
  standalone: true,
  imports: [AfAvatarComponent, AfBadgeComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './chat-list-item.html',
})
export class AfChatListItemComponent {
  /** Nome do contato ou da conversa */
  readonly name = input.required<string>();

  /** URL da imagem do avatar */
  readonly avatarUrl = input<string | null>(null);

  /** Prévia da última mensagem */
  readonly lastMessage = input('');

  /** String formatada com o horário */
  readonly timestamp = input('');

  /** Número de mensagens não lidas */
  readonly unreadCount = input(0);

  /** Indica se o contato está online */
  readonly online = input(false);

  /** Indica se este item é a conversa ativa/selecionada */
  readonly active = input(false);

  /** Emitido quando o item é clicado */
  readonly selected = output<void>();

  /** Gera iniciais a partir do nome */
  protected readonly initials = computed(() => {
    const parts = this.name().trim().split(' ');
    if (parts.length >= 2) {
      return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
    }
    return this.name().slice(0, 2).toUpperCase();
  });

  /** Classes dinâmicas baseadas no estado ativo */
  protected readonly itemClasses = computed(() => {
    const base =
      'w-full flex items-center gap-3 px-4 py-3 text-left transition-colors cursor-pointer border-b border-neutral-100 dark:border-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent-500/50';
    const activeClass = this.active()
      ? 'bg-accent-50 dark:bg-accent-950/30'
      : 'hover:bg-neutral-50 dark:hover:bg-neutral-800/50';
    return `${base} ${activeClass}`;
  });
}
