import { ChangeDetectionStrategy, Component, input, output, viewChild } from '@angular/core';
import { ButtonComponent, LoadingButtonComponent } from '@shared/components/buttons';
import { ModalComponent } from '@shared/components/modal/modal';
import { type Event as CRMEvent } from 'src/app/core/services/event.service';
import { AgendaFormComponent } from '../agenda-form/agenda-form';

/**
 * Agenda event editor component for the Crm module.
 * @selector app-agenda-event-editor
 */
@Component({
  selector: 'app-agenda-event-editor',
  standalone: true,
  imports: [ModalComponent, AgendaFormComponent, ButtonComponent, LoadingButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agenda-event-editor.html',
})
export class AgendaEventEditorComponent {
  readonly isOpen = input(false);
  readonly event = input<CRMEvent | null>(null);
  readonly initialData = input<Partial<CRMEvent> | null>(null);

  readonly saved = output<CRMEvent>();
  readonly closed = output<void>();

  readonly editorFormRef = viewChild<AgendaFormComponent>('editorForm');
}
