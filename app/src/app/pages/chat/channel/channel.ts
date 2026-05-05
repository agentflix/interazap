import {
  type OnInit,
  Component,
  ChangeDetectionStrategy,
  inject,
  signal,
  computed,
  DestroyRef,
  effect,
  viewChild,
  untracked,
} from '@angular/core';
import { type HttpErrorResponse } from '@angular/common/http';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import { toast } from 'ngx-sonner';
import {
  AfCrudPageComponent,
  AfDataTableComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfConfirmModalComponent,
  AfAlertComponent,
  AfTextInputComponent,
} from '@shared/components';
import {
  getIntegrationConnectionUiState,
  isIntegrationConnected,
  IntegrationService,
  type Integration,
  type IntegrationConnectionUiState,
} from '@core/services/integration.service';
import { COUNTRIES, type Country } from '@shared/components/inputs';
import {
  ChatRealtimeService,
  type IntegrationConnectionEvent,
} from '@core/services/chat-realtime.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import { ChannelFormComponent } from './components/channel-form/channel-form';
import { ChannelRoutingComponent } from './components/channel-routing/channel-routing';
import { ChannelRealtimeAdapter } from './channel-realtime.adapter';

const PHONE_COUNTRIES: Country[] = [...COUNTRIES].sort(
  (left, right) => right.code.length - left.code.length,
);

@Component({
  selector: 'app-channel-page',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfAlertComponent,
    AfTextInputComponent,
    ChannelFormComponent,
    ChannelRoutingComponent,
/**
 * Channel page component for the Chat module.
 * @selector app-channel-page
 */
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './channel.html',
})
export class ChannelPage implements OnInit {
  private readonly integrationService = inject(IntegrationService);
  private readonly realtime = inject(ChatRealtimeService);
  private readonly _integrationRealtimeAdapter = inject(ChannelRealtimeAdapter);
  private readonly destroyRef = inject(DestroyRef);
  private readonly authStore = inject(AuthStoreService);
  private readonly fb = inject(FormBuilder);

  readonly integrationFormRef = viewChild<ChannelFormComponent>('integrationForm');

  // State
  readonly integrations = signal<Integration[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly searchTerm = signal('');

  // Modal State
  readonly showFormModal = signal(false);
  readonly selectedIntegration = signal<Integration | null>(null);

  readonly showDeleteModal = signal(false);
  readonly integrationToDelete = signal<Integration | null>(null);

  // Routing Modal State
  readonly selectedRoutingChannel = signal<Integration | null>(null);
  readonly canManageRouting = computed(() => this.authStore.hasPermission('chat.routing.manage'));

  // QR Code State
  readonly showQrModal = signal(false);
  readonly qrCodeData = signal<string | null>(null);
  readonly pairCodeData = signal<string | null>(null);
  readonly connectExpiresAt = signal<string | null>(null);

  readonly connectForm = this.fb.group({
    mode: this.fb.control<'qr' | 'pair'>('qr', { nonNullable: true }),
    phone: this.fb.control('', [Validators.pattern(/^[0-9+]*$/)]),
  });

  readonly connectActionLabel = computed(() =>
    this.connectForm.controls.mode.value === 'qr' ? 'Gerar QR Code' : 'Gerar Pair Code',
  );

  readonly isConnectingInstance = signal(false);

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.integrations().length === 0,
  );

  private lastAppliedConnectionVersion = 0;

  private readonly integrationRealtimeEffect = effect(() => {
    const payload = this.realtime.integrationConnection();
    if (payload.version <= 0 || payload.version === this.lastAppliedConnectionVersion) {
      return;
    }

    const event = payload.event;
    if (!event) {
      return;
    }

    // Track version to prevent re-processing the same event on re-trigger
    this.lastAppliedConnectionVersion = payload.version;
    untracked(() => this.applyRealtimeConnectionUpdate(event));
  });

  ngOnInit(): void {
    void this._integrationRealtimeAdapter;
    this.realtime.connect();
    this.loadIntegrations();
  }

