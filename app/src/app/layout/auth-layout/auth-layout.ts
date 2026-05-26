import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

/**
 * Layout para páginas de autenticação (login, registro, recuperação de senha).
 * Renderiza apenas o `<router-outlet>` sem sidebar ou topbar.
 */
@Component({
  selector: 'af-auth-layout',
  standalone: true,
  imports: [RouterOutlet],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './auth-layout.html',
})
// eslint-disable-next-line @typescript-eslint/no-extraneous-class
export class AuthLayoutComponent {}
