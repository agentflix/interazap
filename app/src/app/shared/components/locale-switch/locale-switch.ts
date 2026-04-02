import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfLocaleOption } from './locale-switch.model';
export * from './locale-switch.model';



/**
 * AfLocaleSwitchComponent — Language/locale selector.
 *
 * @example
 * ```html
 * <af-locale-switch [locales]="availableLocales" [current]="'pt-BR'" (localeChange)="setLocale($event)" />
 * ```
 */
@Component({
  selector: 'af-locale-switch',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './locale-switch.html',
})
export class AfLocaleSwitchComponent {
  /** Available locales */
  readonly locales = input<AfLocaleOption[]>([]);

  /** Current locale code */
  readonly current = input('');

  /** Locale changed */
  readonly localeChange = output<string>();
}
