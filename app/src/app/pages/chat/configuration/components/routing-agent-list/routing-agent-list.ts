import { ChangeDetectionStrategy, Component, input, output, signal } from '@angular/core';
import type { CdkDragDrop} from '@angular/cdk/drag-drop';
import { DragDropModule, moveItemInArray } from '@angular/cdk/drag-drop';
import { LucideAngularModule } from 'lucide-angular';
import { AfButtonComponent, AfSwitchInputComponent, AfTextInputComponent } from '@shared/components';
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
  readonly strategy = input<string>('round_robin');
  readonly reorder = output<ChatRoutingQueueAgent[]>();
  readonly toggleActive = output<{ userId: string; isActive: boolean }>();
  readonly remove = output<string>();
  readonly addSkill = output<{ userId: string; skill: string }>();
  readonly removeSkill = output<{ userId: string; skill: string }>();

  readonly newSkillByAgent = signal<Record<string, string>>({});

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

  protected onSkillInput(agent: ChatRoutingQueueAgent, value: string): void {
    this.newSkillByAgent.update((map) => ({ ...map, [agent.user_id]: value }));
  }

  protected onAddSkill(agent: ChatRoutingQueueAgent): void {
    const skill = this.newSkillByAgent()[agent.user_id]?.trim();
    if (!skill) return;
    this.addSkill.emit({ userId: agent.user_id, skill });
    this.newSkillByAgent.update((map) => ({ ...map, [agent.user_id]: '' }));
  }

  protected onRemoveSkill(agent: ChatRoutingQueueAgent, skill: string): void {
    this.removeSkill.emit({ userId: agent.user_id, skill });
  }
}
