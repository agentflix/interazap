import {
  type ElementRef,
  type AfterViewInit,
  Component,
  ChangeDetectionStrategy,
  input,
  computed,
  viewChild,
} from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * AfTextareaInputComponent — Multi-line text input with optional auto-resize,
 * character counter, label, and error support.
 *
 * @example
 * ```html
 * <af-textarea-input
 *   [control]="descriptionControl"
 *   label="Descrição"
 *   placeholder="Digite a descrição..."
 *   [maxLength]="500"
 *   resize="auto"
 * />
 * ```
 */
@Component({
  selector: 'af-textarea-input, app-textarea-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './textarea-input.html',
})
export class AfTextareaInputComponent implements AfterViewInit {
  /** FormControl for the textarea */
  readonly control = input.required<FormControl<string | null>>();

  /** Label text */
  readonly label = input<string>();

  /** Placeholder */
  readonly placeholder = input('');

  /** Number of visible text rows */
  readonly rows = input(3);

  /** Maximum character length */
  readonly maxLength = input<number | null>(null);

  /** Show required asterisk */
  readonly required = input(false);

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the textarea */
  readonly helpText = input<string>();

  /** Error message */
  readonly errorMessage = input('Campo obrigatório.');

  /** Extra CSS class for textarea element (legacy compatibility) */
  readonly classTextarea = input('');

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Resize behavior: none, vertical, or auto (auto-grows based on content) */
  readonly resize = input<'none' | 'vertical' | 'auto'>('vertical');

  /** Reference to the native textarea element */
  private readonly textareaRef = viewChild<ElementRef<HTMLTextAreaElement>>('textarea');

  /** Unique ID */
  protected readonly inputId = `textarea-${Math.random().toString(36).slice(2, 9)}`;

  /** Current character count */
  protected readonly charCount = computed(() => {
    const value = this.control()?.value ?? '';
    return value.length;
  });

  /** Whether to show error */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);

  /** Dynamic classes */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Dynamic classes */
  protected readonly textareaClasses = computed(() => {
    const resizeMap: Record<string, string> = {
      none: 'resize-none',
      vertical: 'resize-y',
      auto: 'resize-none',
    };

    const base = [
      'w-full rounded-md border bg-white dark:bg-neutral-900',
      'text-sm text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'px-3 py-2',
      'transition-colors duration-150',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      resizeMap[this.resize()],
    ];

    const borderColor = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-white/[0.14]';

    return [...base, borderColor, this.classTextarea()].join(' ');
  });

  ngAfterViewInit(): void {
    if (this.resize() === 'auto') {
      this.autoResize();
    }
  }

  /** Handle input events for auto-resize */
  protected onInput(): void {
    if (this.resize() === 'auto') {
      this.autoResize();
    }
  }

  /** Adjust textarea height based on scroll height */
  private autoResize(): void {
    const el = this.textareaRef()?.nativeElement;
    if (el) {
      el.style.height = 'auto';
      el.style.height = `${el.scrollHeight}px`;
    }
  }
}

export const TextareaInputComponent = AfTextareaInputComponent;
