import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';
import { type NegotiationTask } from 'src/app/core/services/negotiation-task.service';
import { ButtonComponent } from '@shared/components/buttons';

/**
 * Sidebar card showing upcoming tasks preview.
 */
@Component({
  selector: 'app-negotiation-upcoming-tasks',
  standalone: true,
  imports: [LucideAngularModule, ButtonComponent],
  templateUrl: './negotiation-upcoming-tasks.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'flex' },
})
export class NegotiationUpcomingTasksComponent {
  readonly tasks = input<NegotiationTask[]>([]);
  readonly createTaskRequested = output<void>();

  readonly pendingCount = computed(
    () => this.tasks().filter((task) => !task.is_completed && task.status !== 'done').length,
  );
}
