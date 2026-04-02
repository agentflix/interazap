import { ChangeDetectionStrategy, Component } from '@angular/core';
import { AfCardComponent, AfPageTitleComponent } from '@shared/components';

/**
 * Starter page component for the Platform module.
 * @selector app-starter
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
