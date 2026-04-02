import { Component, ChangeDetectionStrategy, input, signal } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Copy-to-clipboard input for AgentFlix UI Kit.
 *
 * @description Read-only text input with a trailing copy button.
 * Copies the value to the clipboard and briefly shows a check icon.
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
  /** FormControl holding the value to copy */
  readonly control = input.required<FormControl<string>>();

  /** Label text */
  readonly label = input<string>();

  /** Container CSS class */
  readonly classContainer = input<string>('mb-4');

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Whether the value was just copied */
  protected readonly copied = signal(false);

  /** Unique ID */
  protected readonly inputId = `copy-${Math.random().toString(36).slice(2, 9)}`;

  /** Copy to clipboard */
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
