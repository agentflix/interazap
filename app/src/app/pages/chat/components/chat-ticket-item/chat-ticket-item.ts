import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { DatePipe } from '@angular/common';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideBot, lucideUser, lucideUsers } from '@ng-icons/lucide';

import { type Called } from 'src/app/core/services/called.service';

/**
 * Item individual da lista de chamados (tickets).
 *
 * Exibe avatar com initials (sem dependência externa), nome do contato,
 * última mensagem, badges de IA/usuário/departamento, status dot e hora.
 *
 * @example
 * ```html
 * <app-chat-ticket-item [ticket]="ticket" (selected)="openTicket(ticket)" />
 * ```
 */
@Component({
  selector: 'app-chat-ticket-item',
  standalone: true,
  imports: [NgIcon, DatePipe],
  templateUrl: './chat-ticket-item.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  viewProviders: [provideIcons({ lucideBot, lucideUser, lucideUsers })],
})
export class ChatTicketItemComponent {
  /** Ticket a ser renderizado. */
  readonly ticket = input.required<Called>();

  /** Indica se o ticket está selecionado na lista. */
  readonly active = input(false);

  /** Evento disparado ao selecionar o ticket. */
  readonly selected = output<void>();

  /**
   * Retorna as iniciais do nome ou telefone do contato.
   *
   * @param ticket - Ticket cujo contato será usado.
   * @returns String com até 2 caracteres maiúsculos.
   */
  getInitials(ticket: Called): string {
    const contactName = ticket.contact?.name?.trim() ?? '';
    const fallbackPhone = ticket.contact?.phone?.trim() ?? '';
    const source = contactName !== '' ? contactName : fallbackPhone;
    return source.slice(0, 2).toUpperCase() || '??';
  }

  /**
   * Retorna a URL da foto de perfil do contato, se disponível.
   *
   * @param ticket - Ticket a verificar.
   * @returns URL da imagem ou null.
   */
  getProfilePicture(ticket: Called): string | null {
    return ticket.profile_picture_url ?? ticket.contact?.profile_picture_url ?? null;
  }

  /** Oculta o elemento de imagem em caso de erro para exibir o fallback de iniciais. */
  handleImageError(event: Event): void {
    (event.target as HTMLImageElement).style.display = 'none';
  }

  /** Dispara evento de seleção do ticket. */
  onSelect(): void {
    this.selected.emit();
  }
}
