import {
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  computed,
  inject,
  effect,
  ChangeDetectorRef,
  signal,
} from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * Switch/toggle input component for AgentFlix UI Kit.
 *
 * @description Styled toggle switch supporting both FormControl and standalone modes.
 *
 * @example
 * ```html
 * <!-- With FormControl -->
 * <af-switch-input
 *   [control]="form.controls.isActive"
 *   label="Ativado"
 * />
 *
 * <!-- Standalone -->
 * <af-switch-input
 *   [checked]="isEnabled"
 *   label="Notificações"
 *   (toggled)="onToggle($event)"
 * />
 * ```
 */
@Component({
  selector: 'af-switch-input, app-switch-input',
  standalone: true,
  imports: [ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './switch-input.html',
})
export class AfSwitchInputComponent {
  /** FormControl for reactive forms integration */
  readonly control = input<FormControl<boolean | null> | null>(null);

  /** Checked state when not using FormControl */
  readonly checked = input(false);

  /** Whether the switch is disabled */
  readonly disabled = input(false);

  /** Label text */
  readonly label = input<string>('');

  /** Label position relative to the switch */
  readonly labelPosition = input<'left' | 'right'>('right');

  /** data-test attribute for E2E tests */
  readonly dataTest = input<string>();

  /** aria-label for accessibility */
  readonly ariaLabel = input<string>();

  /** Extra classes for the container */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  /** Emits the new value whenever the switch toggles */
  readonly toggled = output<boolean>();

  /** Unique ID for the toggle element */
  protected readonly switchId = `switch-${Math.random().toString(36).slice(2, 9)}`;

  private readonly cdr = inject(ChangeDetectorRef);
  private readonly internalChecked = signal(false);

  constructor() {
    effect((onCleanup) => {
      const ctrl = this.control();
      if (ctrl) {
        this.internalChecked.set(!!ctrl.value);
        const sub = ctrl.valueChanges.subscribe((val) => {
          this.internalChecked.set(!!val);
          this.cdr.markForCheck();
        });
        onCleanup(() => sub.unsubscribe());
      }
    });
  }

  /** Resolves the current checked state from either FormControl or input */
  protected readonly isChecked = computed(() => {
    const ctrl = this.control();
    return ctrl ? this.internalChecked() : this.checked();
  });

  /** Computed container classes */
  protected readonly wrapperClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Computed container classes */
  protected readonly containerClasses = computed(() => {
    const base = 'inline-flex items-center align-middle gap-2.5';
    return base;
  });

  /** Track (background) styling based on checked state */
  protected readonly trackClasses = computed(() => {
    const base = [
      'relative inline-flex h-6 w-11 shrink-0 items-center align-middle rounded-full m-0 p-0',
      'border-2 border-transparent',
      'cursor-pointer transition-colors duration-200 ease-in-out',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/30 focus-visible:ring-offset-2',
      'disabled:opacity-50 disabled:cursor-not-allowed',
    ];

    const bg = this.isChecked() ? 'bg-accent-500' : 'bg-neutral-200 dark:bg-neutral-700';

    return [...base, bg].join(' ');
  });

  /** Thumb (knob) styling based on checked state */
  protected readonly thumbClasses = computed(() => {
    const base = [
      'pointer-events-none inline-block size-5 rounded-full',
      'bg-white shadow-sm ring-0 transition-transform duration-200 ease-in-out',
    ];

    const translate = this.isChecked() ? 'translate-x-5' : 'translate-x-0';

    return [...base, translate].join(' ');
  });

  /** Toggle the switch value */
  protected toggle(): void {
    if (this.disabled()) return;

    const ctrl = this.control();
    if (ctrl) {
      ctrl.setValue(!ctrl.value);
      ctrl.markAsTouched();
    }
    const newValue = ctrl ? (ctrl.value ?? false) : !this.checked();
    this.toggled.emit(newValue);
  }
}

export const SwitchInputComponent = AfSwitchInputComponent;
