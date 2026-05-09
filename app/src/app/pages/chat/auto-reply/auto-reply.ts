import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { toast } from 'ngx-sonner';
import {
  AfCrudPageComponent,
  AfDataTableComponent,
  AfTableActionsComponent,
  AfStatusBadgeComponent,
  AfModalComponent,
  AfConfirmModalComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
  AfSelectInputComponent,
  AfCheckboxInputComponent,
  AfFileInputComponent,
  AfSwitchInputComponent,
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfAlertComponent,
  type AfSelectOption,
} from '@shared/components';
import {
  type AutoReplyAction,
  type AutoReplyActionType,
  type AutoReplyMatchType,
  type AutoReplyRule,
  AutoReplyService,
} from '@core/services/auto-reply.service';
import { type Department, DepartmentService } from '@core/services/department.service';

interface MessageTypeOption {
  id: string;
  label: string;
}

interface ActionTypeOption {
  id: 'message' | 'transfer';
  label: string;
}

interface SaveActionBuildResult {
  actions: AutoReplyAction[];
  errorMessage: string | null;
}

/**
 * Componente principal para gestão das regras de Auto Reply.
 *
 * Permite configurar regras de resposta automática baseadas em palavras-chave (patterns),
 * com ações de envio de mensagens multimídia ou transferência para departamentos específicos.
 *
 * Lógica TS preservada verbatim do legado; apenas camada visual migrada para UI Kit (af-*).
 */
