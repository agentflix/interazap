import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Contêiner que impõe proporção de aspecto ao conteúdo filho via CSS.
 *
 * Contexto: utilizado em cards de mídia, miniaturas de vídeo e qualquer
 * elemento visual que precisa manter razão de aspecto fixa.
 *
 * @example
 * ```html
 * <af-aspect-ratio ratio="16/9">
 *   <img src="miniatura.jpg" class="w-full h-full object-cover" />
 * </af-aspect-ratio>
 * ```
 */
@Component({
  selector: 'af-aspect-ratio',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './aspect-ratio.html',
})
export class AfAspectRatioComponent {
  /** Valor CSS de proporção (ex.: '16/9', '4/3', '1/1') */
  readonly ratio = input('16/9');
}
