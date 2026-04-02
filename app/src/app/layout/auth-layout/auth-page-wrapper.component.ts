import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  selector: 'app-auth-page-wrapper',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './auth-page-wrapper.component.html',
})
export class AuthPageWrapperComponent {
  readonly title = input.required<string>();
  readonly subtitle = input.required<string>();
  readonly eyebrow = input<string>('InteraZap Access');
  readonly highlight = input<string>('');
}
