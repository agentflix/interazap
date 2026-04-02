import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  EventEmitter,
  Input,
  Output,
  computed,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { type Contact } from 'src/app/core/models/contact.model';
import { ContactService } from 'src/app/core/services/contact.service';
import { type CalledContactSummary } from 'src/app/core/services/called.service';
import { type CRMCompany, CRMCompanyService } from 'src/app/core/services/crm-company.service';
import {
  type Country,
  PhoneInputComponent,
  COUNTRIES,
  DEFAULT_COUNTRY,
} from 'src/app/shared/components/phone-input/phone-input';
import { TextInputComponent } from 'src/app/shared/components/text-input/text-input';
import { DocumentInputComponent } from 'src/app/shared/components/document-input/document-input';
import { MaskedInputComponent } from 'src/app/shared/components/masked-input/masked-input';
import {
  type SelectOption,
  SelectInputComponent,
} from 'src/app/shared/components/select-input/select-input';
import { TextareaInputComponent } from 'src/app/shared/components/textarea-input/textarea-input';
import { toast } from 'ngx-sonner';
import { LoadingButtonComponent } from 'src/app/shared/components/loading-button/loading-button';
import { AfScrollAreaComponent } from 'src/app/shared/components/scroll-area/scroll-area';

/**
 * Componente para visualização e edição simplificada dos dados de um contato.
 *
 * Permite atualizar informações básicas (nome, documento, e-mail), gerenciar
 * o vínculo com empresas do CRM e configurar campos personalizados como cargo e notas.
 *
 * @class ChatContactView
 * @description Formulário de detalhes e edição de contato no contexto do chat.
 */
@Component({
  selector: 'app-chat-contact-view',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    PhoneInputComponent,
    TextInputComponent,
    DocumentInputComponent,
    MaskedInputComponent,
    SelectInputComponent,
    TextareaInputComponent,
    LoadingButtonComponent,
    AfScrollAreaComponent,
  ],
  templateUrl: './chat-contact-view.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    class: 'block h-full',
  },
})
export class ChatContactView {
  private readonly contactService = inject(ContactService);
  private readonly crmCompanyService = inject(CRMCompanyService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);

  /**
   * Dados completos do contato carregados do servidor.
   * @type {WritableSignal<Contact | null>}
   */
  readonly contactSignal = signal<Contact | null>(null);

  /**
   * Indica se a busca pelos detalhes completos do contato está em curso.
   * @type {WritableSignal<boolean>}
   */
  readonly isLoading = signal(false);

  /**
   * Identificador do contato selecionado.
   * @type {WritableSignal<string | number | null>}
   */
  readonly contactId = signal<string | number | null>(null);

  /**
   * Verifica se existe um contato selecionado para visualização.
   * @type {Signal<boolean>}
   */
  readonly hasSelection = computed(() => this.contactId() !== null);

  /**
   * Lista de empresas do CRM disponíveis para associação.
   * @type {WritableSignal<CRMCompany[]>}
   */
  readonly crmCompanies = signal<CRMCompany[]>([]);

  /**
   * Opções formatadas para o SelectInputComponent de empresas.
   * @type {Signal<SelectOption[]>}
   */
  readonly crmCompaniesOptions = computed<SelectOption[]>(() =>
    this.crmCompanies().map((c) => ({ value: c.id.toString(), label: c.name })),
  );

  /**
   * Lista de países suportados para formatação de telefone.
   */
  readonly countries = COUNTRIES;

  /**
   * País selecionado para o input de WhatsApp.
   * @type {WritableSignal<Country>}
   */
  readonly selectedCountry = signal<Country>(DEFAULT_COUNTRY);

  readonly form = this.fb.group({
    name: new FormControl<string>('', { nonNullable: true, validators: [Validators.required] }),
    document: new FormControl<string>('', { nonNullable: true }),
    email: new FormControl<string>('', { nonNullable: true, validators: [Validators.email] }),
    phone: new FormControl<string>('', { nonNullable: true }),
    whatsapp: new FormControl<string>('', { nonNullable: true, validators: [Validators.required] }),
    crm_company_id: new FormControl<string>('', { nonNullable: true }),
    role: new FormControl<string>('', { nonNullable: true }),
    notes: new FormControl<string>('', { nonNullable: true }),
  });

  /**
   * Indica se o processo de salvamento das alterações do contato está ocorrendo.
   * @type {WritableSignal<boolean>}
   */
  readonly isSaving = signal(false);

  /**
   * Flag que indica se houve uma tentativa de submissão do formulário (para validação visual).
   * @type {WritableSignal<boolean>}
   */
  readonly attemptedSubmit = signal(false);

  /**
   * Verifica se o formulário está válido de acordo com as regras de validação.
   * @type {Signal<boolean>}
   */
  readonly isFormValid = computed(() => this.form.valid);

  /**
   * Emissor que notifica o componente pai sobre atualizações bem-sucedidas no contato.
   * @type {Output}
   */
  @Output() readonly contactUpdated = new EventEmitter<Contact>();

  /**
   * Setter para a propriedade de entrada que recebe um resumo do contato do ticket ativo.
   * Ao ser acionado, reinicia o formulário com dados parciais e carrega os dados completos.
   *
   * @param value Resumo do contato vindo do atendimento selecionado.
   */
  @Input() set contact(value: CalledContactSummary | null) {
    if (!value) {
      this.resetState();
      return;
    }

    this.contactId.set(value.id);
    this.attemptedSubmit.set(false);

    // Initial partial load from summary
    this.form.patchValue({
      name: value.name ?? '',
      email: value.email ?? '',
      phone: value.phone ?? '',
      whatsapp: value.whatsapp ?? '',
    });
    this.setupPhoneFromContact(value.whatsapp || undefined); // WhatsApp is the priority for sidebar

    this.fetchFullContact(value.id);
  }

