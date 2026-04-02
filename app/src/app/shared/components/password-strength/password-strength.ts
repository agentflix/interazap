import {
  type OnInit,
  Component,
  ChangeDetectionStrategy,
  input,
  computed,
  signal,
  DestroyRef,
  inject,
} from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';

import type { AfPasswordStrength } from './password-strength.model';
export * from './password-strength.model';



/** Strength check result */
interface StrengthResult {
  level: AfPasswordStrength;
  score: number;
  label: string;
  color: string;
  checks: PasswordCheck[];
}

/** Individual check item */
interface PasswordCheck {
  label: string;
  passed: boolean;
}

/**
 * Password input with strength meter and visibility toggle.
 *
 * @description Extended password field that shows a real-time strength
 * indicator bar and individual requirement checklist. Ideal for
 * registration and "create password" wizard forms.
 *
 * @example
 * ```html
 * <af-password-strength
 *   [control]="form.controls.newPassword"
 *   label="Nova Senha"
 *   [required]="true"
 * />
 * ```
 */
@Component({
  selector: 'af-password-strength',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './password-strength.html',
})
export class AfPasswordStrengthComponent implements OnInit {
  /** FormControl for the password */
  readonly control = input.required<FormControl<string>>();

  /** Label text */
  readonly label = input<string>();

  /** Container CSS class */
  readonly classContainer = input<string>('mb-4');

  /** Placeholder text */
  readonly placeholder = input('Crie uma senha segura');

  /** Required asterisk on label */
  readonly required = input(false);

  /** Error message */
  readonly errorMessage = input('Senha é obrigatória.');

  /** Minimum password length */
  readonly minLength = input(8);

  /** Whether to show the requirement checklist */
  readonly showChecklist = input(true);

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Password visibility state */
  protected readonly visible = signal(false);

  /** Computed strength result */
  protected readonly strength = signal<StrengthResult>(this.calculateStrength(''));

  /** Number of bar segments */
  protected readonly segments = [0, 1, 2, 3];

  /** Unique ID */
  protected readonly inputId = `pw-strength-${Math.random().toString(36).slice(2, 9)}`;

  private readonly destroyRef = inject(DestroyRef);

  ngOnInit() {
    const ctrl = this.control();
    if (ctrl) {
      // Set initial state
      this.strength.set(this.calculateStrength(ctrl.value ?? ''));

      // Watch for value changes
      ctrl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((value) => {
        this.strength.set(this.calculateStrength(value ?? ''));
      });
    }
  }

  /** Input CSS classes */
  protected readonly inputClasses = computed(() => {
    const borderColor = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-neutral-600';

    return [
      'w-full h-10 pl-3 pr-10 text-sm rounded-md border',
      'bg-white dark:bg-neutral-900',
      'text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'transition-colors duration-150',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      borderColor,
    ].join(' ');
  });

  /** Whether to show error */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);

  /** Strength label text color */
  protected readonly strengthLabelColor = computed(() => {
    const colors: Record<AfPasswordStrength, string> = {
      empty: '',
      weak: 'text-red-500',
      fair: 'text-amber-500',
      good: 'text-blue-500',
      strong: 'text-green-500 dark:text-green-400',
    };
    return colors[this.strength().level];
  });

  /** Toggle password visibility */
  protected toggleVisibility(): void {
    this.visible.update((v) => !v);
  }

  /** Calculate password strength */
  private calculateStrength(value: string): StrengthResult {
    if (!value) {
      return { level: 'empty', score: 0, label: '', color: '', checks: [] };
    }

    const checks: PasswordCheck[] = [
      { label: `Mínimo ${this.minLength()} caracteres`, passed: value.length >= this.minLength() },
      { label: 'Letra maiúscula', passed: /[A-Z]/.test(value) },
      { label: 'Letra minúscula', passed: /[a-z]/.test(value) },
      { label: 'Número', passed: /[0-9]/.test(value) },
      { label: 'Caractere especial', passed: /[^A-Za-z0-9]/.test(value) },
    ];

    const passed = checks.filter((c) => c.passed).length;

    const levels: { min: number; level: AfPasswordStrength; label: string; color: string }[] = [
      { min: 5, level: 'strong', label: 'Senha forte', color: 'bg-green-500' },
      { min: 4, level: 'good', label: 'Senha boa', color: 'bg-blue-500' },
      { min: 3, level: 'fair', label: 'Senha razoável', color: 'bg-amber-500' },
      { min: 0, level: 'weak', label: 'Senha fraca', color: 'bg-red-500' },
    ];

    const result = levels.find((l) => passed >= l.min)!;

    return {
      level: result.level,
      score: passed <= 1 ? 1 : passed <= 2 ? 2 : passed <= 3 ? 3 : 4,
      label: result.label,
      color: result.color,
      checks,
    };
  }
}
