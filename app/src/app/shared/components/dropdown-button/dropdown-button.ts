import {
  type ElementRef,
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
  viewChild,
  HostListener,
} from '@angular/core';
import { AfButtonComponent } from '../button/button';
import { LucideAngularModule } from 'lucide-angular';

import type { AfDropdownOption } from './dropdown-button.model';
export * from './dropdown-button.model';



/**
 * AfDropdownButtonComponent — Button with dropdown menu of actions.
 *
 * @example
 * ```html
 * <af-dropdown-button
 *   label="Ações"
 *   [options]="actionOptions"
 *   (optionSelected)="onAction($event)"
 * />
 * ```
 */
@Component({
  selector: 'af-dropdown-button',
  standalone: true,
  imports: [AfButtonComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './dropdown-button.html',
})
export class AfDropdownButtonComponent {
  /** Button label */
  readonly label = input.required<string>();

  /** Dropdown options */
  readonly options = input.required<AfDropdownOption[]>();

  /** Visual variant */
  readonly variant = input<'primary' | 'secondary' | 'ghost' | 'danger' | 'outline'>('secondary');

  /** Button size */
  readonly size = input<'xs' | 'sm' | 'md' | 'lg'>('md');

  /** Placement of dropdown */
  readonly placement = input<'bottom' | 'bottom-end' | 'top'>('bottom');

  /** Disabled state */
  readonly disabled = input(false);

  /** Emitted when an option is selected */
  readonly optionSelected = output<string>();

  protected readonly isOpen = signal(false);

  private readonly rootRef = viewChild<ElementRef<HTMLElement>>('root');

  protected toggle(): void {
    this.isOpen.update((v) => !v);
  }

  protected select(opt: AfDropdownOption): void {
    if (opt.disabled) return;
    this.optionSelected.emit(opt.value);
    this.isOpen.set(false);
  }

  @HostListener('document:click', ['$event'])
  protected onDocumentClick(event: MouseEvent): void {
    if (!this.isOpen()) return;
    const root = this.rootRef()?.nativeElement;
    if (root && event.target instanceof Node && !root.contains(event.target)) {
      this.isOpen.set(false);
    }
  }
}
