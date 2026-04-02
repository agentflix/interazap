import { Injectable, inject, signal } from '@angular/core';
import { ElectronService } from './electron.service';

export type NotificationType = 'info' | 'success' | 'warning' | 'error';

/**
 * Servico de notificacoes nativas do sistema.
 * Utiliza notificacoes Electron quando disponivel, com fallback para
 * a API de Notification do navegador.
 * Suporta controle de enable/disable e som.
 *
 * @class NativeNotificationService
 * @example
 * ```ts
 * const notifications = inject(NativeNotificationService);
 * notifications.show('Novo ticket', 'Ticket #123 atualizado', 'info');
 * notifications.showError('Falha ao conectar');
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class NativeNotificationService {
  private electronService = inject(ElectronService);

  private enabled = signal(true);
  private soundEnabled = signal(true);

  /** Indica se notificacoes estao habilitadas globalmente. */
  get isEnabled(): boolean {
    return this.enabled();
  }

  /** Indica se som de notificacao esta habilitado. */
  get isSoundEnabled(): boolean {
    return this.soundEnabled();
  }

  /** Habilita todas as notificacoes. */
  enable(): void {
    this.enabled.set(true);
  }

  /** Desabilita todas as notificacoes. */
  disable(): void {
    this.enabled.set(false);
  }

  /** Habilita som de notificacao. */
  enableSound(): void {
    this.soundEnabled.set(true);
  }

  /** Desabilita som de notificacao. */
  disableSound(): void {
    this.soundEnabled.set(false);
  }

  /**
   * Exibe uma notificacao nativa.
   * Prioriza Electron (se disponivel) e faz fallback para Web Notification API.
   *
   * @param title - Titulo da notificacao
   * @param body - Corpo/mensagem da notificacao
   * @param type - Tipo de notificacao (info, success, warning, error)
   */
  show(title: string, body: string, type: NotificationType = 'info'): void {
    if (!this.enabled()) return;

    // Use Electron native notification if available
    if (this.electronService.isElectron) {
      this.electronService.showNotification(title, body);
      return;
    }

    // Fallback to web notification
    if ('Notification' in window && Notification.permission === 'granted') {
      const notification = new Notification(title, {
        body,
        icon: this.getIconForType(type),
      });

      notification.onclick = () => {
        window.focus();
        notification.close();
      };
    } else if ('Notification' in window && Notification.permission !== 'denied') {
      void Notification.requestPermission().then((permission) => {
        if (permission === 'granted') {
          this.show(title, body, type);
        }
      });
    }
  }

  /**
   * Exibe notificacao de nova mensagem de chat.
   *
   * @param sender - Nome do remetente
   * @param message - Conteudo da mensagem
   */
  showChatMessage(sender: string, message: string): void {
    this.show(`Chat: ${sender}`, message, 'info');
  }

  /**
   * Exibe notificacao de atualizacao de ticket.
   *
   * @param ticketId - ID do ticket
   * @param status - Novo status do ticket
   */
  showTicketUpdate(ticketId: string, status: string): void {
    this.show(`Ticket #${ticketId}`, `Status updated to: ${status}`, 'success');
  }

  /**
   * Exibe notificacao de sistema generica.
   *
   * @param message - Mensagem de sistema
   */
  showSystemNotification(message: string): void {
    this.show('AgentFlix', message, 'info');
  }

  /**
   * Exibe notificacao de erro.
   *
   * @param message - Mensagem de erro
   */
  showError(message: string): void {
    this.show('Error', message, 'error');
  }

  /**
   * Exibe notificacao de alerta.
   *
   * @param message - Mensagem de aviso
   */
  showWarning(message: string): void {
    this.show('Warning', message, 'warning');
  }

  private getIconForType(_type: NotificationType): string {
    // Return empty string for now - in production you'd have actual icons
    return '';
  }
}
