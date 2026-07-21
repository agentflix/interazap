import { Component, ChangeDetectionStrategy, signal, input, output } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Botão reutilizável de cópia para área de transferência.
 *
 * Emite o evento `copied` ao copiar o texto com sucesso.
 * Utiliza fallback via `execCommand` em navegadores sem suporte à Clipboard API.
 *
 * @example
 * ```html
 * <app-copy-button [text]="meuCodigo" (copied)="onCopiado()" />
 * ```
 */
@Component({
  selector: 'app-copy-button',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <button
      type="button"
      (click)="copy()"
      [disabled]="isCopying()"
      class="p-1.5 rounded-md text-neutral-500 dark:text-neutral-400
             hover:bg-neutral-100 dark:hover:bg-[#191d1a]
             disabled:opacity-50 disabled:cursor-not-allowed
             transition-colors duration-150"
      [attr.data-test]="'copy-btn'"
      [attr.title]="isCopied() ? 'Copiado!' : 'Copiar'"
    >
      @if (isCopied()) {
        <lucide-icon name="check" [size]="16" class="block text-green-600" />
      } @else {
        <lucide-icon name="copy" [size]="16" class="block" />
      }
    </button>
  `,
})
export class CopyButtonComponent {
  /** Texto a ser copiado para a área de transferência. */
  text = input<string>('');

  /** Emitido quando o texto foi copiado com sucesso. */
  copied = output<void>();

  readonly isCopying = signal(false);
  readonly isCopied = signal(false);

  copy(): void {
    const textToCopy = this.text();
    if (!textToCopy) return;

    this.isCopying.set(true);

    navigator.clipboard
      .writeText(textToCopy)
      .then(() => {
        this.isCopied.set(true);
        this.copied.emit();

        setTimeout(() => {
          this.isCopied.set(false);
        }, 2000);
      })
      .catch(() => {
        this.fallbackCopy(textToCopy);
      })
      .finally(() => {
        this.isCopying.set(false);
      });
  }

  private fallbackCopy(text: string): void {
    try {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.cssText = 'position:fixed;opacity:0;';
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);

      this.isCopied.set(true);
      this.copied.emit();

      setTimeout(() => this.isCopied.set(false), 2000);
    } catch {
      // silent fallback fail — user will notice nothing was copied
    }
  }
}