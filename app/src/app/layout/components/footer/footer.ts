import { Component, ChangeDetectionStrategy } from '@angular/core';

/**
 * Rodapé da aplicação com copyright e informações de versão.
 *
 * @example
 * ```html
 * <af-footer />
 * ```
 */
@Component({
  selector: 'af-footer',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './footer.html',
})
export class FooterComponent {
  readonly year = new Date().getFullYear();
}
