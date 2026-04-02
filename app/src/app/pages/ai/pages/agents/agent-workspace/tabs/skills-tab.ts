import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  signal,
  type OnInit,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfIconButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfSelectInputComponent,
  AfStatusBadgeComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import {
  type AiAgentSkill,
  type AiAgentSkillPayload,
  type AiSkillType,
} from '@ai/models/ai.model';
import { AiAgentService } from '@ai/services/ai-agent.service';

const VALID_SKILL_TYPES: readonly AiSkillType[] = [
  'classification',
  'extraction',
  'routing',
  'validation',
  'summarization',
  'custom',
];

/**
 * Skills tab — CRUD for agent skills.
 */
@Component({
  selector: 'app-agent-skills-tab',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfButtonComponent,
    AfIconButtonComponent,
    AfLoadingButtonComponent,
    AfModalComponent,
    AfTextInputComponent,
    AfTextareaInputComponent,
    AfSelectInputComponent,
    AfSwitchInputComponent,
    AfStatusBadgeComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './skills-tab.html',
})
export class AgentSkillsTabComponent implements OnInit {
  private readonly agentService = inject(AiAgentService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);

  readonly agentId = input.required<string>();
  readonly skills = signal<AiAgentSkill[]>([]);
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);
  readonly saving = signal(false);
  readonly showModal = signal(false);
  readonly editingSkill = signal<AiAgentSkill | null>(null);

  readonly skillForm = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.minLength(2)]],
    skill_type: ['custom' as AiSkillType, [Validators.required]],
    description: [''],
    prompt_append: [''],
    priority: [0],
    is_active: [true],
  });

  readonly skillTypeOptions: AfSelectOption[] = [
    { value: 'classification', label: 'Classificação' },
    { value: 'extraction', label: 'Extração' },
    { value: 'routing', label: 'Roteamento' },
    { value: 'validation', label: 'Validação' },
    { value: 'summarization', label: 'Sumarização' },
    { value: 'custom', label: 'Personalizada' },
  ];

  ngOnInit(): void {
    this.loadSkills();
  }

  /**
   * Format skill type label.
   */
  formatSkillType(type: AiSkillType | undefined): string {
    const safeType = this.normalizeSkillType(type);
    const labels: Record<AiSkillType, string> = {
      classification: 'Classificação',
      extraction: 'Extração',
      routing: 'Roteamento',
      validation: 'Validação',
      summarization: 'Sumarização',
      custom: 'Personalizada',
    };
    return labels[safeType];
  }

  /**
   * Get class for skill type icon.
   */
  skillIconClass(type: AiSkillType | undefined): string {
    const safeType = this.normalizeSkillType(type);
    const classes: Record<string, string> = {
      classification: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
      extraction: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
      routing: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
      validation: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
      summarization: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400',
    };
    return (
      classes[safeType] ??
      'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'
    );
  }

  /**
   * Opens modal for creating new skill.
   */
  openCreateModal(): void {
    this.editingSkill.set(null);
    this.skillForm.reset({
      name: '',
      skill_type: 'custom',
      description: '',
      prompt_append: '',
      priority: 0,
      is_active: true,
    });
    this.showModal.set(true);
  }

  /**
   * Opens modal for editing existing skill.
   */
  openEditModal(skill: AiAgentSkill): void {
    const metadata = this.normalizeSkillMetadata(skill);

    this.editingSkill.set(skill);
    this.skillForm.patchValue({
      name: skill.name,
      skill_type: metadata.type,
      description: skill.description ?? '',
      prompt_append: (skill.metadata as Record<string, string>)?.['prompt_append'] ?? '',
      priority: metadata.priority,
      is_active: skill.is_active,
    });
    this.showModal.set(true);
  }

  /**
   * Close the modal.
   */
  closeModal(): void {
    this.showModal.set(false);
    this.editingSkill.set(null);
  }

  /**
   * Save (create or update) a skill.
   */
  saveSkill(): void {
    if (this.skillForm.invalid) return;

    this.saving.set(true);
    const formValue = this.skillForm.getRawValue();
    const payload: AiAgentSkillPayload = {
      name: formValue.name,
      description: formValue.description,
      is_active: formValue.is_active,
      metadata: {
        type: this.normalizeSkillType(formValue.skill_type),
        priority: this.normalizePriority(formValue.priority),
        prompt_append: formValue.prompt_append || '',
      },
    };
    const editing = this.editingSkill();

    const request$ =
      editing !== null
        ? this.agentService.updateSkill(this.agentId(), editing.id, payload)
        : this.agentService.createSkill(this.agentId(), payload);

    request$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.saving.set(false);
        this.closeModal();
        this.toast.success(editing !== null ? 'Skill atualizada.' : 'Skill criada.');
        this.loadSkills();
      },
      error: () => {
        this.saving.set(false);
        this.toast.error('Erro ao salvar skill.');
      },
    });
  }

  /**
   * Delete a skill.
   */
  deleteSkill(skill: AiAgentSkill): void {
    if (!confirm(`Excluir skill "${skill.name}"?`)) return;

    this.agentService
      .deleteSkill(this.agentId(), skill.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toast.success('Skill excluída.');
          this.loadSkills();
        },
        error: () => this.toast.error('Erro ao excluir skill.'),
      });
  }

  private loadSkills(): void {
    const id = this.agentId();
    if (!id) return;

    this.loading.set(true);
    this.error.set(null);

    this.agentService
      .getSkills(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (skills) => {
          this.skills.set(skills.map((skill) => this.normalizeSkill(skill)));
          this.loading.set(false);
        },
        error: () => {
          this.error.set('Erro ao carregar skills.');
          this.loading.set(false);
        },
      });
  }

  private normalizeSkill(skill: AiAgentSkill): AiAgentSkill {
    const metadata = this.normalizeSkillMetadata(skill);

    return {
      ...skill,
      skill_type: metadata.type,
      priority: metadata.priority,
      metadata,
    };
  }

  private normalizeSkillMetadata(skill: AiAgentSkill): { type: AiSkillType; priority: number } {
    const metadata = skill.metadata ?? null;
    const typeCandidate = metadata?.type ?? skill.skill_type;
    const priorityCandidate = metadata?.priority ?? skill.priority;

    return {
      type: this.normalizeSkillType(typeCandidate),
      priority: this.normalizePriority(priorityCandidate),
    };
  }

  private normalizeSkillType(type: AiSkillType | string | undefined): AiSkillType {
    return this.isSkillType(type) ? type : 'custom';
  }

  private isSkillType(type: string | undefined): type is AiSkillType {
    return type !== undefined && VALID_SKILL_TYPES.includes(type as AiSkillType);
  }

  private normalizePriority(priority: number | string | null | undefined): number {
    if (typeof priority === 'number' && Number.isFinite(priority)) {
      return priority;
    }

    const parsed = Number(priority);
    return Number.isFinite(parsed) ? parsed : 0;
  }
}
