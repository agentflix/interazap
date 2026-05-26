import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  signal,
} from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfConfirmModalComponent,
  AfLoadingButtonComponent,
  AfPageTitleComponent,
  AfScrollAreaComponent,
  AfTextareaInputComponent,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { AiPromptService } from '@ai/services/ai-prompt.service';
import { type AiPrompt } from '@ai/models/ai.model';

interface PromptVersion {
  id: string;
  template: string;
  updated_at: string;
  is_current: boolean;
}

/**
 * Editor de prompts de IA com suporte a versionamento.
 *
 * Contexto: lista prompts na sidebar esquerda e exibe editor de template à direita.
 * Detecta variáveis no formato {{variavel}} e exibe-as como chips. Suporta rollback
 * para versão anterior. Exibe indicador de alterações não salvas.
 */
@Component({
  selector: 'app-ai-prompt-editor',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    RouterLink,
    LucideAngularModule,
    AfPageTitleComponent,
    AfTextareaInputComponent,
    AfButtonComponent,
    AfConfirmModalComponent,
    AfLoadingButtonComponent,
    AfScrollAreaComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './prompt-editor.html',
})
export class PromptEditorComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly promptService = inject(AiPromptService);
  private readonly toast = inject(ToastService);

  readonly isLoading = signal(true);
  readonly isSaving = signal(false);
  readonly hasError = signal(false);
  readonly showVersions = signal(false);
  readonly showDeleteConfirm = signal(false);

  readonly prompts = signal<AiPrompt[]>([]);
  readonly selectedPrompt = signal<AiPrompt | null>(null);
  readonly versions = signal<PromptVersion[]>([]);

  readonly form = this.fb.group({
    template: ['', [Validators.required, Validators.minLength(10)]],
  });

  private readonly templateValue = toSignal(this.form.get('template')!.valueChanges, {
    initialValue: this.form.get('template')!.value ?? '',
  });

  readonly hasChanges = computed(() => {
    const current = this.selectedPrompt();
    if (!current) return false;
    return this.templateValue() !== current.template;
  });

  readonly variablesList = computed(() => {
    const template = this.form.get('template')?.value ?? '';
    const matches = template.match(/\{\{(\w+)\}\}/g) ?? [];
    return [...new Set(matches.map((m: string) => m.replace(/\{\{|\}\}/g, '')))];
  });

  ngOnInit(): void {
    this.loadPrompts();
  }

  /**
   * Carrega todos os prompts disponíveis para seleção na sidebar.
   */
  private loadPrompts(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.promptService.list({ per_page: 50 }).subscribe({
      next: (response) => {
        this.prompts.set(response.data ?? []);
        if (response.data?.length > 0) {
          this.selectPrompt(response.data[0]);
        }
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
        this.hasError.set(true);
      },
    });
  }

  /**
   * Seleciona um prompt para edição e carrega seu template no editor.
   * @param prompt Prompt a ser editado
   */
  selectPrompt(prompt: AiPrompt): void {
    this.selectedPrompt.set(prompt);
    this.form.patchValue({ template: prompt.template });
    this.showVersions.set(false);
  }

  /**
   * Salva as alterações do template do prompt selecionado.
   */
  save(): void {
    const prompt = this.selectedPrompt();
    if (!prompt || this.form.invalid || this.isSaving()) return;

    this.isSaving.set(true);
    const template = this.form.get('template')!.value!;

    this.promptService.update(prompt.id, { template }).subscribe({
      next: (updated) => {
        this.selectedPrompt.set(updated);
        this.isSaving.set(false);
        this.toast.success('Prompt salvo com sucesso.');
      },
      error: () => {
        this.isSaving.set(false);
        this.toast.error('Erro ao salvar prompt.');
      },
    });
  }

  /**
   * Restaura o template para uma versão anterior (sem salvar automaticamente).
   * @param version Versão anterior a restaurar
   */
  rollback(version: PromptVersion): void {
    this.form.patchValue({ template: version.template });
    this.showVersions.set(false);
    this.toast.info('Template restaurado. Salve para confirmar.');
  }

  openDeleteConfirm(): void {
    this.showDeleteConfirm.set(true);
  }

  deletePrompt(): void {
    const prompt = this.selectedPrompt();
    if (!prompt || this.isSaving()) return;

    this.isSaving.set(true);

    this.promptService.delete(prompt.id).subscribe({
      next: () => {
        this.isSaving.set(false);
        this.showDeleteConfirm.set(false);
        this.toast.success('Prompt customizado removido com sucesso.');
        this.loadPrompts();
      },
      error: () => {
        this.isSaving.set(false);
        this.showDeleteConfirm.set(false);
        this.toast.error('Erro ao excluir prompt customizado.');
      },
    });
  }

  /**
   * Alterna a visibilidade do painel de histórico de versões.
   */
  toggleVersions(): void {
    this.showVersions.update((v) => !v);
  }

  /**
   * Navega de volta para a lista de agentes.
   */
  goBack(): void {
    void this.router.navigate(['/ai/agents']);
  }

  /**
   * Tenta recarregar a lista de prompts após erro.
   */
  retry(): void {
    this.loadPrompts();
  }
}
