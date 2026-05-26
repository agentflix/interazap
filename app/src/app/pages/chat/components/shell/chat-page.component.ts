import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Componente shell da página de chat.
 *
 * Stub de estrutura que serve como ponto de entrada
 * para o layout raiz do módulo de chat.
 */
@Component({
  selector: 'app-chat-page',
  standalone: true,
  templateUrl: './chat-page.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChatPageComponent {
  protected readonly isShellReady = true;
}