@Component({
  selector: 'app-auto-reply',
  standalone: true,
  imports: [
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfTableActionsComponent,
    AfStatusBadgeComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    ReactiveFormsModule,
    AfTextInputComponent,
    AfTextareaInputComponent,
    AfSelectInputComponent,
    AfCheckboxInputComponent,
    AfFileInputComponent,
    AfSwitchInputComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfAlertComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './auto-reply.html',
})
export class AutoReply implements OnInit {
  private readonly autoReplyRules = inject(AutoReplyService);
  private readonly departmentService = inject(DepartmentService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);

  readonly form = this.fb.group({
    messageType: ['1', Validators.required],
    keyword: [''],
    actionType: ['message', Validators.required],
    department: [''],
    caption: [''],
    is_active: this.fb.nonNullable.control(true),
    isWelcome: this.fb.nonNullable.control(false),
    file: [null],
  });

  readonly messageTypeOptions: AfSelectOption[] = [
    { label: '1 - Texto', value: '1' },
    { label: '2 - Áudio', value: '2' },
    { label: '3 - Imagem', value: '3' },
    { label: '4 - Vídeo', value: '4' },
    { label: '5 - Documento', value: '5' },
  ];

  readonly actionTypeOptions: AfSelectOption[] = [
    { label: 'Enviar mensagem', value: 'message' },
    { label: 'Transferir para departamento', value: 'transfer' },
  ];

  readonly departmentOptions = computed<AfSelectOption[]>(() =>
    this.departments().map((d) => ({ label: d.name, value: d.id })),
  );

  /**
   * Opções de tipos de mensagens disponíveis para o auto reply.
   */
  readonly messageTypes: MessageTypeOption[] = [
    { id: '1', label: '1 - Texto' },
    { id: '2', label: '2 - Áudio' },
    { id: '3', label: '3 - Imagem' },
    { id: '4', label: '4 - Vídeo' },
    { id: '5', label: '5 - Documento' },
  ];

  /**
   * Opções de ações que o auto reply pode executar ao identificar um padrão.
   */
  readonly actionTypes: ActionTypeOption[] = [
    { id: 'message', label: 'Enviar mensagem' },
    { id: 'transfer', label: 'Transferir para departamento' },
  ];

  readonly rules = signal<AutoReplyRule[]>([]);
  readonly departments = signal<Department[]>([]);
  readonly isLoading = signal(false);
  readonly errorMessage = signal<string | null>(null);
  readonly isEditOpen = signal(false);
  readonly isDeleteOpen = signal(false);
  readonly isCreating = signal(false);
  readonly editingRule = signal<AutoReplyRule | null>(null);
  readonly editingRuleId = signal<string | null>(null);
  readonly deletingRule = signal<AutoReplyRule | null>(null);
  readonly selectedMessageType = signal(this.messageTypes[0].id);
  readonly selectedActionType = signal<ActionTypeOption['id']>('message');
  readonly keyword = signal('');
  readonly department = signal('');
  readonly caption = signal('');
  readonly isWelcome = signal(false);
  readonly isSaving = signal(false);
  readonly isDeleting = signal(false);

  readonly keywordStatus = signal<'idle' | 'checking' | 'available' | 'unavailable' | 'error'>(
    'idle',
  );
  readonly keywordMessage = signal('');

  private keywordCheckTimeoutId: number | undefined;

  readonly hasRules = computed(() => this.rules().length > 0);

  ngOnInit(): void {
    this.loadRules();
    this.loadDepartments();

    this.form.controls.keyword.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        this.onKeywordInput(value || '');
      });
  }

  loadRules(): void {
    this.isLoading.set(true);
    this.errorMessage.set(null);
    this.autoReplyRules
      .list({ per_page: 100 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const payload = response.data as { data?: AutoReplyRule[] } | AutoReplyRule[];
          const items = Array.isArray(payload) ? payload : (payload.data ?? []);
          this.rules.set(items);
          this.isLoading.set(false);
        },
        error: () => {
          this.errorMessage.set('Não foi possível carregar as regras.');
          this.isLoading.set(false);
        },
      });
  }

  loadDepartments(): void {
    this.departmentService
      .list({ per_page: 100, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.departments.set(response.data ?? []),
        error: () => this.departments.set([]),
      });
  }

  getOption(rule: AutoReplyRule): string {
    return rule.patterns?.[0] || rule.name;
  }

  getRuleName(rule: AutoReplyRule): string {
    return rule.name?.trim() || '';
  }

  getDepartmentName(rule: AutoReplyRule): string {
    return rule.department?.name || 'Sem departamento';
  }

  getInstanceName(rule: AutoReplyRule): string {
    return rule.instance?.name || rule.instance?.phone || 'Global';
  }

  getMatchTypeLabel(rule: AutoReplyRule): string {
    switch (rule.match_type) {
      case 'exact':
        return 'Exato';
      case 'contains':
        return 'Contém';
      case 'starts_with':
        return 'Começa com';
      case 'ends_with':
        return 'Termina com';
      case 'regex':
        return 'Regex';
      default:
        return 'Match';
    }
  }

  getBodyLines(rule: AutoReplyRule): string[] {
    const action = rule.actions?.[0];
    const message = typeof action?.message === 'string' ? action.message : '';
    if (!message) {
      return rule.description ? [rule.description] : ['Sem mensagem definida.'];
    }
    return message.split('\n').filter((line) => line.trim().length > 0);
  }

  getActionLabel(rule: AutoReplyRule): string {
    const action = rule.actions?.[0];
    if (!action) return 'Ação não definida';
    return this.getActionTypeLabel(action.type);
  }

  getActionTypeLabel(type?: AutoReplyActionType): string {
    switch (type) {
      case 'send_message':
        return 'Enviar mensagem';
      case 'transfer_department':
        return 'Transferir para departamento';
      case 'transfer_agent':
        return 'Transferir para agente';
      case 'close_ticket':
        return 'Encerrar atendimento';
      case 'add_tag':
        return 'Adicionar etiqueta';
      case 'create_task':
        return 'Criar tarefa';
      default:
        return 'Ação';
    }
  }

  getActionTypeBadge(rule: AutoReplyRule): string {
    const action = rule.actions?.[0];
    if (!action) return '-';
    if (action.type === 'send_message') {
      const messageType =
        typeof action['message_type'] === 'string' ? action['message_type'] : null;
      return messageType ? this.getMessageTypeLabel(messageType) : '1 - Texto';
    }
    return 'Automação';
  }

  getMessageTypeLabel(id: string): string {
    return this.messageTypes.find((type) => type.id === id)?.label ?? '1 - Texto';
  }

  normalizeKeyword(value: string): string {
    return value
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  openEdit(rule: AutoReplyRule): void {
    this.isCreating.set(false);
    this.editingRule.set(rule);
    this.editingRuleId.set(rule.id);
    this.form.reset({
      messageType:
        rule.actions?.[0]?.message_type && typeof rule.actions[0].message_type === 'string'
          ? rule.actions[0].message_type
          : '1',
      keyword: this.getOption(rule),
      actionType: rule.actions?.[0]?.type === 'transfer_department' ? 'transfer' : 'message',
      department: rule.department_id ? String(rule.department_id) : '',
      caption: this.getBodyLines(rule).join('\n'),
      is_active: Boolean(rule.is_active),
      isWelcome: !!rule.is_welcome,
    });
    this.keywordStatus.set('idle');
    this.keywordMessage.set('');
    this.isEditOpen.set(true);
  }

  closeEdit(): void {
    this.isEditOpen.set(false);
    this.isCreating.set(false);
    this.editingRule.set(null);
    this.editingRuleId.set(null);
    this.keywordStatus.set('idle');
    this.keywordMessage.set('');
    if (this.keywordCheckTimeoutId) {
      window.clearTimeout(this.keywordCheckTimeoutId);
      this.keywordCheckTimeoutId = undefined;
    }
  }

  openCreate(): void {
    this.isCreating.set(true);
    this.editingRule.set(null);
    this.editingRuleId.set(null);
    this.form.reset({
      messageType: '1',
      keyword: '',
      actionType: 'message',
      department: '',
      caption: '',
      is_active: true,
      isWelcome: false,
    });
    this.keywordStatus.set('idle');
    this.keywordMessage.set('');
    this.isEditOpen.set(true);
  }

  onKeywordInput(value: string): void {
    const trimmed = value.trim();

    this.keywordStatus.set('idle');
    this.keywordMessage.set('');

    if (this.keywordCheckTimeoutId) {
      window.clearTimeout(this.keywordCheckTimeoutId);
    }

    if (!trimmed) {
      return;
    }

    this.keywordCheckTimeoutId = window.setTimeout(() => {
      this.keywordStatus.set('checking');
      const rule = this.editingRule();
      const departmentId = this.form.get('department')?.value;
      this.autoReplyRules
        .validateKeyword({
          keyword: trimmed,
          match_type: (rule?.match_type || 'contains') as AutoReplyMatchType,
          instance_id: rule?.instance_id ?? null,
          department_id: departmentId || null,
          rule_id: this.editingRuleId(),
        })
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: (response) => {
            if (response.data.available) {
              this.keywordStatus.set('available');
              this.keywordMessage.set('Palavra disponível.');
            } else {
              this.keywordStatus.set('unavailable');
              this.keywordMessage.set('Palavra já está em uso.');
            }
          },
          error: () => {
            this.keywordStatus.set('error');
            this.keywordMessage.set('Não foi possível validar a palavra.');
          },
        });
    }, 400);
  }

  saveEdit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const value = this.form.getRawValue();
    const rule = this.editingRule();
    const ruleId = this.editingRuleId();
    const isEdit = Boolean(ruleId) && !this.isCreating();
    const keyword = (value.keyword || '').trim();

    if (!keyword) {
      toast.error('Informe a palavra-chave.');
      return;
    }
    if (/\s/.test(keyword)) {
      toast.error('Use apenas uma palavra, sem espaços.');
      return;
    }
    if (this.keywordStatus() === 'checking') {
      toast.error('Aguarde a validação da palavra-chave.');
      return;
    }
    if (this.keywordStatus() === 'unavailable') {
      toast.error('Palavra-chave já está em uso.');
      return;
    }
    const hasDuplicate = this.hasDuplicateKeyword(keyword, rule?.id ?? null);
    if (hasDuplicate) {
      toast.error('Essa palavra-chave já está cadastrada.');
      return;
    }

    const actionResult = this.buildActions(
      value.actionType,
      value.department,
      value.caption,
      value.messageType,
    );
    if (actionResult.errorMessage !== null) {
      toast.error(actionResult.errorMessage);
      return;
    }

    const payload = this.buildRulePayload(
      rule,
      keyword,
      value.is_active,
      value.isWelcome,
      value.department,
      actionResult.actions,
    );

    if (!isEdit && !this.isCreating()) {
      toast.error('Não foi possível identificar a regra para edição.');
      return;
    }

    const request$ = isEdit
      ? this.autoReplyRules.update(ruleId as string, payload)
      : this.autoReplyRules.create(payload);

    this.isSaving.set(true);
    request$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (_response) => {
        this.isSaving.set(false);
        this.isEditOpen.set(false);
        this.isCreating.set(false);
        this.editingRuleId.set(null);
        this.loadRules();
        toast.success(isEdit ? 'Regra atualizada.' : 'Regra criada.');
      },
      error: () => {
        this.isSaving.set(false);
        toast.error('Não foi possível salvar a regra.');
      },
    });
  }

  openDelete(rule: AutoReplyRule): void {
    this.deletingRule.set(rule);
    this.isDeleteOpen.set(true);
  }

  closeDelete(): void {
    this.isDeleteOpen.set(false);
    this.deletingRule.set(null);
  }

  confirmDelete(): void {
    const rule = this.deletingRule();
    if (!rule) return;

    this.isDeleting.set(true);
    this.autoReplyRules
      .delete(rule.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.rules.update((items) => items.filter((item) => item.id !== rule.id));
          this.isDeleting.set(false);
          this.isDeleteOpen.set(false);
          this.deletingRule.set(null);
          toast.success('Regra removida.');
        },
        error: () => {
          this.isDeleting.set(false);
          toast.error('Não foi possível remover a regra.');
        },
      });
  }

  private hasDuplicateKeyword(keyword: string, currentRuleId: string | null): boolean {
    const normalizedKeyword = this.normalizeKeyword(keyword);

    return this.rules().some((item) => {
      if (currentRuleId !== null && item.id === currentRuleId) {
        return false;
      }

      return (item.patterns ?? []).some(
        (pattern) => this.normalizeKeyword(pattern) === normalizedKeyword,
      );
    });
  }

  private buildActions(
    actionType: string | null,
    departmentId: string | null,
    captionValue: string | null,
    messageType: string | null,
  ): SaveActionBuildResult {
    const caption = (captionValue || '').trim();

    if (actionType === 'transfer') {
      if (!departmentId) {
        return {
          actions: [],
          errorMessage: 'Selecione um departamento.',
        };
      }

      return {
        actions: [{ type: 'transfer_department', department_id: departmentId }],
        errorMessage: null,
      };
    }

    if (!caption) {
      return {
        actions: [],
        errorMessage: 'Informe a legenda da mensagem.',
      };
    }

    return {
      actions: [
        {
          type: 'send_message',
          message: caption,
          message_type: messageType || '1',
        },
      ],
      errorMessage: null,
    };
  }

  private buildRulePayload(
    rule: AutoReplyRule | null,
    keyword: string,
    isActive: boolean | null | undefined,
    isWelcome: boolean,
    departmentId: string | null,
    actions: AutoReplyAction[],
  ): {
    name: string;
    description?: string;
    instance_id?: string;
    department_id?: string;
    is_welcome: boolean;
    match_type: AutoReplyMatchType;
    patterns: string[];
    actions: AutoReplyAction[];
    cooldown_seconds: number;
    priority: number;
    is_active: boolean;
    respect_business_hours: boolean;
  } {
    return {
      name: rule?.name || `Regra ${keyword}`,
      description: rule?.description || undefined,
      instance_id: rule?.instance_id ?? undefined,
      department_id: departmentId || undefined,
      is_welcome: Boolean(isWelcome),
      match_type: (rule?.match_type || 'contains') as AutoReplyMatchType,
      patterns: [keyword],
      actions,
      cooldown_seconds: rule?.cooldown_seconds ?? 60,
      priority: rule?.priority ?? 0,
      is_active: isActive ?? false,
      respect_business_hours: rule?.respect_business_hours ?? true,
    };
  }
}
