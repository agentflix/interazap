import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  type FormGroup,
  ReactiveFormsModule,
  FormControl,
  Validators,
  NonNullableFormBuilder,
} from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { debounceTime, distinctUntilChanged } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCardComponent,
  AfChatBubbleComponent,
  AfEmptyStateComponent,
  AfLoadingButtonComponent,
  AfPageTitleComponent,
  AfSelectInputComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
  type AfSelectOption,
} from '@shared/components';
import {
  type ChatTransmissionListPreview,
  ChatTransmissionListService,
} from '@core/services/chat-transmission-list.service';
import { type Integration, IntegrationService } from '@core/services/integration.service';
import { ToastService } from '@core/services/toast.service';

interface TransmissionListFormControls {
  name: FormControl<string>;
  instance_id: FormControl<string>;
  message: FormControl<string>;
  status: FormControl<string>;
  scheduled_at: FormControl<string | null>;
  filter_tags: FormControl<string[]>;
  filter_status: FormControl<string>;
  filter_company_id: FormControl<string | null>;
}

/**
 * Formulário de criação e edição de lista de transmissão em massa.
 *
 * @remarks
 * Suporta preview de mensagem em tempo real, cálculo de público estimado via API
 * e agendamento de envio. Variáveis de personalização são inseridas no corpo da mensagem.
 */
@Component({
  selector: 'app-chat-transmission-list-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfPageTitleComponent,
    AfCardComponent,
    AfAlertComponent,
    AfEmptyStateComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfTextInputComponent,
    AfSelectInputComponent,
    AfTextareaInputComponent,
    AfChatBubbleComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './chat-transmission-list-form.html',
})
export class ChatTransmissionListFormComponent implements OnInit {
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly transmissionListService = inject(ChatTransmissionListService);
  private readonly integrationService = inject(IntegrationService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);
  private readonly toast = inject(ToastService);

  readonly form: FormGroup<TransmissionListFormControls> = this.fb.group({
    name: this.fb.control('', [Validators.required, Validators.minLength(3)]),
    instance_id: this.fb.control('', [Validators.required]),
    message: this.fb.control('', [Validators.required, Validators.minLength(10)]),
    status: this.fb.control('draft'),
    scheduled_at: this.fb.control<string | null>(null),
    filter_tags: this.fb.control<string[]>([]),
    filter_status: this.fb.control('active'),
    filter_company_id: this.fb.control<string | null>(null),
  });

  readonly tagsControl = new FormControl<string>('', { nonNullable: true });

  readonly isEditing = signal(false);
  readonly isLoading = signal(false);
  readonly isSubmitting = signal(false);
  readonly hasError = signal(false);
  readonly audienceCount = signal<number | null>(null);
  readonly instances = signal<Integration[]>([]);
  readonly previewData = signal<ChatTransmissionListPreview | null>(null);

  private transmissionListId: string | null = null;

  readonly isEmpty = computed(
    () => !this.hasError() && !this.isLoading() && this.instances().length === 0,
  );

  readonly instanceOptions = computed<AfSelectOption[]>(() =>
    this.instances().map((instance) => ({
      label: `${instance.name} (${instance.settings?.cellphone || '-'})`,
      value: instance.id,
    })),
  );

  readonly statusOptions: AfSelectOption[] = [
    { label: 'Somente Ativos', value: 'active' },
    { label: 'Somente Inativos', value: 'inactive' },
    { label: 'Todos', value: 'all' },
  ];

  readonly previewMessage = computed(() => this.previewData()?.preview || '');

  readonly currentTime = new Date().toLocaleTimeString('pt-BR', {
    hour: '2-digit',
    minute: '2-digit',
  });

  ngOnInit(): void {
    this.setupEffects();
    this.loadInitialData();
  }

  /**
   * Insere uma variável de personalização no final da mensagem.
   *
   * @param variable - Token da variável (ex: `{{nome}}`).
   */
  insertVariable(variable: string): void {
    const currentMessage = this.form.controls.message.value || '';
    this.form.controls.message.setValue(`${currentMessage} ${variable} `.trim());
  }

  /** Navega de volta para a listagem de transmissões. */
  goBack(): void {
    void this.router.navigate(['/chat/transmission-list']);
  }

  /** Tenta recarregar os dados iniciais após erro. */
  retry(): void {
    this.loadInitialData();
  }

