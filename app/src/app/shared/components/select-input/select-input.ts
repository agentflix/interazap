import {
  Component,
  ChangeDetectionStrategy,
  input,
  computed,
  signal,
  inject,
  ElementRef,
  effect,
  type OnDestroy,
} from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';
import { AfScrollAreaComponent } from '../scroll-area/scroll-area';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfSelectOption, SelectOption } from './select-input.model';
export * from './select-input.model';

/**
 * Searchable select dropdown for InteraZap UI Kit.
 *
 * @description Custom dropdown with a search filter at the top.
 * Options are filtered in real-time. Single-select with FormControl sync.
 *
 * @example
 * ```html
 * <af-select-input
 *   [control]="statusControl"
 *   label="Status"
 *   [options]="statusOptions"
 *   placeholder="Selecione..."
 * />
 * ```
 */
@Component({
  selector: 'af-select-input, app-select-input',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfFormLabelComponent,
    AfFormErrorComponent,
    LucideAngularModule,
    AfScrollAreaComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './select-input.html',
  host: {
    '(document:click)': 'onDocumentClick()',
    '(window:scroll)': 'onWindowScroll()',
    '(window:resize)': 'onWindowResize()',
  },
})
export class AfSelectInputComponent implements OnDestroy {
  /** Força recomputações quando estado/valor do FormControl muda */
  private readonly controlRevision = signal(0);

  /** FormControl for the selected value */
  readonly control = input.required<FormControl<string | number | null>>();

  /** Options array */
  readonly options = input.required<readonly AfSelectOption[]>();

  /** Label text */
  readonly label = input<string>();

  /** Input size — 'sm' matches compact h-8 (32 px), 'md' matches default h-10 (40 px) */
  readonly size = input<'sm' | 'md'>('md');

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  /** Placeholder when nothing is selected */
  readonly placeholder = input('Selecione...');

  /** Legacy placeholder alias */
  readonly emptyLabel = input<string | undefined>(undefined);

  /** Legacy trigger class name */
  readonly classSelect = input('');

  /** Whether the dropdown has a search filter */
  readonly searchable = input(true);

  /** Required asterisk */
  readonly required = input(false);

  /** Error message */
  readonly errorMessage = input('Selecione uma opção.');

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Accessible label for screen readers */
  readonly ariaLabel = input<string>();

  /** Accessible role for the trigger */
  readonly role = input<string>('combobox');

  /** Open state */
  protected readonly isOpen = signal(false);

  /** Search query */
  protected readonly searchQuery = signal('');

  /** Label of currently selected option */
  protected selectedLabel(): string {
    this.controlRevision();
    const selectedValue = this.control().value;
    const selectedOption = this.options().find(
      (option) => String(option.value) === String(selectedValue),
    );
    return selectedOption?.label ?? '';
  }

  /** Unique ID */
  protected readonly inputId = `select-${Math.random().toString(36).slice(2, 9)}`;

  /** Dropdown fixed position */
  protected readonly dropdownPos = signal({ top: 0, left: 0, width: 0 });

  private readonly elRef = inject(ElementRef);

  /** Scroll listeners for ancestor elements */
  private scrollCleanups: (() => void)[] = [];

  /** Filtered options based on search query */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Filtered options based on search query */
  protected readonly filteredOptions = computed(() => {
    const query = this.searchQuery().toLowerCase();
    if (!query) return this.options();
    return this.options().filter((o) => o.label.toLowerCase().includes(query));
  });

  /** Dynamic trigger classes */
  protected readonly triggerClasses = computed(() => {
    const borderColor = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-neutral-600';

    const sizeClasses = this.size() === 'sm' ? 'h-8 px-3 text-xs' : 'h-10 px-3 text-sm';

    const disabledClasses = this.isDisabled()
      ? 'opacity-50 cursor-not-allowed pointer-events-none'
      : '';

    return [
      'w-full rounded-md border',
      sizeClasses,
      'bg-white dark:bg-neutral-900',
      'flex items-center justify-between gap-2',
      'transition-colors',
      this.isDisabled() ? '' : 'cursor-pointer',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      borderColor,
      this.classSelect(),
      disabledClasses,
    ].join(' ');
  });

