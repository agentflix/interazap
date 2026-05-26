import { Component, ChangeDetectionStrategy, input, output, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Pré-visualização de imagem simples com seleção de área de recorte.
 *
 * @example
 * ```html
 * <af-image-cropper [src]="imageUrl()" [aspectRatio]="1" (cropped)="onCrop($event)" />
 * ```
 */
@Component({
  selector: 'af-image-cropper',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './image-cropper.html',
})
export class AfImageCropperComponent {
  /** URL da imagem de origem */
  readonly src = input('');

  /** Proporção da área de recorte (largura/altura) */
  readonly aspectRatio = input(1);

  /** Emitido quando o recorte é aplicado */
  readonly cropped = output<{ src: string; zoom: number }>();

  protected readonly zoom = signal(1);

  protected onZoom(event: Event): void {
    const value = parseFloat((event.target as HTMLInputElement).value);
    this.zoom.set(value);
  }
}
