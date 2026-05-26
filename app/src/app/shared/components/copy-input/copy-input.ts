import { Component, ChangeDetectionStrategy, input, signal } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Campo de texto somente leitura com botão para copiar o valor para a área de transferência.
 *
 * Ao copiar, exibe brevemente um ícone de confirmação.
 *
 * @example
 * ```html
 * <af-copy-input
 *   [control]="apiKeyControl"
 *   label="API Key"
 * />
 * ```
 */
@Component({
  selector: 'af-copy-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './copy-input.html',
})
export class AfCopyInputComponent {
  /** FormControl que contém o valor a ser copiado */
  readonly control = input.required<FormControl<string>>();

  /** Texto do rótulo */
  readonly label = input<string>();

  /** Classe CSS do contêiner */
  readonly classContainer = input<string>('mb-4');

  /** Atributo data-test */
  readonly dataTest = input<string>();

  /** Indica se o valor acabou de ser copiado */
  protected readonly copied = signal(false);

  /** ID único do campo */
  protected readonly inputId = `copy-${Math.random().toString(36).slice(2, 9)}`;

  /** Copia o valor para a área de transferência */
  protected async copyValue(): Promise<void> {
    const value = this.control().value;
    if (!value) return;

    try {
      await navigator.clipboard.writeText(value);
      this.copied.set(true);
      setTimeout(() => this.copied.set(false), 2000);
    } catch {
      // Fallback for older browsers
      const textarea = document.createElement('textarea');
      textarea.value = value;
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
      this.copied.set(true);
      setTimeout(() => this.copied.set(false), 2000);
    }
  }
}
