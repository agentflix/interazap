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

/**
 * Tag/chip input for AgentFlix UI Kit.
 *
 * @description User types text and presses Enter to add tags as pills.
 * Each pill has an × remove button. Value is stored as comma-separated
 * string in the FormControl. Supports max tags and duplicate prevention.
 *
 * @example
 * ```html
 * <af-tag-input
 *   [control]="tagsControl"
 *   label="Tags"
 *   placeholder="Adicione uma tag..."
 *   [maxTags]="5"
 * />
 * ```
 */
@Component({
  selector: 'af-tag-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tag-input.html',
})
export class AfTagInputComponent implements OnInit {
  /** FormControl holding the comma-separated tags */
  readonly control = input.required<FormControl<string>>();

  /** Label text */
  readonly label = input<string>();

  /** Container CSS class */
  readonly classContainer = input<string>('mb-4');

  /** Placeholder */
  readonly placeholder = input('Adicione uma tag...');

  /** Max number of tags (0 = unlimited) */
  readonly maxTags = input(0);

  /** Required asterisk */
  readonly required = input(false);

  /** Error message */
  readonly errorMessage = input('Campo obrigatório.');

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Internal tags list */
  protected readonly tags = signal<string[]>([]);

  /** Whether max tags reached */
  protected readonly maxReached = computed(() => {
    const max = this.maxTags();
    return max > 0 && this.tags().length >= max;
  });

  /** Unique ID */
  protected readonly inputId = `tag-${Math.random().toString(36).slice(2, 9)}`;

  private readonly destroyRef = inject(DestroyRef);

  /** Dynamic border classes */
  protected readonly containerBorderClasses = computed(() => {
    if (this.showError()) {
      return 'border-red-500 dark:border-red-400';
    }
    return 'border-neutral-300 dark:border-neutral-600 focus-within:ring-2 focus-within:ring-accent-500/30 focus-within:border-accent-500';
  });

  /** Whether to show error */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);

  ngOnInit(): void {
    const ctrl = this.control();
    if (ctrl) {
      // Parse initial value
      const initial = ctrl.value;
      if (initial) {
        this.tags.set(
          initial
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean),
        );
      }

      // Watch for external value changes
      ctrl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((value) => {
        const newTags = value
          ? value
              .split(',')
              .map((t: string) => t.trim())
              .filter(Boolean)
          : [];
        const currentTags = this.tags();
        if (JSON.stringify(newTags) !== JSON.stringify(currentTags)) {
          this.tags.set(newTags);
        }
      });
    }
  }

  /** Handle keyboard events */
  protected onKeyDown(event: KeyboardEvent): void {
    const input = event.target as HTMLInputElement;
    const value = input.value.trim();

    if (event.key === 'Enter' && value) {
      event.preventDefault();
      this.addTag(value);
      input.value = '';
    }

    if (event.key === 'Backspace' && !value && this.tags().length > 0) {
      this.tags.update((tags) => tags.slice(0, -1));
      this.syncControl();
    }
  }

  /** Add a tag */
  private addTag(value: string): void {
    if (this.maxReached()) return;

    // Prevent duplicates
    if (this.tags().some((t) => t.toLowerCase() === value.toLowerCase())) return;

    this.tags.update((tags) => [...tags, value]);
    this.syncControl();
  }

  /** Remove a tag */
  protected removeTag(tag: string, event: Event): void {
    event.stopPropagation();
    this.tags.update((tags) => tags.filter((t) => t !== tag));
    this.syncControl();
  }

  /** Focus the text input */
  protected focusInput(): void {
    const input = document.getElementById(this.inputId) as HTMLInputElement;
    input?.focus();
  }

  /** Sync tags to FormControl */
  private syncControl(): void {
    this.control().setValue(this.tags().join(','));
    this.control().markAsTouched();
  }
}
