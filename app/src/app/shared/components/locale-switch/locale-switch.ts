import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfLocaleOption } from './locale-switch.model';
export * from './locale-switch.model';



/**
 * Seletor de idioma/localidade.
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
  /** Localidades disponíveis */
  readonly locales = input<AfLocaleOption[]>([]);

  /** Código da localidade atual */
  readonly current = input('');

  /** Emitido quando a localidade muda */
  readonly localeChange = output<string>();
}
