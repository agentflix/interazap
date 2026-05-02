import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { CdkDragDrop, DragDropModule, moveItemInArray } from '@angular/cdk/drag-drop';
import { LucideAngularModule } from 'lucide-angular';
import { AfButtonComponent, AfSwitchInputComponent } from '@shared/components';
import { type ChatRoutingQueueAgent } from '../../../services/chat-routing-queue.service';

@Component({
  selector: 'app-routing-agent-list',
  standalone: true,
  imports: [DragDropModule, LucideAngularModule, AfButtonComponent, AfSwitchInputComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './routing-agent-list.html',
})
export class RoutingAgentListComponent {
  readonly agents = input.required<ChatRoutingQueueAgent[]>();
  readonly reorder = output<ChatRoutingQueueAgent[]>();
  readonly toggleActive = output<{ userId: string; isActive: boolean }>();
  readonly remove = output<string>();

  protected onDrop(event: CdkDragDrop<ChatRoutingQueueAgent[]>): void {
    const updated = [...this.agents()];
    moveItemInArray(updated, event.previousIndex, event.currentIndex);
    this.reorder.emit(updated);
  }

  protected onToggleActive(agent: ChatRoutingQueueAgent): void {
    this.toggleActive.emit({ userId: agent.user_id, isActive: !agent.is_active });
  }

  protected onRemove(userId: string): void {
    this.remove.emit(userId);
  }
}
