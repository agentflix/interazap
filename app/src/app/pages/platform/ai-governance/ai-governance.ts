import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

/**
 * Layout root for AI prompt governance pages.
 * Navigation is handled by the sidenav accordion — renders only the child outlet.
 */
@Component({
  selector: 'app-ai-governance',
  standalone: true,
  imports: [RouterOutlet],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './ai-governance.html',
})
// eslint-disable-next-line @typescript-eslint/no-extraneous-class
export class AiGovernanceComponent {}

export default AiGovernanceComponent;
