import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfPageTitleComponent,
  AfSelectInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { type AiAgent, type AiAgentPayload } from '@ai/models/ai.model';
import { AiAgentService } from '@ai/services/ai-agent.service';
import { AI_MODEL_OPTIONS } from '@ai/constants/ai-model-options';

/**
 * Formulário para criação e edição de Agentes de IA.
 *
 * Contexto: gerencia o formulário reativo com nome, tipo, modelo, prompt do sistema e parâmetros
 * de geração. Em modo de edição, carrega os dados do agente via rota (/id). Navega de volta
 * para /ai/agents após salvar ou cancelar.
 */
@Component({
  selector: 'app-ai-agent-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfPageTitleComponent,
    AfTextInputComponent,
    AfSelectInputComponent,
    AfTextareaInputComponent,
    AfSwitchInputComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agent-form.html',
})
export class AgentFormComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly agentService = inject(AiAgentService);
  private readonly toast = inject(ToastService);

  readonly isLoading = signal(false);
  readonly isSaving = signal(false);
  readonly agentId = signal<string | null>(null);

  readonly isEditMode = computed(() => this.agentId() !== null);
  readonly pageTitle = computed(() => (this.isEditMode() ? 'Editar Agente' : 'Novo Agente'));

  readonly form = this.fb.group({
    name: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(100)]],
    type: ['sales' as AiAgent['type'], [Validators.required]],
    model_id: ['gpt-4o-mini'],
    system_prompt: [''],
    max_tokens: [2048, [Validators.required, Validators.min(100), Validators.max(8192)]],
    temperature: [0.7, [Validators.required, Validators.min(0), Validators.max(2)]],
    top_p: [1.0, [Validators.required, Validators.min(0), Validators.max(1)]],
    is_active: [true],
  });

  readonly agentTypes: AfSelectOption[] = [
    { value: 'sales', label: 'Vendas' },
    { value: 'support', label: 'Suporte' },
    { value: 'qualifier', label: 'Qualificador' },
    { value: 'general', label: 'Geral (Primário)' },
  ];

  readonly modelOptions = AI_MODEL_OPTIONS;

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.agentId.set(id);
      this.loadAgent(id);
    }
  }

  /**
   * Carrega os dados do agente para edição.
   * @param id ID do agente a ser carregado
   */
  private loadAgent(id: string): void {
    this.isLoading.set(true);
    this.agentService.get(id).subscribe({
      next: (agent) => {
        this.form.patchValue({
          name: agent.name,
          type: agent.type,
          model_id: agent.model_id ?? 'gpt-4o-mini',
          system_prompt: agent.system_prompt ?? '',
          max_tokens: agent.max_tokens,
          temperature: agent.temperature,
          top_p: agent.top_p,
          is_active: agent.is_active,
        });
        this.isLoading.set(false);
      },
      error: () => {
        this.toast.error('Erro ao carregar agente.');
        this.isLoading.set(false);
        void this.router.navigate(['/ai/agents']);
      },
    });
  }

  /**
   * Envia o formulário para criar ou atualizar o agente.
   */
  submit(): void {
    if (this.form.invalid || this.isSaving()) return;

    this.isSaving.set(true);
    const payload = this.form.value as AiAgentPayload;

    const request$ = this.isEditMode()
      ? this.agentService.update(this.agentId()!, payload)
      : this.agentService.create(payload);

    request$.subscribe({
      next: () => {
        this.isSaving.set(false);
        this.toast.success(this.isEditMode() ? 'Agente atualizado.' : 'Agente criado.');
        void this.router.navigate(['/ai/agents']);
      },
      error: () => {
        this.isSaving.set(false);
        this.toast.error('Erro ao salvar agente.');
      },
    });
  }

  /**
   * Navega de volta para a lista de agentes.
   */
  goBack(): void {
    void this.router.navigate(['/ai/agents']);
  }
}
