import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';

@Component({
  selector: 'af-auth-layout',
  standalone: true,
  imports: [RouterOutlet, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './auth-layout.html',
})
// eslint-disable-next-line @typescript-eslint/no-extraneous-class
export class AuthLayoutComponent {}
