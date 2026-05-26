import {
  type ElementRef,
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
  viewChildren,
  QueryList,
  computed,
} from '@angular/core';
import { AfFormLabelComponent } from '../form-label/form-label';

/**
 * Campo de entrada de senha de uso único (OTP) / código de verificação.
 *
 * @example
 * ```html
 * <af-otp-input [length]="6" (completed)="onVerify($event)" />
 * ```
 */
@Component({
  selector: 'af-otp-input',
  standalone: true,
  imports: [AfFormLabelComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './otp-input.html',
})
export class AfOtpInputComponent {
  /** Número de dígitos do OTP */
  readonly length = input(6);

  /** Rótulo */
  readonly label = input('');

  /** Estado desabilitado */
  readonly disabled = input(false);

  /** Emitido quando todos os dígitos estão preenchidos */
  readonly completed = output<string>();

  protected readonly values = signal<string[]>([]);

  protected readonly slots = computed(() => Array.from({ length: this.length() }, (_, i) => i));

  private readonly inputRefs = viewChildren<ElementRef<HTMLInputElement>>('otpInput');

  constructor() {
    queueMicrotask(() => this.values.set(Array(this.length()).fill('')));
  }

  protected onInput(event: Event, index: number): void {
    const el = event.target as HTMLInputElement;
    const val = el.value.replace(/\D/g, '').slice(0, 1);
    el.value = val;

    const current = [...this.values()];
    current[index] = val;
    this.values.set(current);

    if (val && index < this.length() - 1) {
      const inputs = this.inputRefs();
      inputs[index + 1]?.nativeElement.focus();
    }

    if (current.every((v) => v.length === 1)) {
      this.completed.emit(current.join(''));
    }
  }

  protected onKeydown(event: KeyboardEvent, index: number): void {
    if (event.key === 'Backspace' && !this.values()[index] && index > 0) {
      const inputs = this.inputRefs();
      inputs[index - 1]?.nativeElement.focus();
    }
  }

  protected onPaste(event: ClipboardEvent): void {
    event.preventDefault();
    const paste = event.clipboardData?.getData('text')?.replace(/\D/g, '') ?? '';
    const chars = paste.slice(0, this.length()).split('');
    const inputs = this.inputRefs();

    const current = Array(this.length()).fill('');
    chars.forEach((c, i) => {
      current[i] = c;
      if (inputs[i]) {
        inputs[i].nativeElement.value = c;
      }
    });
    this.values.set(current);

    const focusIdx = Math.min(chars.length, this.length() - 1);
    inputs[focusIdx]?.nativeElement.focus();

    if (current.every((v) => v.length === 1)) {
      this.completed.emit(current.join(''));
    }
  }
}
