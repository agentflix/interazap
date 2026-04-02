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
 * Editor for AI prompts with versioning support.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
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
   * Load all prompts for selection.
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
   * Select a prompt to edit.
   */
  selectPrompt(prompt: AiPrompt): void {
    this.selectedPrompt.set(prompt);
    this.form.patchValue({ template: prompt.template });
    this.showVersions.set(false);
  }

  /**
   * Save prompt changes.
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
   * Rollback to a previous version.
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
   * Toggle version history panel.
   */
  toggleVersions(): void {
    this.showVersions.update((v) => !v);
  }

  /**
   * Navigate back.
   */
  goBack(): void {
    void this.router.navigate(['/ai/agents']);
  }

  /**
   * Retry loading.
   */
  retry(): void {
    this.loadPrompts();
  }
}
