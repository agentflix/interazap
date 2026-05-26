import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';

/**
 * Controle deslizante de intervalo (slider).
 *
 * @example
 * ```html
 * <af-slider [value]="50" [min]="0" [max]="100" (valueChange)="onSlide($event)" />
 * ```
 */
@Component({
  selector: 'af-slider',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './slider.html',
})
export class AfSliderComponent {
  /** Valor atual */
  readonly value = input(50);

  /** Valor mínimo */
  readonly min = input(0);

  /** Valor máximo */
  readonly max = input(100);

  /** Passo */
  readonly step = input(1);

  /** Rótulo */
  readonly label = input('');

  /** Exibe o valor atual */
  readonly showValue = input(true);

  /** Desabilitado */
  readonly disabled = input(false);

  /** Emitido ao alterar o valor */
  readonly valueChange = output<number>();

  protected onInput(event: Event): void {
    const val = +(event.target as HTMLInputElement).value;
    this.valueChange.emit(val);
  }
}
