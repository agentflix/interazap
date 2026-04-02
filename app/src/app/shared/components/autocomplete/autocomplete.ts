import {
  type ElementRef,
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
  computed,
  HostListener,
  viewChild,
} from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { LucideAngularModule } from 'lucide-angular';
import { AfScrollAreaComponent } from '../scroll-area/scroll-area';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfAutocompleteOption } from './autocomplete.model';
export * from './autocomplete.model';



/**
 * AfAutocompleteComponent — Text input with filtered suggestion dropdown.
 *
 * @example
 * ```html
 * <af-autocomplete
 *   [control]="cityCtrl"
 *   [options]="cities"
 *   label="Cidade"
 *   (optionSelected)="onCity($event)"
 * />
 * ```
 */
@Component({
  selector: 'af-autocomplete',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, LucideAngularModule, AfScrollAreaComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './autocomplete.html',
})
export class AfAutocompleteComponent {
  /** FormControl binding */
  readonly control = input.required<FormControl<string>>();

  /** Available options */
  readonly options = input<AfAutocompleteOption[]>([]);

  /** Field label */
  readonly label = input('');

  /** Placeholder */
  readonly placeholder = input('Buscar...');

  /** Required */
  readonly required = input(false);

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  /** Emitted when an option is selected */
  readonly optionSelected = output<string>();

  protected readonly showDropdown = signal(false);

  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  private readonly rootRef = viewChild<ElementRef<HTMLElement>>('root');

  protected readonly filtered = computed(() => {
    const term = this.control().value?.toLowerCase() ?? '';
    if (!term) return this.options().slice(0, 20);
    return this.options()
      .filter((o) => o.label.toLowerCase().includes(term))
      .slice(0, 20);
  });

  protected onInput(): void {
    this.showDropdown.set(true);
  }

  protected selectOption(opt: AfAutocompleteOption): void {
    this.control().setValue(opt.label);
    this.optionSelected.emit(opt.value);
    this.showDropdown.set(false);
  }

  @HostListener('document:click', ['$event'])
  protected onDocumentClick(event: MouseEvent): void {
    const root = this.rootRef()?.nativeElement;
    if (root && event.target instanceof Node && !root.contains(event.target)) {
      this.showDropdown.set(false);
    }
  }
}
