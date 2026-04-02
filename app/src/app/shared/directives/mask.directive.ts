import { Directive, ElementRef, HostListener, input, inject } from '@angular/core';
import { NgControl } from '@angular/forms';

/** Supported mask presets for Brazilian formats */
export type AfMaskPreset = 'cpf' | 'cnpj' | 'cep' | 'phone' | 'currency' | 'cpf-cnpj' | string;

/**
 * Input mask directive for Brazilian document formats.
 *
 * @description Applies CPF, CNPJ, CEP, phone and currency masks
 * to text inputs using pure Angular — no third-party dependency.
 *
 * @example
 * ```html
 * <input afMask="cpf" [formControl]="cpfControl" />
 * <input afMask="currency" [formControl]="priceControl" />
 * ```
 */
@Directive({
  selector: '[afMask]',
  standalone: true,
})
export class AfMaskDirective {
  /** Mask preset to apply */
  readonly afMask = input.required<AfMaskPreset>();

  private readonly el = inject(ElementRef<HTMLInputElement>);
  private readonly ngControl = inject(NgControl, { optional: true });

  /** Mask patterns for each preset */
  private readonly masks: Record<string, string> = {
    cpf: '###.###.###-##',
    cnpj: '##.###.###/####-##',
    cep: '#####-###',
    phone: '(##) #####-####',
  };

  @HostListener('input', ['$event'])
  onInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const preset = this.afMask();

    if (preset === 'currency') {
      this.applyCurrencyMask(input);
    } else if (preset === 'cpf-cnpj') {
      this.applyCpfCnpjMask(input);
    } else if (preset.includes('||')) {
      this.applyAlternativeMask(input, preset);
    } else if (this.masks[preset]) {
      this.applyPatternMask(input, this.masks[preset]);
    } else {
      this.applyPatternMask(input, preset.replace(/0/g, '#'));
    }
  }

  private applyAlternativeMask(input: HTMLInputElement, mask: string): void {
    const raw = input.value.replace(/\D/g, '');
    const candidates = mask.split('||').map((item) => item.trim().replace(/0/g, '#'));
    const selectedMask = raw.length > 10 ? candidates[candidates.length - 1] : candidates[0];
    this.applyPatternMask(input, selectedMask);
  }

  @HostListener('focus')
  onFocus(): void {
    if (this.afMask() === 'currency') {
      const input = this.el.nativeElement;
      if (!input.value) {
        input.value = 'R$ 0,00';
        this.updateControl('0');
      }
    }
  }

  /** Apply pattern-based mask (CPF, CNPJ, CEP, Phone) */
  private applyPatternMask(input: HTMLInputElement, mask: string): void {
    const raw = input.value.replace(/\D/g, '');
    let result = '';
    let rawIndex = 0;

    for (let i = 0; i < mask.length && rawIndex < raw.length; i++) {
      if (mask[i] === '#') {
        result += raw[rawIndex];
        rawIndex++;
      } else {
        result += mask[i];
        if (raw[rawIndex] === mask[i]) {
          rawIndex++;
        }
      }
    }

    input.value = result;
    this.updateControl(raw);
  }

  /** Auto-switch between CPF (11 digits) and CNPJ (14 digits) */
  private applyCpfCnpjMask(input: HTMLInputElement): void {
    const raw = input.value.replace(/\D/g, '').slice(0, 14);
    const mask = raw.length <= 11 ? this.masks['cpf'] : this.masks['cnpj'];
    input.value = '';
    input.value = raw;
    this.applyPatternMask(input, mask);
  }

  /** Apply Brazilian currency mask (R$ 1.234,56) */
  private applyCurrencyMask(input: HTMLInputElement): void {
    let raw = input.value.replace(/\D/g, '');
    if (!raw) raw = '0';

    const numericValue = parseInt(raw, 10) / 100;
    const formatted = numericValue.toLocaleString('pt-BR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

    input.value = `R$ ${formatted}`;
    this.updateControl(numericValue.toString());
  }

  /** Update the FormControl raw value */
  private updateControl(value: string): void {
    if (this.ngControl?.control) {
      this.ngControl.control.setValue(value, { emitModelToViewChange: false });
    }
  }
}
