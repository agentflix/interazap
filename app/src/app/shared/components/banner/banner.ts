import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Banner de notificação em largura total exibido no topo da página.
 *
 * Contexto: utilizado para comunicados globais, alertas de manutenção,
 * promoções e avisos que devem chamar atenção do usuário imediatamente.
 *
 * @example
 * ```html
 * <af-banner variant="info" message="Nova versão disponível." [dismissible]="true" />
 * ```
 */
@Component({
  selector: 'af-banner',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './banner.html',
})
export class AfBannerComponent {
  /** Variante visual do banner */
  readonly variant = input<'info' | 'success' | 'warning' | 'danger'>('info');

  /** Mensagem exibida no banner */
  readonly message = input('');

  /** Rótulo do botão de ação opcional */
  readonly actionLabel = input('');

  /** Permite fechar o banner */
  readonly dismissible = input(false);

  /** Emitido ao clicar no botão de ação */
  readonly actionClicked = output<void>();

  /** Emitido ao fechar o banner */
  readonly dismissed = output<void>();

  protected readonly iconName = computed(() => {
    const map: Record<string, string> = {
      info: 'info',
      success: 'circle-check',
      warning: 'alert-triangle',
      danger: 'circle-alert',
    };
    return map[this.variant()];
  });

  protected readonly bannerClasses = computed(() => {
    const variants: Record<string, string> = {
      info: 'bg-blue-600 text-white',
      success: 'bg-accent-600 text-white',
      warning: 'bg-amber-500 text-white',
      danger: 'bg-red-600 text-white',
    };
    return `w-full ${variants[this.variant()]}`;
  });
}
