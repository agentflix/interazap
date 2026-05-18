import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfSelectInputComponent,
  AfTextInputComponent,
} from '@shared/components';
import { type User } from '@core/models/user.model';
import { type ChatRoutingQueueAgent } from '../../../services/chat-routing-queue.service';

@Component({
  selector: 'app-routing-agent-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfSelectInputComponent,
    AfTextInputComponent,
    AfButtonComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './routing-agent-form.html',
})
export class RoutingAgentFormComponent {
  readonly users = input.required<User[]>();
  readonly agents = input.required<ChatRoutingQueueAgent[]>();
  readonly addAgent = output<{ userId: string; position?: number }>();

  readonly form = new FormGroup({
    userId: new FormControl<string | null>(null, Validators.required),
    position: new FormControl<number | null>(null),
  });

  protected readonly availableUsers = computed(() => {
    const addedUserIds = new Set(this.agents().map((a) => a.user_id));
    return this.users()
      .filter((u) => !addedUserIds.has(u.id))
      .map((u) => ({ value: u.id, label: u.name }));
  });

  protected onSubmit(): void {
    if (this.form.invalid) {
      return;
    }
    const userId = this.form.controls.userId.value!;
    const position = this.form.controls.position.value ?? undefined;
    this.addAgent.emit({ userId, position });
    this.form.reset();
  }
}
