import { Component, ChangeDetectionStrategy, input, output, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfImageCropperComponent — Simple image preview with crop area selection.
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
  /** Image source URL */
  readonly src = input('');

  /** Aspect ratio (width/height) */
  readonly aspectRatio = input(1);

  /** Emitted when crop is applied */
  readonly cropped = output<{ src: string; zoom: number }>();

  protected readonly zoom = signal(1);

  protected onZoom(event: Event): void {
    const value = parseFloat((event.target as HTMLInputElement).value);
    this.zoom.set(value);
  }
}
