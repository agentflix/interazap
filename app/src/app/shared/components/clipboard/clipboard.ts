import { Component, ChangeDetectionStrategy, input, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfClipboardComponent — Click-to-copy text with visual feedback.
 *
 * @example
 * ```html
 * <af-clipboard text="npm install agentflix" />
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
  /** Text to copy */
  readonly text = input('');

  protected readonly copied = signal(false);

  protected async copy(): Promise<void> {
    await navigator.clipboard.writeText(this.text());
    this.copied.set(true);
    setTimeout(() => this.copied.set(false), 2000);
  }
}
