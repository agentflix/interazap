import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { map, startWith } from 'rxjs';
import { toast } from 'ngx-sonner';
import {
  isIntegrationConnected,
  type Integration,
  IntegrationService,
} from 'src/app/core/services/integration.service';
import {
  COUNTRIES,
  type Country,
  PhoneInputComponent,
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
  ],
  templateUrl: './channel-form.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChannelFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly integrationService = inject(IntegrationService);
  private readonly destroyRef = inject(DestroyRef);
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

  private readonly supportedCountries: Country[] = [...COUNTRIES].sort(
    (left, right) => right.code.length - left.code.length,
  );

  readonly providerOptions: SelectOption[] = [
    { label: 'UaZapi', value: 'uazapi' },
    { label: 'Z-API', value: 'zapi' },
  ];

  readonly form = this.fb.group({
    name: this.fb.control('', Validators.required),
    provider: this.fb.nonNullable.control('uazapi', Validators.required),
    token: this.fb.control(''),
    instance: this.fb.control(''),
    client_token: this.fb.control(''),
    cellphone: this.fb.nonNullable.control('', Validators.required),
    country_code: this.fb.nonNullable.control('+55', Validators.required),
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
  });

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
        });

        this.form.enable({ emitEvent: false });
        this.updateTokenValidators(integration.provider || 'uazapi');
        this.applyConnectionLock(integration);
      } else {
        this.lastLoadedIntegrationId.set(null);
        this.resetForm();
        this.form.enable({ emitEvent: false });
        this.updateTokenValidators(this.form.controls.provider.value ?? 'uazapi');
      }
    });

    this.form.controls.provider.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((provider) => {
        this.updateTokenValidators(provider ?? 'uazapi');
      });
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

    const payload: Partial<Integration> = {
      name: formValue.name || '',
      provider,
      token: formValue.token || undefined,
      is_active: formValue.is_active ?? true,
      evaluation_enabled: formValue.evaluation_enabled ?? false,
      evaluation_cutoff_score: formValue.evaluation_cutoff_score ?? 3,
      settings: {
        channel_provider_id: integrationId,
        cellphone: this.buildInternationalCellphone(
          formValue.cellphone || '',
          formValue.country_code || '+55',
        ),
        instance: formValue.instance || undefined,
        client_token: formValue.client_token || undefined,
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
      },
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
    });

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
