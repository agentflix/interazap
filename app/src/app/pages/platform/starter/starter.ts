import { ChangeDetectionStrategy, Component } from '@angular/core';
import { AfCardComponent, AfPageTitleComponent } from '@shared/components';

/**
 * Página inicial do módulo de plataforma.
 */
@Component({
  selector: 'app-starter',
  standalone: true,
  imports: [AfPageTitleComponent, AfCardComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './starter.html',
})
// eslint-disable-next-line @typescript-eslint/no-extraneous-class
export class Starter {}
