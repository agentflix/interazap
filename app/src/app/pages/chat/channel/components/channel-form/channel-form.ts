import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  computed,
  DestroyRef,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { FormBuilder, FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { map, startWith } from 'rxjs';
import { toast } from 'ngx-sonner';
import {
  isIntegrationConnected,
  type Integration,
  IntegrationService,
} from 'src/app/core/services/integration.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import { TenantSettingsService } from '@core/services/tenant-settings.service';
import type { TenantChatAutoCloseSettings } from '@shared/models/tenant-settings.model';
import {
  COUNTRIES,
  type Country,
  PhoneInputComponent,
  RadioInputComponent,
  type SelectOption,
  SelectInputComponent,
  SwitchInputComponent,
  TextareaInputComponent,
  TextInputComponent,
} from '@shared/components/inputs';

/**
 * Formulario para criacao e edicao de canais de chat.
 *
 * @remarks
 * Suporta multiplos provedores (UaZapi, Z-API)
 * com validacao condicional de campos e gerenciamento de conexao.
 *
 * @example
 * ```html
 * <app-channel-form
 *   [channel]="channel"
 *   (saved)="onSaved($event)"
 *   (cancelled)="onCancelled()" />
 * ```
 */
@Component({
  selector: 'app-channel-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    TextInputComponent,
    TextareaInputComponent,
    PhoneInputComponent,
    SelectInputComponent,
    SwitchInputComponent,
    RadioInputComponent,
  ],
  templateUrl: './channel-form.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChannelFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly integrationService = inject(IntegrationService);
  private readonly authStore = inject(AuthStoreService);
  private readonly tenantSettingsService = inject(TenantSettingsService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly lastLoadedIntegrationId = signal<string | null>(null);
  private readonly unresolvedCellphone = signal<string | null>(null);
  private readonly loadedCellphoneSnapshot = signal<{
    countryCode: string;
    localDigits: string;
  } | null>(null);

  readonly integration = input<Integration | null>(null);
  readonly saved = output<Integration>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly activeTab = signal<'general' | 'autoClose'>('general');
  readonly tenantChatSettings = signal<TenantChatAutoCloseSettings | null>(null);

  private readonly supportedCountries: Country[] = [...COUNTRIES].sort(
    (left, right) => right.code.length - left.code.length,
  );

  readonly providerOptions: SelectOption[] = [
    { label: 'UaZapi', value: 'uazapi' },
    { label: 'Z-API', value: 'zapi' },
    { label: 'Meta', value: 'meta' },
    { label: 'Telegram', value: 'telegram' },
  ];

  readonly form = this.fb.group({
    name: this.fb.control('', Validators.required),
    provider: this.fb.nonNullable.control('uazapi', Validators.required),
    token: this.fb.control(''),
    instance: this.fb.control(''),
    client_token: this.fb.control(''),
    cellphone: this.fb.nonNullable.control('', Validators.required),
    country_code: this.fb.nonNullable.control('+55', Validators.required),
    phone_number_id: this.fb.control(''),
    access_token: this.fb.control(''),
    bot_token: this.fb.control(''),
    is_active: this.fb.control(true),
    send_attendant_name: this.fb.control(false),
    send_outside_business_hours_message: this.fb.control(false),
    outside_business_hours_message: this.fb.control(''),
    send_no_business_hours_message: this.fb.control(false),
    no_business_hours_message: this.fb.control(''),
    send_department_transfer_message: this.fb.control(false),
    department_transfer_message: this.fb.control(''),
    send_start_service_message: this.fb.control(false),
    start_service_message: this.fb.control(''),
    send_end_service_message: this.fb.control(false),
    end_service_message: this.fb.control(''),
    channel_fallback_message: this.fb.control(''),
    evaluation_enabled: this.fb.control(false),
    evaluation_cutoff_score: this.fb.control(3, [
      Validators.required,
      Validators.min(1),
      Validators.max(5),
    ]),
    auto_close_enabled: this.fb.control<boolean | null>(null),
    auto_close_after_minutes: this.fb.control<number | null>(null),
    auto_close_target: this.fb.control<'both' | 'client' | 'agent' | null>(null),
    auto_close_message: this.fb.control<string | null>(null),
  });

  // ── Auto-fechamento ──────────────────────────────────────────────────────

  /** Toggle que controla se o canal herda a configuracao global do tenant */
  readonly useGlobalToggle = new FormControl<boolean>(false, { nonNullable: true });

  /** Indica se o canal deve herdar a configuracao global do tenant */
  readonly isUsingGlobalConfig = toSignal(
    this.useGlobalToggle.valueChanges.pipe(startWith(this.useGlobalToggle.value)),
    { initialValue: true },
  );

  readonly timeOptions: SelectOption[] = [
    { value: 5, label: 'Após 5 minutos' },
    { value: 10, label: 'Após 10 minutos' },
    { value: 15, label: 'Após 15 minutos' },
    { value: 30, label: 'Após 30 minutos' },
    { value: 45, label: 'Após 45 minutos' },
    { value: 60, label: 'Após 1 hora' },
    { value: 120, label: 'Após 2 horas' },
  ];

  readonly targetOptions = [
    { value: 'both', label: 'Ambos (atendente e cliente)' },
    { value: 'client', label: 'Apenas cliente' },
    { value: 'agent', label: 'Apenas atendente' },
  ];

  readonly isZapi = toSignal(
    this.form.controls.provider.valueChanges.pipe(
      startWith(this.form.controls.provider.value),
      map((provider) => provider === 'zapi'),
    ),
    { initialValue: false },
  );

  readonly isUazapi = toSignal(
    this.form.controls.provider.valueChanges.pipe(
      startWith(this.form.controls.provider.value),
      map((provider) => provider === 'uazapi'),
    ),
    { initialValue: true },
  );

  readonly isMeta = toSignal(
    this.form.controls.provider.valueChanges.pipe(
      startWith(this.form.controls.provider.value),
      map((provider) => provider === 'meta'),
    ),
    { initialValue: false },
  );

  readonly isTelegram = toSignal(
    this.form.controls.provider.valueChanges.pipe(
      startWith(this.form.controls.provider.value),
      map((provider) => provider === 'telegram'),
    ),
    { initialValue: false },
  );

  readonly isWhatsappProvider = computed(() => !this.isTelegram());

  readonly isEvaluationEnabled = toSignal(
    this.form.controls.evaluation_enabled.valueChanges.pipe(
      startWith(this.form.controls.evaluation_enabled.value),
      map((value) => value === true),
    ),
    { initialValue: false },
  );

  readonly isTokenRequired = computed(() => this.isUazapi() && !this.integration());

  readonly tokenHelpText = computed(() => {
    if (!this.isUazapi()) {
      return undefined;
    }

    if (this.integration()?.has_token === true) {
      return 'Token já configurado. Deixe em branco para manter o token atual.';
    }

    if (this.integration()) {
      return 'Opcional. Preencha apenas para alterar o token atual.';
    }

    return 'Obrigatório para conectar instâncias UaZapi.';
  });

  readonly tokenLabel = computed(() => (this.isUazapi() ? 'Token da Instância' : 'Token'));

  readonly whatsappHelpText = computed(() => {
    if (this.unresolvedCellphone()) {
      return 'DDI do número salvo não foi reconhecido com segurança. O valor atual será preservado até que telefone ou DDI sejam ajustados manualmente.';
    }

    return 'Informe o número local. O DDI será enviado junto automaticamente.';
  });

  constructor() {
    effect(() => {
      const integration = this.integration();
      if (integration) {
        if (this.lastLoadedIntegrationId() === String(integration.id)) {
          return;
        }

        this.lastLoadedIntegrationId.set(String(integration.id));
        const cellphone = this.splitCellphone(integration.settings?.cellphone);
        this.unresolvedCellphone.set(cellphone.preservedOriginal);
        this.loadedCellphoneSnapshot.set({
          countryCode: cellphone.countryCode,
          localDigits: cellphone.local.replace(/\D/g, ''),
        });

        this.form.reset({
          name: integration.name,
          provider: integration.provider || 'uazapi',
          token: '',
          instance: integration.settings?.instance || '',
          client_token: integration.settings?.client_token || '',
          cellphone: cellphone.local,
          country_code: cellphone.countryCode,
          phone_number_id: integration.settings?.phone_number_id || '',
          access_token: integration.settings?.access_token || '',
          bot_token: '',
          is_active: integration.is_active,
          send_attendant_name: integration.settings?.send_attendant_name ?? false,
          send_outside_business_hours_message:
            integration.settings?.send_outside_business_hours_message ?? false,
          outside_business_hours_message:
            integration.settings?.outside_business_hours_message ?? '',
          send_no_business_hours_message:
            integration.settings?.send_no_business_hours_message ?? false,
          no_business_hours_message: integration.settings?.no_business_hours_message ?? '',
          send_department_transfer_message:
            integration.settings?.send_department_transfer_message ?? false,
          department_transfer_message: integration.settings?.department_transfer_message ?? '',
          send_start_service_message: integration.settings?.send_start_service_message ?? false,
          start_service_message: integration.settings?.start_service_message ?? '',
          send_end_service_message: integration.settings?.send_end_service_message ?? false,
          end_service_message: integration.settings?.end_service_message ?? '',
          channel_fallback_message: integration.settings?.channel_fallback_message ?? '',
          evaluation_enabled: integration.evaluation_enabled ?? false,
          evaluation_cutoff_score: integration.evaluation_cutoff_score ?? 3,
          auto_close_enabled: integration.settings?.auto_close_enabled ?? null,
          auto_close_after_minutes: integration.settings?.auto_close_after_minutes ?? null,
          auto_close_target: integration.settings?.auto_close_target ?? null,
          auto_close_message: integration.settings?.auto_close_message ?? null,
        });

        this.form.enable({ emitEvent: false });
        this.syncGlobalToggle();
        this.updateTokenValidators(integration.provider || 'uazapi');
        this.updateMetaFieldValidators(integration.provider || 'uazapi');
        this.updateTelegramFieldValidators(integration.provider || 'uazapi');
        this.applyConnectionLock(integration);
        this.fetchTenantSettings();
      } else {
        this.lastLoadedIntegrationId.set(null);
        this.resetForm();
        this.form.enable({ emitEvent: false });
        this.updateTokenValidators(this.form.controls.provider.value ?? 'uazapi');
        this.updateMetaFieldValidators(this.form.controls.provider.value ?? 'uazapi');
        this.updateTelegramFieldValidators(this.form.controls.provider.value ?? 'uazapi');
      }
    });

    this.useGlobalToggle.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((useGlobal) => {
        if (useGlobal) {
          this.form.patchValue(
            {
              auto_close_enabled: null,
              auto_close_after_minutes: null,
              auto_close_target: null,
              auto_close_message: null,
            },
            { emitEvent: false },
          );
        } else {
          this.form.patchValue(
            {
              auto_close_enabled: false,
              auto_close_after_minutes: 30,
              auto_close_target: 'both',
              auto_close_message: '',
            },
            { emitEvent: false },
          );
        }

        this.cdr.markForCheck();
      });

    this.form.controls.provider.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((provider) => {
        this.updateTokenValidators(provider ?? 'uazapi');
        this.updateMetaFieldValidators(provider ?? 'uazapi');
        this.updateTelegramFieldValidators(provider ?? 'uazapi');
      });
  }

  /** Sincroniza o toggle "Usar configuracao global" com o estado atual dos campos */
  private syncGlobalToggle(): void {
    const allNull =
      this.form.controls.auto_close_enabled.value === null &&
      this.form.controls.auto_close_after_minutes.value === null &&
      this.form.controls.auto_close_target.value === null &&
      this.form.controls.auto_close_message.value === null;

    this.useGlobalToggle.setValue(allNull, { emitEvent: false });
  }

  /** Busca as configuracoes de auto-fechamento do tenant para exibir os valores herdados */
  private fetchTenantSettings(): void {
    const tenantId = this.authStore.user()?.tenant_id;
    if (!tenantId) {
      return;
    }

    this.tenantSettingsService
      .getSettings(String(tenantId))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.tenantChatSettings.set(response.data.settings_chat ?? null);
          this.cdr.markForCheck();
        },
        error: () => {
          this.tenantChatSettings.set(null);
          this.cdr.markForCheck();
        },
      });
  }

  private updateMetaFieldValidators(provider: string): void {
    const phoneNumberIdControl = this.form.controls.phone_number_id;
    const accessTokenControl = this.form.controls.access_token;

    if (provider === 'meta') {
      phoneNumberIdControl.setValidators([Validators.required]);
      accessTokenControl.setValidators([Validators.required]);
    } else {
      phoneNumberIdControl.clearValidators();
      accessTokenControl.clearValidators();
    }

    phoneNumberIdControl.updateValueAndValidity({ emitEvent: false });
    accessTokenControl.updateValueAndValidity({ emitEvent: false });
  }

  private updateTelegramFieldValidators(provider: string): void {
    const botTokenControl = this.form.controls.bot_token;
    const cellphoneControl = this.form.controls.cellphone;
    const countryCodeControl = this.form.controls.country_code;

    if (provider === 'telegram') {
      botTokenControl.setValidators([
        Validators.required,
        Validators.pattern(/^\d+:[A-Za-z0-9_-]+$/),
      ]);
      cellphoneControl.clearValidators();
      countryCodeControl.clearValidators();
    } else {
      botTokenControl.clearValidators();
      botTokenControl.setValue('', { emitEvent: false });
      cellphoneControl.setValidators([Validators.required]);
      countryCodeControl.setValidators([Validators.required]);
    }

    botTokenControl.updateValueAndValidity({ emitEvent: false });
    cellphoneControl.updateValueAndValidity({ emitEvent: false });
    countryCodeControl.updateValueAndValidity({ emitEvent: false });
  }

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const formValue = this.form.getRawValue();

    const providerMap: Record<string, number> = {
      zapi: 1,
      uazapi: 5,
    };

    const provider = formValue.provider || 'uazapi';
    const integrationId = providerMap[provider] || 5;
    const settings: Partial<Integration['settings']> = {
      channel_provider_id: integrationId,
      cellphone:
        provider === 'telegram'
          ? undefined
          : this.buildInternationalCellphone(
              formValue.cellphone || '',
              formValue.country_code || '+55',
            ),
      instance: formValue.instance || undefined,
      client_token: formValue.client_token || undefined,
      phone_number_id: formValue.phone_number_id || undefined,
      access_token: formValue.access_token || undefined,
      bot_token: provider === 'telegram' ? formValue.bot_token || undefined : undefined,
      send_attendant_name: formValue.send_attendant_name ?? false,
      send_outside_business_hours_message: formValue.send_outside_business_hours_message ?? false,
      outside_business_hours_message: formValue.outside_business_hours_message || undefined,
      send_no_business_hours_message: formValue.send_no_business_hours_message ?? false,
      no_business_hours_message: formValue.no_business_hours_message || undefined,
      send_department_transfer_message: formValue.send_department_transfer_message ?? false,
      department_transfer_message: formValue.department_transfer_message || undefined,
      send_start_service_message: formValue.send_start_service_message ?? false,
      start_service_message: formValue.start_service_message || undefined,
      send_end_service_message: formValue.send_end_service_message ?? false,
      end_service_message: formValue.end_service_message || undefined,
      channel_fallback_message: formValue.channel_fallback_message || undefined,
      auto_close_enabled: formValue.auto_close_enabled ?? undefined,
      auto_close_after_minutes: formValue.auto_close_after_minutes ?? undefined,
      auto_close_target: formValue.auto_close_target ?? undefined,
      auto_close_message: formValue.auto_close_message ?? undefined,
    };

    const payload: Partial<Integration> = {
      name: formValue.name || '',
      provider,
      token: formValue.token || undefined,
      is_active: formValue.is_active ?? true,
      evaluation_enabled: formValue.evaluation_enabled ?? false,
      evaluation_cutoff_score: formValue.evaluation_cutoff_score ?? 3,
      settings: settings as Integration['settings'],
    };

    if (!payload.token) {
      delete payload.token;
    }

    const editing = this.integration();
    this.isSaving.set(true);

    const request = editing
      ? this.integrationService.update(editing.id, payload)
      : this.integrationService.create(payload);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.saved.emit(response.data);
      },
      error: (error) => {
        this.isSaving.set(false);
        toast.error(error.error?.message || 'Não foi possível salvar o canal.');
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  private resetForm(): void {
    this.unresolvedCellphone.set(null);
    this.loadedCellphoneSnapshot.set({ countryCode: '+55', localDigits: '' });

    this.form.reset({
      name: '',
      provider: 'uazapi',
      token: '',
      instance: '',
      client_token: '',
      cellphone: '',
      country_code: '+55',
      phone_number_id: '',
      access_token: '',
      bot_token: '',
      is_active: true,
      send_attendant_name: false,
      send_outside_business_hours_message: false,
      outside_business_hours_message: '',
      send_no_business_hours_message: false,
      no_business_hours_message: '',
      send_department_transfer_message: false,
      department_transfer_message: '',
      send_start_service_message: false,
      start_service_message: '',
      send_end_service_message: false,
      end_service_message: '',
      channel_fallback_message: '',
      evaluation_enabled: false,
      evaluation_cutoff_score: 3,
      auto_close_enabled: null,
      auto_close_after_minutes: null,
      auto_close_target: null,
      auto_close_message: null,
    });

    this.useGlobalToggle.setValue(true, { emitEvent: false });
    this.updateTokenValidators('uazapi');
  }

  private updateTokenValidators(provider: string): void {
    const tokenControl = this.form.controls.token;
    const isEditing = !!this.integration();

    if (provider === 'uazapi' && !isEditing) {
      tokenControl.setValidators([Validators.required]);
    } else {
      tokenControl.clearValidators();
    }

    tokenControl.updateValueAndValidity({ emitEvent: false });
  }

  private applyConnectionLock(integration: Integration): void {
    if (isIntegrationConnected(integration)) {
      this.form.controls.provider.disable({ emitEvent: false });
      this.form.controls.instance.disable({ emitEvent: false });
      this.form.controls.client_token.disable({ emitEvent: false });
      this.form.controls.phone_number_id.disable({ emitEvent: false });
      this.form.controls.access_token.disable({ emitEvent: false });
      this.form.controls.is_active.disable({ emitEvent: false });
    }
  }

  private splitCellphone(value: string | undefined): {
    countryCode: string;
    local: string;
    preservedOriginal: string | null;
  } {
    if (!value) {
      return { countryCode: '+55', local: '', preservedOriginal: null };
    }

    const normalized = value.trim();
    const digits = normalized.replace(/\D/g, '');

    for (const country of this.supportedCountries) {
      const dialDigits = country.code.replace(/\D/g, '');
      if (!digits.startsWith(dialDigits)) {
        continue;
      }

      const local = digits.slice(dialDigits.length);
      if (local.length >= 8 && local.length <= 11) {
        return { countryCode: country.code, local, preservedOriginal: null };
      }
    }

    return {
      countryCode: '+55',
      local: digits.length <= 11 ? digits : digits.slice(-11),
      preservedOriginal: normalized,
    };
  }

  private buildInternationalCellphone(phone: string, countryCode: string): string {
    const localDigits = phone.replace(/\D/g, '');
    const loadedSnapshot = this.loadedCellphoneSnapshot();
    const unresolvedCellphone = this.unresolvedCellphone();

    const isUnchangedFromLoad =
      loadedSnapshot !== null &&
      countryCode === loadedSnapshot.countryCode &&
      localDigits === loadedSnapshot.localDigits;

    if (unresolvedCellphone && isUnchangedFromLoad) {
      return unresolvedCellphone;
    }

    if (!localDigits) {
      return '';
    }

    const dialDigits = countryCode.replace(/\D/g, '') || '55';
    return `+${dialDigits}${localDigits}`;
  }
}
