import { Component, ChangeDetectionStrategy, input, output, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfThemeSwitchComponent — Dark/light/system theme toggle.
 *
 * @example
 * ```html
 * <af-theme-switch [mode]="currentTheme()" (modeChange)="setTheme($event)" />
 * ```
 */
@Component({
  selector: 'af-theme-switch',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './theme-switch.html',
})
export class AfThemeSwitchComponent {
  /** Current theme mode */
  readonly mode = input<'light' | 'dark' | 'system'>('system');

  /** Theme changed */
  readonly modeChange = output<'light' | 'dark' | 'system'>();

  protected readonly options = [
    { value: 'light' as const, icon: 'sun', label: 'Claro' },
    { value: 'dark' as const, icon: 'moon', label: 'Escuro' },
    { value: 'system' as const, icon: 'monitor', label: 'Sistema' },
  ];
}
