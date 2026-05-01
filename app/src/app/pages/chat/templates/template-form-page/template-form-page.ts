import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
  type OnInit,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import { toast } from 'ngx-sonner';
import {
  AfAlertComponent,
  AfBadgeComponent,
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfSelectInputComponent,
  type AfSelectOption,
  AfSwitchInputComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
} from '@shared/components';
import { IntegrationService, type Integration } from '@core/services/integration.service';
import {
  type ChatMessageTemplate,
  type ChatMessageTemplateComponent,
  type ChatMessageTemplatePayload,
} from '@core/models/chat-message-template.model';
import { ChatMessageTemplateService } from '../../services/chat-message-template.service';

@Component({
  selector: 'app-chat-template-form-page',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfAlertComponent,
    AfBadgeComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfSelectInputComponent,
    AfSwitchInputComponent,
    AfTextInputComponent,
    AfTextareaInputComponent,
  ],
  templateUrl: './template-form-page.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TemplateFormPageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly templateService = inject(ChatMessageTemplateService);
  private readonly integrationService = inject(IntegrationService);
  private readonly destroyRef = inject(DestroyRef);

  // ── State ───────────────────────────────────────────────────────────
  readonly templateId = signal<string | null>(null);
  readonly isEditMode = computed(() => this.templateId() !== null);
  readonly isLoading = signal(false);
  readonly isSubmitting = signal(false);
  readonly currentTemplate = signal<ChatMessageTemplate | null>(null);
  readonly integrations = signal<Integration[]>([]);
  readonly serverError = signal<string | null>(null);

  /** Modo Meta + edição → bloqueia campos sensíveis. */
  readonly isMetaLocked = computed(() => {
    const tpl = this.currentTemplate();
    return tpl !== null && tpl.provider === 'meta';
  });

  // ── Form ────────────────────────────────────────────────────────────
  readonly form = new FormGroup({
    chat_instance_id: new FormControl<string | number | null>('', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    name: new FormControl<string | number | null>('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(/^[a-z0-9_]{1,512}$/)],
    }),
    language: new FormControl<string | number | null>('pt_BR', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    category: new FormControl<string | number | null>('UTILITY', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    shortcut: new FormControl<string | number | null>('', { nonNullable: true }),
    is_active: new FormControl<boolean | null>(true),
    header_text: new FormControl<string | null>(''),
    body_text: new FormControl<string | null>('', {
      validators: [Validators.required, Validators.maxLength(1024)],
    }),
    footer_text: new FormControl<string | null>(''),
  });

  readonly languageOptions: AfSelectOption[] = [
    { value: 'pt_BR', label: 'Português (Brasil)' },
    { value: 'en_US', label: 'Inglês (EUA)' },
    { value: 'es_ES', label: 'Espanhol (Espanha)' },
  ];

  readonly categoryOptions: AfSelectOption[] = [
    { value: 'MARKETING', label: 'Marketing' },
    { value: 'UTILITY', label: 'Utility' },
    { value: 'AUTHENTICATION', label: 'Authentication' },
  ];

  readonly chatInstanceOptions = computed<AfSelectOption[]>(() =>
    this.integrations().map((i) => ({ value: i.id, label: i.name })),
  );

  // ── Preview ─────────────────────────────────────────────────────────
  readonly previewBody = signal<string>('');
  readonly previewHeader = signal<string>('');
  readonly previewFooter = signal<string>('');

  ngOnInit(): void {
    this.loadIntegrations();

    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.templateId.set(id);
      this.loadTemplate(id);
    }

    this.form.controls.body_text.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((v) => this.previewBody.set(v ?? ''));
    this.form.controls.header_text.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((v) => this.previewHeader.set(v ?? ''));
    this.form.controls.footer_text.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((v) => this.previewFooter.set(v ?? ''));
  }

  loadIntegrations(): void {
    this.integrationService
      .list({ per_page: 100 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (resp) => this.integrations.set(resp.data),
        error: () => this.integrations.set([]),
      });
  }

  loadTemplate(id: string): void {
    this.isLoading.set(true);
    this.templateService
      .get(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (tpl) => {
          this.currentTemplate.set(tpl);
          this.applyTemplate(tpl);
          this.isLoading.set(false);
          if (tpl.provider === 'meta') {
            this.lockMetaFields();
          }
        },
        error: () => {
          this.isLoading.set(false);
          toast.error('Falha ao carregar template.');
          void this.router.navigate(['/chat/templates']);
        },
      });
  }

  private applyTemplate(tpl: ChatMessageTemplate): void {
    const header = (tpl.components ?? []).find((c) => c.type === 'HEADER');
    const body = (tpl.components ?? []).find((c) => c.type === 'BODY');
    const footer = (tpl.components ?? []).find((c) => c.type === 'FOOTER');

    this.form.patchValue({
      chat_instance_id: tpl.chat_instance_id ?? '',
      name: tpl.name,
      language: tpl.language,
      category: tpl.category,
      shortcut: tpl.shortcut ?? '',
      is_active: tpl.is_active,
      header_text: header?.text ?? '',
      body_text: body?.text ?? tpl.content ?? '',
      footer_text: footer?.text ?? '',
    });

    this.previewBody.set(body?.text ?? tpl.content ?? '');
    this.previewHeader.set(header?.text ?? '');
    this.previewFooter.set(footer?.text ?? '');
  }

  private lockMetaFields(): void {
    this.form.controls.chat_instance_id.disable();
    this.form.controls.name.disable();
    this.form.controls.language.disable();
    this.form.controls.category.disable();
    this.form.controls.header_text.disable();
    this.form.controls.body_text.disable();
    this.form.controls.footer_text.disable();
  }

  cancel(): void {
    if (this.form.dirty) {
      const ok = window.confirm('Descartar alterações não salvas?');
      if (!ok) return;
    }
    void this.router.navigate(['/chat/templates']);
  }

  submit(): void {
    if (this.isMetaLocked()) {
      this.submitMetaPartial();
      return;
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = this.buildPayload();
    this.isSubmitting.set(true);
    this.serverError.set(null);

    const id = this.templateId();
    const obs$ = id
      ? this.templateService.update(id, payload)
      : this.templateService.create(payload);

    obs$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.isSubmitting.set(false);
        toast.success(id ? 'Template atualizado.' : 'Template enviado para aprovação.');
        void this.router.navigate(['/chat/templates']);
      },
      error: (err: unknown) => {
        this.isSubmitting.set(false);
        const msg =
          (err as { error?: { message?: string } })?.error?.message ?? 'Falha ao salvar template.';
        this.serverError.set(msg);
        toast.error(msg);
      },
    });
  }

  private submitMetaPartial(): void {
    const id = this.templateId();
    if (!id) return;

    const payload: ChatMessageTemplatePayload = {
      shortcut: (this.form.controls.shortcut.value ?? '') as string,
      is_active: this.form.controls.is_active.value ?? true,
    };
    this.isSubmitting.set(true);
    this.templateService
      .update(id, payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSubmitting.set(false);
          toast.success('Template atualizado.');
          void this.router.navigate(['/chat/templates']);
        },
        error: (err: unknown) => {
          this.isSubmitting.set(false);
          const msg =
            (err as { error?: { message?: string } })?.error?.message ?? 'Falha ao atualizar.';
          this.serverError.set(msg);
          toast.error(msg);
        },
      });
  }

  private buildPayload(): ChatMessageTemplatePayload {
    const v = this.form.getRawValue();
    const components: ChatMessageTemplateComponent[] = [];

    if (v.header_text && v.header_text.trim()) {
      components.push({ type: 'HEADER', format: 'TEXT', text: v.header_text });
    }
    if (v.body_text) {
      components.push({ type: 'BODY', text: v.body_text });
    }
    if (v.footer_text && v.footer_text.trim()) {
      components.push({ type: 'FOOTER', text: v.footer_text });
    }

    return {
      chat_instance_id: v.chat_instance_id ? String(v.chat_instance_id) : null,
      name: v.name ? String(v.name) : '',
      language: v.language ? String(v.language) : 'pt_BR',
      category: v.category ? String(v.category) : 'UTILITY',
      shortcut: (v.shortcut ?? '') as string,
      is_active: v.is_active ?? true,
      provider: 'meta',
      components,
    };
  }
}
