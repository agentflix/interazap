import { Component, ChangeDetectionStrategy, input, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Componente de cópia para área de transferência com feedback visual ao clicar.
 *
 * @example
 * ```html
 * <af-clipboard text="npm install interazap" />
 * ```
 */
@Component({
  selector: 'af-clipboard',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './clipboard.html',
})
export class AfClipboardComponent {
  /** Texto a ser copiado */
  readonly text = input('');

  protected readonly copied = signal(false);

  protected async copy(): Promise<void> {
    await navigator.clipboard.writeText(this.text());
    this.copied.set(true);
    setTimeout(() => this.copied.set(false), 2000);
  }
}
