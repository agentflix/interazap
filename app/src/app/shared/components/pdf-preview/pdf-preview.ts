import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { type SafeResourceUrl, DomSanitizer } from '@angular/platform-browser';
import { AfButtonComponent } from '../button/button';

/**
 * Af pdf preview shared component for the Shared module.
 * @selector af-pdf-preview
 */
@Component({
  selector: 'af-pdf-preview',
  standalone: true,
  imports: [AfButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './pdf-preview.html',
})
export class AfPdfPreviewComponent {
  readonly src = input<string | null>(null);
  readonly title = input('Prévia do PDF');

  readonly loadError = signal(false);

  private readonly sanitizer = inject(DomSanitizer);

  readonly safeSrc = computed<SafeResourceUrl | null>(() => {
    const src = this.src();
    if (!src) {
      return null;
    }
    return this.sanitizer.bypassSecurityTrustResourceUrl(src);
  });

  onLoadError(): void {
    this.loadError.set(true);
  }

  openExternal(): void {
    const src = this.src();
    if (!src) {
      return;
    }
    window.open(src, '_blank', 'noopener,noreferrer');
  }
}
