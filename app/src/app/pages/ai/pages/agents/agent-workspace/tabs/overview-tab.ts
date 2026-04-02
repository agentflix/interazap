import { ChangeDetectionStrategy, Component, input, inject, effect } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfSelectInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { type AiAgent } from '@ai/models/ai.model';
import { AI_MODEL_OPTIONS } from '@ai/constants/ai-model-options';

/**
 * Overview tab for the agent workspace.
 * Contains basic config fields organized in card sections.
 */
@Component({
  selector: 'app-agent-overview-tab',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfTextInputComponent,
    AfSelectInputComponent,
    AfTextareaInputComponent,
    AfSwitchInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './overview-tab.html',
})
export class AgentOverviewTabComponent {
  private readonly fb = inject(FormBuilder);

  readonly agent = input.required<AiAgent>();

  readonly form = this.fb.group({
    name: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(100)]],
    description: [''],
    type: ['sales' as string, [Validators.required]],
    model_id: ['gpt-4o-mini'],
    temperature: [0.7, [Validators.required, Validators.min(0), Validators.max(2)]],
    max_tokens: [2048, [Validators.required, Validators.min(100), Validators.max(8192)]],
    fallback_message: [''],
    is_active: [true],
  });

  readonly typeOptions: AfSelectOption[] = [
    { value: 'sales', label: 'Vendas' },
    { value: 'support', label: 'Suporte' },
    { value: 'qualifier', label: 'Qualificador' },
    { value: 'general', label: 'Geral (Primário)' },
  ];

  readonly modelOptions = AI_MODEL_OPTIONS;

  constructor() {
    effect(() => {
      const agent = this.agent();
      this.form.patchValue({
        name: agent.name,
        description: agent.description ?? '',
        type: agent.type ?? 'sales',
        model_id: agent.model_id ?? 'gpt-4o-mini',
        temperature: agent.temperature,
        max_tokens: agent.max_tokens,
        fallback_message: agent.fallback_message ?? '',
        is_active: agent.is_active,
      });
    });
  }
}
