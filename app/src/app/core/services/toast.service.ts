import { Injectable } from '@angular/core';
import { toast } from 'ngx-sonner';

/**
 * Servico wrapper para notificacoes de toast via ngx-sonner.
 * Fornece metodos convenience para exibir mensagens de sucesso, erro, etc.
 *
 * @class ToastService
 * @example
 * ```ts
 * const toast = inject(ToastService);
 * toast.success('Operacao realizada com sucesso');
 * toast.error('Algo deu errado');
 * ```
 */
@Injectable({ providedIn: 'root' })
export class ToastService {
  /**
   * Exibe notificação de sucesso.
   *
   * @param message - Mensagem a ser exibida
   */
  success(message: string): void {
    toast.success(message);
  }

  /**
   * Exibe notificação informativa.
   *
   * @param message - Mensagem a ser exibida
   */
  info(message: string): void {
    toast.info(message);
  }

  /**
   * Exibe notificação de alerta/warning.
   *
   * @param message - Mensagem a ser exibida
   */
  warning(message: string): void {
    toast.warning(message);
  }

  /**
   * Exibe notificação de erro.
   *
   * @param message - Mensagem a ser exibida
   */
  error(message: string): void {
    toast.error(message);
  }
}