  /** Valida e persiste a lista de transmissão (criação ou edição). */
  save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toast.error('Preencha todos os campos obrigatórios.');
      return;
    }

    this.isSubmitting.set(true);
    const value = this.form.getRawValue();

    const payload = {
      name: value.name ?? '',
      instance_id: value.instance_id ?? '',
      message: value.message ?? '',
      scheduled_at: value.scheduled_at || undefined,
      filter_criteria: {
        tags: value.filter_tags,
        status: value.filter_status,
        company_id: value.filter_company_id || undefined,
      },
    };

    if (this.isEditing() && this.transmissionListId) {
      this.transmissionListService
        .update(this.transmissionListId, payload)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: () => {
            this.toast.success('Lista de transmissão atualizada!');
            void this.router.navigate(['/chat/transmission-list']);
          },
          error: () => {
            this.toast.error('Erro ao salvar lista de transmissão.');
            this.isSubmitting.set(false);
          },
        });
      return;
    }

    this.transmissionListService
      .create(payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toast.success('Lista de transmissão criada!');
          void this.router.navigate(['/chat/transmission-list']);
        },
        error: () => {
          this.toast.error('Erro ao criar lista de transmissão.');
          this.isSubmitting.set(false);
        },
      });
  }

  private setupEffects(): void {
    this.form.controls.message.valueChanges
      .pipe(debounceTime(500), distinctUntilChanged(), takeUntilDestroyed(this.destroyRef))
      .subscribe((message) => {
        if (message.length > 5) {
          this.loadPreview(message);
        } else {
          this.previewData.set(null);
        }
      });

    this.form.valueChanges
      .pipe(
        debounceTime(800),
        distinctUntilChanged((previous, current) => {
          return (
            JSON.stringify(previous.filter_tags) === JSON.stringify(current.filter_tags) &&
            previous.filter_status === current.filter_status &&
            previous.filter_company_id === current.filter_company_id
          );
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe(() => this.updateAudience());

    this.tagsControl.valueChanges
      .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.onTagsChange(value));
  }

  private loadInitialData(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.loadInstances();

    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const id = params.get('id');

      if (id && id !== 'new') {
        this.transmissionListId = id;
        this.isEditing.set(true);
        this.loadTransmissionList(id);
        return;
      }

      this.updateAudience();
      this.isLoading.set(false);
    });
  }

  private onTagsChange(value: string): void {
    const tags = value
      .split(',')
      .map((tag) => tag.trim())
      .filter((tag) => tag.length > 0);

    this.form.controls.filter_tags.setValue(tags);
  }

  private loadInstances(): void {
    this.integrationService
      .list({ is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.instances.set(response.data);

          if (response.data.length === 1 && !this.form.controls.instance_id.value) {
            this.form.controls.instance_id.setValue(response.data[0].id);
          }

          if (response.data.length === 0 && !this.isEditing()) {
            this.isLoading.set(false);
          }
        },
        error: () => {
          this.hasError.set(true);
          this.isLoading.set(false);
        },
      });
  }

  private loadTransmissionList(id: string): void {
    this.transmissionListService
      .show(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const transmissionList = response.data;

          this.form.patchValue({
            name: transmissionList.name,
            instance_id: transmissionList.instance_id || '',
            message: transmissionList.message || '',
            scheduled_at: transmissionList.scheduled_at || null,
            filter_tags: transmissionList.filter_criteria?.tags || [],
            filter_status: transmissionList.filter_criteria?.status || 'active',
            filter_company_id: transmissionList.filter_criteria?.company_id || null,
          });

          if (transmissionList.filter_criteria?.tags?.length) {
            this.tagsControl.setValue(transmissionList.filter_criteria.tags.join(', '), {
              emitEvent: false,
            });
          }

          if (transmissionList.message) {
            this.loadPreview(transmissionList.message);
          }

          this.updateAudience();
          this.isLoading.set(false);
        },
        error: () => {
          this.hasError.set(true);
          this.isLoading.set(false);
        },
      });
  }

  private loadPreview(message: string): void {
    this.transmissionListService
      .preview(message)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (data) => this.previewData.set(data),
        error: () => this.previewData.set(null),
      });
  }

  private updateAudience(): void {
    const value = this.form.getRawValue();

    this.transmissionListService
      .audience({
        tags: value.filter_tags,
        status: value.filter_status,
        company_id: value.filter_company_id || undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.audienceCount.set(response.count),
        error: () => this.audienceCount.set(null),
      });
  }
}

export default ChatTransmissionListFormComponent;
