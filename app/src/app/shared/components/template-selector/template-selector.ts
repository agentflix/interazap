import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { HttpClient } from '@angular/common/http';
import { catchError, of } from 'rxjs';
import { environment } from '@env/environment';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import {
  SelectInputComponent,
  TextInputComponent,
  type SelectOption,
} from '@shared/components/inputs';
import { ButtonComponent } from '@shared/components/buttons';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Representa um template do Meta WhatsApp.
 */
export interface MetaTemplate {
  name: string;
  category: string;
  language: string;
  status: 'APPROVED' | 'PENDING' | 'REJECTED' | string;
  components?: MetaTemplateComponent[];
}

/**
 * Representa um componente dentro de um template do Meta WhatsApp.
 */
export interface MetaTemplateComponent {
  type: string;
  text?: string;
  parameters?: MetaTemplateParameter[];
}

/**
 * Representa um parâmetro em um componente de template.
 */
export interface MetaTemplateParameter {
  type: string;
  text?: string;
}

/**
 * Resposta do endpoint de templates.
 */
interface TemplatesResponse {
  data: MetaTemplate[];
}

/**
 * Evento emitido quando um template é selecionado.
 */
export interface TemplateSelectedEvent {
  templateName: string;
  parameters: Record<string, string>;
}

/**
 * Componente para seleção de template do Meta WhatsApp Business.
 *
 * Carrega templates aprovados para um canal e permite ao usuário
 * selecionar um e preencher seus parâmetros obrigatórios.
 *
 * @example
 * ```html
 * <app-template-selector
 *   [channelId]="channelId"
 *   (templateSelected)="onTemplateSelected($event)"
 * />
 * ```
 */
@Component({
  selector: 'app-template-selector',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    SelectInputComponent,
    TextInputComponent,
    ButtonComponent,
    LucideAngularModule,
  ],
  templateUrl: './template-selector.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TemplateSelectorComponent {
  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);

  /** ID do canal para carregar os templates. */
  readonly channelId = input.required<string>();

  /** Modo de apresentação visual. Afeta apenas layout/densidade. */
  readonly mode = input<'popover' | 'sheet' | 'modal'>('popover');

  /** Evento emitido quando um template é selecionado com seus parâmetros. */
  readonly templateSelected = output<TemplateSelectedEvent>();

  /** Estado de carregamento. */
  readonly isLoading = signal(false);

  /** Mensagem de erro se o carregamento de templates falhar. */
  readonly loadError = signal<string | null>(null);

  /** Lista de templates aprovados. */
  readonly templates = signal<MetaTemplate[]>([]);

  /** Template atualmente selecionado. */
  readonly selectedTemplate = signal<MetaTemplate | null>(null);

  /** FormControl para seleção de template. */
  readonly templateControl = new FormControl<string>('', {
    nonNullable: true,
    validators: [Validators.required],
  });

  /** FormControls de parâmetros (dinâmico baseado no template selecionado). */
  readonly parameterControls = signal<Record<string, FormControl<string>>>({});

  /** Entradas de parâmetros como array para iteração. */
  readonly parameterEntries = computed(() => Object.entries(this.parameterControls()));

  /** Indica se houve erro ao selecionar/validar. */
  readonly validationError = signal<string | null>(null);

  /** Opções de template para o dropdown de seleção. */
  readonly templateOptions = computed<SelectOption[]>(() =>
    this.templates().map((t) => ({
      value: t.name,
      label: `${t.name} (${t.category} - ${t.language})`,
    })),
  );

  /** Número de parâmetros obrigatórios para o template selecionado. */
  readonly requiredParamCount = computed(() => {
    const template = this.selectedTemplate();
    if (!template?.components) {
      return 0;
    }
    return template.components.filter((c) => c.type === 'BODY' && c.parameters?.length).length ?? 0;
  });

  /** Indica se o template selecionado requer parâmetros. */
  readonly hasRequiredParameters = computed(() => this.requiredParamCount() > 0);

  /** Indica se o botão de submit deve estar habilitado. */
  readonly canSubmit = computed(() => {
    if (!this.selectedTemplate()) {
      return false;
    }
    if (!this.hasRequiredParameters()) {
      return true;
    }
    const controls = this.parameterControls();
    return Object.values(controls).every((c) => c.valid);
  });

  constructor() {
    this.templateControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((templateName) => this.onTemplateChange(templateName));

    // Carrega templates quando o input channelId muda
    effect(() => {
      const channelId = this.channelId();
      if (channelId) {
        this.loadTemplates(channelId);
      }
    });
  }

  /**
   * Carrega templates aprovados da API.
   * @param channelId - ID do canal para carregar os templates.
   */
  loadTemplates(channelId: string): void {
    this.isLoading.set(true);
    this.loadError.set(null);

    this.http
      .get<TemplatesResponse>(
        `${environment.apiUrl}/chat/message-templates?chat_instance_id=${channelId}&status=APPROVED`,
      )
      .pipe(
        takeUntilDestroyed(this.destroyRef),
        catchError(() => {
          this.loadError.set('Não foi possível carregar os templates.');
          return of({ data: [] } as TemplatesResponse);
        }),
      )
      .subscribe((response) => {
        const approved = (response.data ?? []).filter((template) => template.status === 'APPROVED');
        this.templates.set(approved);
        this.isLoading.set(false);

        if (approved.length === 0) {
          this.loadError.set('Nenhum template aprovado encontrado para este canal.');
        }
      });
  }

  /**
   * Processa a mudança de template selecionado.
   * @param templateName - Nome do template selecionado.
   */
  private onTemplateChange(templateName: string): void {
    this.validationError.set(null);

    if (!templateName) {
      this.selectedTemplate.set(null);
      this.parameterControls.set({});
      return;
    }

    const template = this.templates().find((t) => t.name === templateName);
    this.selectedTemplate.set(template ?? null);

    if (template?.components) {
      const bodyComponent = template.components.find((c) => c.type === 'BODY');
      if (bodyComponent?.parameters && bodyComponent.parameters.length > 0) {
        const controls: Record<string, FormControl<string>> = {};
        bodyComponent.parameters.forEach((_, index) => {
          controls[`param_${index}`] = new FormControl<string>('', {
            nonNullable: true,
            validators: [Validators.required],
          });
        });
        this.parameterControls.set(controls);
      } else {
        this.parameterControls.set({});
      }
    } else {
      this.parameterControls.set({});
    }
  }

  /**
   * Emite o evento templateSelected com o template e parâmetros selecionados.
   */
  submit(): void {
    const template = this.selectedTemplate();
    if (!template) {
      this.validationError.set('Selecione um template.');
      return;
    }

    if (this.hasRequiredParameters()) {
      const controls = this.parameterControls();
      const hasEmpty = Object.values(controls).some((c) => !c.value.trim());
      if (hasEmpty) {
        this.validationError.set('Preencha todos os parâmetros do template.');
        Object.values(controls).forEach((c) => c.markAsTouched());
        return;
      }
    }

    const parameters: Record<string, string> = {};
    Object.entries(this.parameterControls()).forEach(([key, control]) => {
      parameters[key] = control.value;
    });

    this.templateSelected.emit({
      templateName: template.name,
      parameters,
    });
  }
}
