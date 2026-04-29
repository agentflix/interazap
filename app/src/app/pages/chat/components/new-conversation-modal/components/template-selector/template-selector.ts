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
import { LucideAngularModule } from 'lucide-angular';

/**
 * Represents a Meta WhatsApp template.
 */
export interface MetaTemplate {
  name: string;
  category: string;
  language: string;
  status: 'APPROVED' | 'PENDING' | 'REJECTED' | string;
  components?: MetaTemplateComponent[];
}

/**
 * Represents a component within a Meta WhatsApp template.
 */
export interface MetaTemplateComponent {
  type: string;
  text?: string;
  parameters?: MetaTemplateParameter[];
}

/**
 * Represents a parameter in a template component.
 */
export interface MetaTemplateParameter {
  type: string;
  text?: string;
}

/**
 * Response from the templates endpoint.
 */
interface TemplatesResponse {
  data: MetaTemplate[];
}

/**
 * Event emitted when a template is selected.
 */
export interface TemplateSelectedEvent {
  templateName: string;
  parameters: Record<string, string>;
}

/**
 * Component for selecting a Meta WhatsApp Business template.
 *
 * Loads approved templates for a given channel and allows the user to
 * select one and fill in its required parameters.
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
  imports: [ReactiveFormsModule, SelectInputComponent, TextInputComponent, LucideAngularModule],
  templateUrl: './template-selector.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TemplateSelectorComponent {
  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);

  /** The channel ID to load templates from. */
  readonly channelId = input.required<string>();

  /** Visual presentation mode. Affects layout/density only. */
  readonly mode = input<'popover' | 'sheet' | 'modal'>('popover');

  /** Event emitted when a template is selected with its parameters. */
  readonly templateSelected = output<TemplateSelectedEvent>();

  /** Loading state. */
  readonly isLoading = signal(false);

  /** Error message if template loading fails. */
  readonly loadError = signal<string | null>(null);

  /** List of approved templates. */
  readonly templates = signal<MetaTemplate[]>([]);

  /** Currently selected template. */
  readonly selectedTemplate = signal<MetaTemplate | null>(null);

  /** Form control for template selection. */
  readonly templateControl = new FormControl<string>('', {
    nonNullable: true,
    validators: [Validators.required],
  });

  /** Parameter form controls (dynamic based on selected template). */
  readonly parameterControls = signal<Record<string, FormControl<string>>>({});

  /** Parameter entries as array for iteration. */
  readonly parameterEntries = computed(() => Object.entries(this.parameterControls()));

  /** Whether there was an error selecting/validating. */
  readonly validationError = signal<string | null>(null);

  /** Template options for the select dropdown. */
  readonly templateOptions = computed<SelectOption[]>(() =>
    this.templates().map((t) => ({
      value: t.name,
      label: `${t.name} (${t.category} - ${t.language})`,
    })),
  );

  /** Number of required parameters for the selected template. */
  readonly requiredParamCount = computed(() => {
    const template = this.selectedTemplate();
    if (!template?.components) {
      return 0;
    }
    return template.components.filter((c) => c.type === 'BODY' && c.parameters?.length).length ?? 0;
  });

  /** Whether the selected template requires parameters. */
  readonly hasRequiredParameters = computed(() => this.requiredParamCount() > 0);

  /** Whether the submit button should be enabled. */
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

    // Load templates when channelId input changes
    effect(() => {
      const channelId = this.channelId();
      if (channelId) {
        this.loadTemplates(channelId);
      }
    });
  }

  /**
   * Loads approved templates from the API.
   * @param channelId - The channel ID to load templates for.
   */
  loadTemplates(channelId: string): void {
    this.isLoading.set(true);
    this.loadError.set(null);

    this.http
      .get<TemplatesResponse>(`${environment.gateway.url}/channels/${channelId}/templates`)
      .pipe(
        takeUntilDestroyed(this.destroyRef),
        catchError(() => {
          this.loadError.set('Não foi possível carregar os templates.');
          return of({ data: [] } as TemplatesResponse);
        }),
      )
      .subscribe((response) => {
        const approved = (response.data ?? []).filter((t) => t.status === 'APPROVED');
        this.templates.set(approved);
        this.isLoading.set(false);

        if (approved.length === 0) {
          this.loadError.set('Nenhum template aprovado encontrado para este canal.');
        }
      });
  }

  /**
   * Handles template selection change.
   * @param templateName - The name of the selected template.
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
   * Emits the templateSelected event with the selected template and parameters.
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
