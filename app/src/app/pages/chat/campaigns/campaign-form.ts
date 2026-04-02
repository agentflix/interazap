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
  type ChatCampaignPreview,
  ChatCampaignService,
} from '@core/services/chat-campaign.service';
import { type Integration, IntegrationService } from '@core/services/integration.service';
import { ToastService } from '@core/services/toast.service';

interface CampaignFormControls {
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
 * Campaign create/edit form page.
 *
 * @remarks
 * Keeps legacy functional flow while migrating the visual layer to UI Kit.
 * Suporta preview de mensagem, calculo de publica estimado e agendamento.
 *
 * @example
 * ```html
 * <app-campaign-form />
 * ```
 */
@Component({
  selector: 'app-campaign-form',
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
  templateUrl: './campaign-form.html',
})
export class CampaignFormComponent implements OnInit {
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly campaignService = inject(ChatCampaignService);
  private readonly integrationService = inject(IntegrationService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);
  private readonly toast = inject(ToastService);

  readonly form: FormGroup<CampaignFormControls> = this.fb.group({
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
  readonly previewData = signal<ChatCampaignPreview | null>(null);

  private campaignId: string | null = null;

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

  insertVariable(variable: string): void {
    const currentMessage = this.form.controls.message.value || '';
    this.form.controls.message.setValue(`${currentMessage} ${variable} `.trim());
  }

  goBack(): void {
    void this.router.navigate(['/chat/campaigns']);
  }

  retry(): void {
    this.loadInitialData();
  }

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

    if (this.isEditing() && this.campaignId) {
      this.campaignService
        .update(this.campaignId, payload)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: () => {
            this.toast.success('Campanha atualizada!');
            void this.router.navigate(['/chat/campaigns']);
          },
          error: () => {
            this.toast.error('Erro ao salvar campanha.');
            this.isSubmitting.set(false);
          },
        });
      return;
    }

    this.campaignService
      .create(payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toast.success('Campanha criada!');
          void this.router.navigate(['/chat/campaigns']);
        },
        error: () => {
          this.toast.error('Erro ao criar campanha.');
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
        this.campaignId = id;
        this.isEditing.set(true);
        this.loadCampaign(id);
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

  private loadCampaign(id: string): void {
    this.campaignService
      .show(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const campaign = response.data;

          this.form.patchValue({
            name: campaign.name,
            instance_id: campaign.instance_id || '',
            message: campaign.message || '',
            scheduled_at: campaign.scheduled_at || null,
            filter_tags: campaign.filter_criteria?.tags || [],
            filter_status: campaign.filter_criteria?.status || 'active',
            filter_company_id: campaign.filter_criteria?.company_id || null,
          });

          if (campaign.filter_criteria?.tags?.length) {
            this.tagsControl.setValue(campaign.filter_criteria.tags.join(', '), {
              emitEvent: false,
            });
          }

          if (campaign.message) {
            this.loadPreview(campaign.message);
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
    this.campaignService
      .preview(message)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (data) => this.previewData.set(data),
        error: () => this.previewData.set(null),
      });
  }

  private updateAudience(): void {
    const value = this.form.getRawValue();

    this.campaignService
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

export default CampaignFormComponent;