  loadIntegrations(page = 1): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.integrationService
      .list({
        page,
        per_page: 15,
        search: this.searchTerm() || undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.integrations.set(res.data);
          this.meta.set(res.meta);
          this.isLoading.set(false);
        },
        error: () => {
          this.hasError.set(true);
          this.isLoading.set(false);
        },
      });
  }

  onSearch(term: string): void {
    this.searchTerm.set(term);
    this.loadIntegrations(1);
  }

  loadPage(page: number): void {
    this.loadIntegrations(page);
  }

  // CRUD Actions
  openCreate(): void {
    this.selectedIntegration.set(null);
    this.showFormModal.set(true);
  }

  openEdit(item: Integration): void {
    this.selectedIntegration.set(item);
    this.showFormModal.set(true);
  }

  openDelete(item: Integration): void {
    if (this.isConnected(item)) {
      toast.error('Não é possível excluir um canal conectado. Desconecte primeiro.');
      return;
    }

    this.integrationToDelete.set(item);
    this.showDeleteModal.set(true);
  }

  handleFormSaved(item: Integration): void {
    void item; // suppress unused
    this.showFormModal.set(false);
    toast.success('Canal salvo com sucesso');
    this.loadIntegrations(this.meta().current_page);
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
  }

  openRouting(item: Integration): void {
    this.selectedRoutingChannel.set(item);
  }

  handleRoutingClosed(): void {
    this.selectedRoutingChannel.set(null);
  }

  handleDeleteConfirmed(): void {
    const item = this.integrationToDelete();
    if (!item) return;

    this.integrationService
      .delete(item.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          toast.success('Canal removido');
          this.showDeleteModal.set(false);
          this.loadIntegrations(this.meta().current_page);
        },
        error: (err: HttpErrorResponse) => {
          if (err.status === 409) {
            toast.error('Não é possível excluir um canal conectado. Desconecte primeiro.');
            this.showDeleteModal.set(false);
            this.loadIntegrations(this.meta().current_page);
            return;
          }

          toast.error('Erro ao remover canal');
        },
      });
  }

  // Connection Actions
  connect(item: Integration): void {
    if (!this.canConnect(item)) {
      toast.error(
        'Fluxo de conexão disponível apenas para canais UaZapi com token configurado.',
      );
      return;
    }

    this.selectedIntegration.set(item);
    this.showQrModal.set(true);
    this.qrCodeData.set(null);
    this.pairCodeData.set(null);
    this.connectExpiresAt.set(null);
    this.connectForm.reset({ mode: 'qr', phone: '' });
  }

  closeQrModal(): void {
    this.showQrModal.set(false);
    this.qrCodeData.set(null);
    this.pairCodeData.set(null);
    this.connectExpiresAt.set(null);
    this.loadIntegrations(this.meta().current_page);
  }

  submitConnect(): void {
    const target = this.selectedIntegration();
    if (!target || this.isConnectingInstance()) return;

    if (this.connectForm.controls.mode.value === 'pair') {
      this.connectForm.controls.phone.addValidators([Validators.required]);
      this.connectForm.controls.phone.updateValueAndValidity({ emitEvent: false });
    } else {
      this.connectForm.controls.phone.removeValidators([Validators.required]);
      this.connectForm.controls.phone.updateValueAndValidity({ emitEvent: false });
    }

    if (this.connectForm.invalid) {
      this.connectForm.markAllAsTouched();
      return;
    }

    this.isConnectingInstance.set(true);
    this.qrCodeData.set(null);
    this.pairCodeData.set(null);
    this.connectExpiresAt.set(null);

    this.integrationService
      .connect(target.id, {
        mode: this.connectForm.controls.mode.value,
        phone: this.resolveConnectPhone(),
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.isConnectingInstance.set(false);
          const connection = res.data.connection;

          if (connection.mode === 'qr' && connection.qr_code) {
            this.qrCodeData.set(this.normalizeQrCode(connection.qr_code));
          } else if (connection.mode === 'pair' && connection.pair_code) {
            this.pairCodeData.set(connection.pair_code);
          }

          if (connection.expires_at) {
            this.connectExpiresAt.set(connection.expires_at);
          }

          if (this.connectForm.controls.mode.value === 'qr' && !this.qrCodeData()) {
            toast.warning('QR Code não retornado. Tente novamente em alguns segundos.');
          }

          this.loadIntegrations(this.meta().current_page);
        },
        error: () => {
          this.isConnectingInstance.set(false);
          toast.error('Erro ao iniciar conexão');
        },
      });
  }

  private resolveConnectPhone(): string | null {
    if (this.connectForm.controls.mode.value !== 'pair') return null;
    const phoneValue = this.connectForm.controls.phone.value;
    const sanitizedPhone = typeof phoneValue === 'string' ? phoneValue.trim() : '';
    return sanitizedPhone.length > 0 ? sanitizedPhone : null;
  }

  canConnect(item: Integration): boolean {
    return item.provider === 'uazapi' && !this.isConnected(item) && item.has_token === true;
  }

  isConnected(item: Integration): boolean {
    return isIntegrationConnected(item);
  }

  connectionState(item: Integration): IntegrationConnectionUiState {
    return getIntegrationConnectionUiState(item);
  }

  connectionLabel(item: Integration): string {
    switch (this.connectionState(item)) {
      case 'connected':
        return 'Conectado';
      case 'disconnected':
        return 'Desconectado';
      case 'connecting':
        return 'Conectando...';
      case 'qr':
        return 'Aguardando Leitura';
      default:
        return 'Desconhecido';
    }
  }

  private applyRealtimeConnectionUpdate(event: IntegrationConnectionEvent): void {
    const resolvedStatus = getIntegrationConnectionUiState(event);

    if (resolvedStatus === 'unknown') {
      return;
    }

    this.integrations.update((items) => {
      let hasChanges = false;

      const nextItems = items.map((item) => {
        if (!this.matchesIntegrationEvent(item, event)) {
          return item;
        }

        if (
          item.connection_status === resolvedStatus &&
          item.is_connected === (resolvedStatus === 'connected')
        ) {
          return item;
        }

        hasChanges = true;
        return {
          ...item,
          is_connected: resolvedStatus === 'connected',
          connection_status: resolvedStatus,
        };
      });

      return hasChanges ? nextItems : items;
    });

    const selected = this.selectedIntegration();
    if (selected && this.matchesIntegrationEvent(selected, event)) {
      if (resolvedStatus === 'connected') {
        this.isConnectingInstance.set(false);
        this.showQrModal.set(false);
        toast.success('WhatsApp conectado com sucesso');
      }

      const incomingQr = typeof event.qrcode === 'string' ? event.qrcode.trim() : '';
      if (incomingQr.length > 0) {
        this.isConnectingInstance.set(false);
        this.qrCodeData.set(this.normalizeQrCode(incomingQr));
      }
    }
  }

  private matchesIntegrationEvent(item: Integration, event: IntegrationConnectionEvent): boolean {
    const eventInstanceId = typeof event.instance_id === 'string' ? event.instance_id.trim() : '';
    if (eventInstanceId.length > 0 && item.id === eventInstanceId) {
      return true;
    }

    const eventToken = typeof event.token === 'string' ? event.token.trim() : '';
    if (eventToken.length === 0) {
      return false;
    }

    const tokens = [item.token, item.settings.token, item.settings.client_token]
      .filter((value): value is string => typeof value === 'string')
      .map((value) => value.trim())
      .filter((value) => value.length > 0);

    return tokens.includes(eventToken);
  }

  private normalizeQrCode(value: string): string {
    const trimmed = value.trim();
    if (trimmed.startsWith('data:image/')) {
      return trimmed;
    }

    return `data:image/png;base64,${trimmed}`;
  }

  disconnect(item: Integration): void {
    if (!confirm(`Desconectar ${item.name}?`)) return;

    this.integrationService
      .disconnect(item.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          toast.success('Desconectado com sucesso');
          this.loadIntegrations(this.meta().current_page);
        },
        error: () => toast.error('Erro ao desconectar'),
      });
  }

  formatPhone(phone: string | undefined): string {
    if (typeof phone !== 'string') {
      return '—';
    }

    const normalized = phone.trim();
    if (normalized.length === 0) {
      return '—';
    }

    const digits = normalized.replace(/\D/g, '');

    const matchedCountry = PHONE_COUNTRIES.find((country) => {
      const dialDigits = country.code.replace(/\D/g, '');
      if (!digits.startsWith(dialDigits)) {
        return false;
      }

      return digits.slice(dialDigits.length).length >= 6;
    });

    if (matchedCountry && matchedCountry.code !== '+55') {
      const dialDigits = matchedCountry.code.replace(/\D/g, '');
      const localDigits = digits.slice(dialDigits.length);
      return this.formatInternationalPhone(matchedCountry.code, localDigits);
    }

    const localDigits = matchedCountry?.code === '+55' ? digits.slice(2) : digits;

    if (localDigits.length === 11) {
      return `(${localDigits.slice(0, 2)}) ${localDigits.slice(2, 7)}-${localDigits.slice(7)}`;
    }

    if (localDigits.length === 10) {
      return `(${localDigits.slice(0, 2)}) ${localDigits.slice(2, 6)}-${localDigits.slice(6)}`;
    }

    return normalized;
  }

  private formatInternationalPhone(countryCode: string, localDigits: string): string {
    if (localDigits.length <= 4) {
      return `${countryCode} ${localDigits}`.trim();
    }

    const lastGroup = localDigits.slice(-4);
    let remainingDigits = localDigits.slice(0, -4);
    const groups: string[] = [];

    while (remainingDigits.length > 3) {
      groups.unshift(remainingDigits.slice(-3));
      remainingDigits = remainingDigits.slice(0, -3);
    }

    if (remainingDigits.length > 0) {
      groups.unshift(remainingDigits);
    }

    return `${countryCode} ${[...groups, lastGroup].join(' ')}`.trim();
  }
}
