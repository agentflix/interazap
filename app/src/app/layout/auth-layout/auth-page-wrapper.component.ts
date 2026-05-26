import { ChangeDetectionStrategy, Component, input } from '@angular/core';

/**
 * Wrapper de página de autenticação com título, subtítulo e texto de destaque.
 * Usado como contêiner visual padrão nas páginas de login, registro e redefinição de senha.
 */
@Component({
  selector: 'app-auth-page-wrapper',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './auth-page-wrapper.component.html',
})
export class AuthPageWrapperComponent {
  /** Título principal da página de autenticação. */
  readonly title = input.required<string>();
  /** Subtítulo descritivo exibido abaixo do título. */
  readonly subtitle = input.required<string>();
  /** Texto de cabeçalho exibido acima do título (padrão: 'InteraZap Access'). */
  readonly eyebrow = input<string>('InteraZap Access');
  /** Trecho do título que receberá destaque visual (gradiente). */
  readonly highlight = input<string>('');
}
