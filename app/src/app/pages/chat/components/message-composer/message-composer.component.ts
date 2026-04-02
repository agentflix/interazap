import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Message composer component for the Chat module.
 * @selector app-message-composer
 */
@Component({
  selector: 'app-message-composer',
  standalone: true,
  templateUrl: './message-composer.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MessageComposerComponent {
  protected readonly isComposerReady = true;
}
