import {
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  computed,
  effect,
  inject,
  DestroyRef,
  signal,
} from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { startWith } from 'rxjs';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';
import { AfScrollAreaComponent } from '../scroll-area/scroll-area';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfInputSize, AfCountryCode, Country } from './phone-input.model';
export * from './phone-input.model';

/** Default country codes */
const DEFAULT_COUNTRIES: AfCountryCode[] = [
  { name: 'Brasil', code: '+55', iso: 'BR', flag: '🇧🇷' },
  { name: 'Estados Unidos', code: '+1', iso: 'US', flag: '🇺🇸' },
  { name: 'Portugal', code: '+351', iso: 'PT', flag: '🇵🇹' },
  { name: 'Argentina', code: '+54', iso: 'AR', flag: '🇦🇷' },
  { name: 'Chile', code: '+56', iso: 'CL', flag: '🇨🇱' },
  { name: 'Colômbia', code: '+57', iso: 'CO', flag: '🇨🇴' },
  { name: 'México', code: '+52', iso: 'MX', flag: '🇲🇽' },
  { name: 'Espanha', code: '+34', iso: 'ES', flag: '🇪🇸' },
  { name: 'Reino Unido', code: '+44', iso: 'GB', flag: '🇬🇧' },
  { name: 'Alemanha', code: '+49', iso: 'DE', flag: '🇩🇪' },
];

/**
 * Phone input with country code selector.
 *
 * @description Input with a leading dropdown to select country code (DDI)
 * and automatic phone number mask formatting.
 *
 * @example
 * ```html
 * <af-phone-input
 *   [control]="phoneControl"
 *   label="Telefone (WhatsApp)"
 *   [required]="true"
 * />
 * ```
 */
@Component({
  selector: 'af-phone-input, app-phone-input',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfFormLabelComponent,
    AfFormErrorComponent,
    LucideAngularModule,
    AfScrollAreaComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './phone-input.html',
  host: {
    '(document:click)': 'onDocumentClick($event)',
  },
})
export class AfPhoneInputComponent {
  private readonly destroyRef = inject(DestroyRef);
  private syncedControl: FormControl<string> | null = null;

  /** FormControl for the phone number */
  readonly control = input.required<FormControl<string>>();

  /** Optional FormControl for selected country dial code (e.g. +55) */
  readonly countryControl = input<FormControl<string> | null>(null);

  /** Label text */
  readonly label = input<string>();

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  /** Placeholder */
  readonly placeholder = input('(11) 99999-0000');

  /** Required indicator on label */
  readonly required = input(false);

  /** Error message */
  readonly errorMessage = input('Telefone inválido.');

  /** Available country codes */
  readonly countries = input<AfCountryCode[]>(DEFAULT_COUNTRIES);

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Legacy data-test alias */
  readonly testId = input<string>();

  /** Legacy selected country input */
  readonly selectedCountry = input<AfCountryCode | undefined>(undefined);

  /** Legacy selected country output */
  readonly selectedCountryChange = output<AfCountryCode>();

  /** Input size: sm for compact fields, md for the default comfortable field */
  readonly size = input<AfInputSize>('md');

  /** Selected country */
  protected readonly currentCountry = signal<AfCountryCode>(DEFAULT_COUNTRIES[0]);

  /** Dropdown open state */
  protected readonly dropdownOpen = signal(false);

  /** Unique ID */
  protected readonly inputId = `phone-${Math.random().toString(36).slice(2, 9)}`;

  /** Input CSS classes */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Dynamic CSS classes for country button based on size */
  protected readonly buttonClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'h-8 px-2 text-xs' : 'h-10 px-3 text-sm';
    return [
      'flex items-center gap-1 rounded-l-md border border-r-0 border-neutral-300 dark:border-neutral-600',
      'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300',
      'hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer',
      sizeClasses,
    ].join(' ');
  });

  /** Dynamic CSS classes for phone input based on size */
  protected readonly phoneInputClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'h-8 px-2 text-xs' : 'h-10 px-3 text-sm';
    const borderColor = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-neutral-600';

    return [
      'flex-1 rounded-r-md',
      'bg-white dark:bg-neutral-900',
      'text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'border focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      'transition-colors duration-150',
      borderColor,
      sizeClasses,
    ].join(' ');
  });

  /** Whether to show error */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);

  constructor() {
    effect(() => {
      const control = this.control();
      if (this.syncedControl === control) return;

      this.syncedControl = control;
      control.valueChanges
        .pipe(startWith(control.value), takeUntilDestroyed(this.destroyRef))
        .subscribe((value) => {
          const masked = this.processPhone(value ?? '');
          if (masked !== (value ?? '')) {
            control.setValue(masked, { emitEvent: false });
          }
        });
    });

    effect(() => {
      const control = this.countryControl();
      if (!control) return;

      const current = control.value;
      if (!current) {
        control.setValue(this.currentCountry().code, { emitEvent: false });
        return;
      }

      const match = this.countries().find((country) => country.code === current);
      if (match && match.iso !== this.currentCountry().iso) {
        this.currentCountry.set(match);
      }
    });

    effect(() => {
      const externalCountry = this.selectedCountry();
      if (!externalCountry || externalCountry.iso === this.currentCountry().iso) {
        return;
      }

      this.currentCountry.set(externalCountry);
    });
  }

  /** Toggle the country dropdown */
  protected toggleDropdown(event: Event): void {
    event.stopPropagation();
    this.dropdownOpen.update((v) => !v);
  }

  /** Select a country code */
  protected selectCountry(country: AfCountryCode): void {
    this.currentCountry.set(country);
    this.selectedCountryChange.emit(country);
    const control = this.countryControl();
    if (control) {
      control.setValue(country.code, { emitEvent: false });
    }
    this.dropdownOpen.set(false);
  }

  /** Apply phone mask on input */
  protected onPhoneInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const masked = this.processPhone(input.value);
    input.value = masked;
    this.control().setValue(masked, { emitEvent: false });
  }

  private processPhone(value: string): string {
    let rawValue = value;

    if (rawValue.includes('+') || rawValue.replace(/\D/g, '').length > 11) {
      const digits = rawValue.replace(/\D/g, '');
      const hasPlus = rawValue.includes('+');

      for (const country of this.countries()) {
        const countryCodeDigits = country.code.replace(/\D/g, '');
        if (digits.startsWith(countryCodeDigits)) {
          const local = digits.slice(countryCodeDigits.length);
          if (local.length >= 8 && local.length <= 11) {
            this.selectCountry(country);
            rawValue = local;
            break;
          }
        }
      }
    }

    return this.maskPhone(rawValue);
  }

  private maskPhone(value: string): string {
    let raw = value.replace(/\D/g, '').slice(0, 11);

    if (raw.length <= 10) {
      raw = raw.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    } else {
      raw = raw.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    }

    return raw.replace(/-$/, '');
  }

  /** Close dropdown on outside click */
  protected onDocumentClick(event: MouseEvent): void {
    if (this.dropdownOpen()) {
      this.dropdownOpen.set(false);
    }
  }
}

export const COUNTRIES: Country[] = DEFAULT_COUNTRIES;
export const DEFAULT_COUNTRY: Country = DEFAULT_COUNTRIES[0];
export const PhoneInputComponent = AfPhoneInputComponent;