  constructor() {
    this.loadCRMCompanies();
  }

  /**
   * Carregar a listagem de empresas ativas do CRM para o select de associação.
   * @private
   */
  private loadCRMCompanies(): void {
    this.crmCompanyService
      .list({ per_page: 100, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.crmCompanies.set(response.data),
        error: () => this.crmCompanies.set([]),
      });
  }

  /**
   * Persistir as alterações do contato no servidor após validar o formulário.
   */
  save(): void {
    this.attemptedSubmit.set(true);
    if (!this.contactId() || this.form.invalid) {
      this.form.markAllAsTouched();
      toast.error('Preencha os campos obrigatórios antes de continuar.');
      return;
    }

    this.isSaving.set(true);
    const payload = this.buildPayload();

    // Ensure contact ID is present
    const id = this.contactId()!;

    this.contactService
      .update(id, payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const updated = response.data.contact;
          this.isSaving.set(false);
          this.attemptedSubmit.set(false);
          this.contactSignal.set(updated);

          this.form.patchValue({
            name: updated.name ?? '',
            document: updated.document ?? '',
            email: updated.email ?? '',
            phone: updated.phone ?? '',
            crm_company_id: updated.crm_company_id ?? '',
            role: String(updated.custom_fields?.['role'] ?? ''),
            notes: String(updated.custom_fields?.['notes'] ?? ''),
          });
          // Whatsapp handled specially due to phone input component logic in buildPayload if modifying it back

          this.contactUpdated.emit(updated);
          toast.success('Contato atualizado com sucesso.');
        },
        error: () => {
          this.isSaving.set(false);
          toast.error('Não foi possível salvar as alterações.');
        },
      });
  }

  /**
   * Construir as propriedades do payload para envio à API baseado no estado do formulário.
   * @returns {Partial<Contact>} Dados mapeados do formulário.
   * @private
   */
  private buildPayload(): Partial<Contact> {
    const raw = this.form.getRawValue();
    return {
      name: raw.name?.trim(),
      document: raw.document?.trim() || undefined,
      email: raw.email?.trim() || undefined,
      phone: raw.phone?.trim() || undefined,
      whatsapp: this.buildPhonePayload(raw.whatsapp),
      crm_company_id: raw.crm_company_id || undefined,
      custom_fields: {
        ...(this.contactSignal()?.custom_fields ?? {}),
        role: raw.role?.trim() || undefined,
        notes: raw.notes?.trim() || undefined,
      },
    } as Partial<Contact>;
  }

  /**
   * Formatar a string de telefone para inclusão do código de país (DDI) para o backend.
   * @param phone String bruta do número do telefone.
   * @returns {string | undefined} Número formatado com DDI.
   * @private
   */
  private buildPhonePayload(phone: string | null | undefined): string | undefined {
    if (!phone) return undefined;
    const cleanPhone = phone.replace(/\D/g, '');
    if (!cleanPhone) return undefined;
    // App-phone-input returns cleaned number often, but we need to ensure country code is attached if the component stripped it or not.
    // Typically app-phone-input component manages the masked value.
    // If using raw value from control, it usually doesn't include country code if the mask is just (00) 0000.
    // We prepend selected country code.
    return this.selectedCountry().code + cleanPhone;
  }

  /**
   * Buscar os detalhes completos de um contato no backend via ID.
   * @param id Identificador do contato.
   * @private
   */
  private fetchFullContact(id: string | number): void {
    this.isLoading.set(true);
    this.contactService
      .find(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const contact = response.data.contact;
          this.contactSignal.set(contact);

          this.form.patchValue({
            name: contact.name ?? '',
            document: contact.document ?? '',
            email: contact.email ?? '',
            phone: contact.phone ?? '',
            crm_company_id: contact.crm_company_id ? String(contact.crm_company_id) : '',
            // Handle custom fields
            role: String(contact.custom_fields?.['role'] ?? ''),
            notes: String(contact.custom_fields?.['notes'] ?? ''),
          });

          // Handle WhatsApp specifically with Country Config
          // Use timeout to ensure form patch doesn't conflict or if mask directive needs a tick
          setTimeout(() => {
            this.setupPhoneFromContact(contact.whatsapp);
          });

          this.isLoading.set(false);
        },
        error: () => {
          this.resetState();
          this.isLoading.set(false);
          toast.error('Não foi possível carregar o contato.');
        },
      });
  }

  /**
   * Configurar adequadamente o país do seletor de telefone e o número formatado a partir da string bruta do contato.
   * @param phone String bruta do telefone gravada.
   * @private
   */
  private setupPhoneFromContact(phone: string | undefined): void {
    if (!phone) {
      this.selectedCountry.set(DEFAULT_COUNTRY);
      this.form.controls.whatsapp.setValue('');
      return;
    }

    const availableCountries = this.countries ?? [];
    const country = availableCountries.find((c) => phone.startsWith(c.code));
    if (country) {
      this.selectedCountry.set(country);
      // Remove country code from the displayed value in the control
      this.form.controls.whatsapp.setValue(phone.substring(country.code.length).trim());
    } else {
      this.selectedCountry.set(DEFAULT_COUNTRY);
      this.form.controls.whatsapp.setValue(phone);
    }
  }

  /**
   * Limpar o estado interno do componente e resetar o formulário.
   * @private
   */
  private resetState(): void {
    this.contactSignal.set(null);
    this.contactId.set(null);
    this.form.reset();
    this.attemptedSubmit.set(false);
  }
}