  /** Whether to show error */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);

  /** Whether the control is disabled */
  protected readonly isDisabled = computed(() => {
    this.controlRevision();
    return this.control()?.disabled ?? false;
  });

  constructor() {
    effect((onCleanup) => {
      const subscription = this.control().events.subscribe(() => {
        this.controlRevision.update((current) => current + 1);
      });
      onCleanup(() => subscription.unsubscribe());
    });
  }

  /** Toggle dropdown */
  protected toggle(event: Event): void {
    if (this.isDisabled()) {
      event.preventDefault();
      event.stopPropagation();
      return;
    }
    event.stopPropagation();
    this.isOpen.update((v) => !v);
    if (this.isOpen()) {
      this.updateDropdownPosition();
      this.attachScrollListeners();
    } else {
      this.searchQuery.set('');
      this.detachScrollListeners();
    }
  }

  /** Select an option */
  protected selectOption(option: AfSelectOption, event: Event): void {
    event.stopPropagation();
    this.control().setValue(option.value);
    this.control().markAsTouched();
    this.isOpen.set(false);
    this.searchQuery.set('');
  }

  /** Compare selected values in a type-safe way (string/number) */
  protected isSelected(optionValue: string | number): boolean {
    const selectedValue = this.control().value;
    if (selectedValue === null) {
      return false;
    }

    return String(optionValue) === String(selectedValue);
  }

  /** Filter options */
  protected onSearch(event: Event): void {
    this.searchQuery.set((event.target as HTMLInputElement).value);
  }

  /** Close on outside click */
  protected onDocumentClick(): void {
    if (this.isOpen()) {
      this.isOpen.set(false);
      this.searchQuery.set('');
      this.detachScrollListeners();
    }
  }

  /** Reposition or close on window scroll */
  protected onWindowScroll(): void {
    if (this.isOpen()) {
      this.updateDropdownPosition();
    }
  }

  /** Reposition or close on window resize */
  protected onWindowResize(): void {
    if (this.isOpen()) {
      this.updateDropdownPosition();
    }
  }

  /** Calculate dropdown position based on trigger getBoundingClientRect */
  private updateDropdownPosition(): void {
    const trigger: HTMLElement | null =
      this.elRef.nativeElement.querySelector('button[aria-haspopup]');
    if (!trigger) return;

    const rect = trigger.getBoundingClientRect();
    const gap = 4;
    const dropdownHeight = 220; // max-h-48 (192px) + search bar (~28px)

    // Check if there's space below; if not, position above
    const spaceBelow = window.innerHeight - rect.bottom;
    const top =
      spaceBelow >= dropdownHeight + gap ? rect.bottom + gap : rect.top - dropdownHeight - gap;

    this.dropdownPos.set({
      top: Math.max(0, top),
      left: rect.left,
      width: rect.width,
    });
  }

  /** Attach scroll listeners to all scrollable ancestors */
  private attachScrollListeners(): void {
    this.detachScrollListeners();

    let parent = this.elRef.nativeElement.parentElement;
    while (parent) {
      const style = window.getComputedStyle(parent);
      const overflow = style.overflow + style.overflowY;
      if (overflow.includes('auto') || overflow.includes('scroll')) {
        const handler = () => this.updateDropdownPosition();
        parent.addEventListener('scroll', handler, { passive: true });
        const el = parent;
        this.scrollCleanups.push(() => el.removeEventListener('scroll', handler));
      }
      parent = parent.parentElement;
    }
  }

  /** Detach all scroll listeners */
  private detachScrollListeners(): void {
    this.scrollCleanups.forEach((fn) => fn());
    this.scrollCleanups = [];
  }

  ngOnDestroy(): void {
    this.detachScrollListeners();
  }
}

export const SelectInputComponent = AfSelectInputComponent;
