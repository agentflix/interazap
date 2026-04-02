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
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import { AfScrollAreaComponent } from '../scroll-area/scroll-area';

import type { AfMentionUser } from './mention-input.model';
export * from './mention-input.model';



/**
 * AfMentionInputComponent — Text input with @mention suggestion dropdown.
 *
 * @example
 * ```html
 * <af-mention-input [users]="teamMembers" placeholder="Mencione com @..." (valueChange)="onText($event)" />
 * ```
 */
@Component({
  selector: 'af-mention-input',
  standalone: true,
  imports: [ReactiveFormsModule, LucideAngularModule, AfScrollAreaComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './mention-input.html',
})
export class AfMentionInputComponent {
  /** Placeholder text */
  readonly placeholder = input('');

  /** Textarea rows */
  readonly rows = input(3);

  /** Users available for mention */
  readonly users = input<AfMentionUser[]>([]);

  /** Value changed */
  readonly valueChange = output<string>();

  /** User mentioned */
  readonly mentioned = output<string>();

  protected readonly textCtrl = new FormControl('', { nonNullable: true });
  protected readonly showSuggestions = signal(false);
  protected readonly mentionQuery = signal('');

  private readonly rootRef = viewChild<ElementRef<HTMLElement>>('root');

  protected readonly filteredUsers = computed(() => {
    const q = this.mentionQuery().toLowerCase();
    if (!q) return this.users();
    return this.users().filter((u) => u.name.toLowerCase().includes(q));
  });

  protected onInput(): void {
    const value = this.textCtrl.value;
    this.valueChange.emit(value);

    // Check for @ mention
    const lastAt = value.lastIndexOf('@');
    if (lastAt >= 0 && lastAt === value.length - 1) {
      this.showSuggestions.set(true);
      this.mentionQuery.set('');
    } else if (lastAt >= 0) {
      const after = value.slice(lastAt + 1);
      if (!after.includes(' ')) {
        this.showSuggestions.set(true);
        this.mentionQuery.set(after);
      } else {
        this.showSuggestions.set(false);
      }
    } else {
      this.showSuggestions.set(false);
    }
  }

  protected selectUser(user: AfMentionUser): void {
    const value = this.textCtrl.value;
    const lastAt = value.lastIndexOf('@');
    const before = value.slice(0, lastAt);
    this.textCtrl.setValue(`${before}@${user.name} `);
    this.showSuggestions.set(false);
    this.mentioned.emit(user.id);
    this.valueChange.emit(this.textCtrl.value);
  }

  protected onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') this.showSuggestions.set(false);
  }

  @HostListener('document:click', ['$event'])
  protected onDocClick(event: MouseEvent): void {
    const root = this.rootRef()?.nativeElement;
    if (root && event.target instanceof Node && !root.contains(event.target)) {
      this.showSuggestions.set(false);
    }
  }
}
