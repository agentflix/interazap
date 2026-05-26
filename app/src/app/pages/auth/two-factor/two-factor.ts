import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { encode } from 'uqr';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCopyInputComponent,
  AfEmptyStateComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfPageTitleComponent,
  AfPasswordInputComponent,
  AfStatusBadgeComponent,
  AfTextInputComponent,
} from '@shared/components';
import {
  type TwoFactorSetupResponse,
  type TwoFactorStatusResponse,
  TwoFactorService,
} from '@core/services/two-factor.service';
import { AuthStoreService } from '@core/services/auth-store.service';

/** Fluxo multi-etapa de configuração da autenticação de dois fatores. */
type Step = 'status' | 'setup' | 'verify' | 'recovery' | 'complete';

/**
 * Página de autenticação de dois fatores — configuração, verificação, desativação e
 * regeneração de códigos de recuperação.
 */
@Component({
  selector: 'app-two-factor',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfModalComponent,
    AfPageTitleComponent,
    AfPasswordInputComponent,
    AfTextInputComponent,
    AfCopyInputComponent,
    AfAlertComponent,
    AfEmptyStateComponent,
    AfStatusBadgeComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './two-factor.html',
})
export class TwoFactor implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly service = inject(TwoFactorService);
  private readonly authStore = inject(AuthStoreService);
  private readonly destroyRef = inject(DestroyRef);

  // ── Step / loading ────────────────────────────────────────────────────────────
  readonly step = signal<Step>('status');
  readonly isLoading = signal(false);
  readonly loadError = signal(false);
  readonly isProcessing = signal(false);

  // ── 2FA state ────────────────────────────────────────────────────────────────
  readonly status = signal<TwoFactorStatusResponse['data'] | null>(null);
  readonly twoFactorEnabled = computed(() => this.status()?.enabled ?? false);
  readonly setupData = signal<TwoFactorSetupResponse['data'] | null>(null);
  readonly recoveryCodes = signal<string[]>([]);
  readonly qrCodeDataUrl = signal<string | null>(null);

  // ── Error messages ────────────────────────────────────────────────────────────
  readonly errorMessage = signal('');
  readonly disableError = signal('');
  readonly regenerateError = signal('');

  // ── Modal flags ───────────────────────────────────────────────────────────────
  readonly disableModalOpen = signal(false);
  readonly regenerateModalOpen = signal(false);

  // ── Form controls ─────────────────────────────────────────────────────────────
  readonly codeControl = this.fb.nonNullable.control('', [
    Validators.required,
    Validators.minLength(6),
    Validators.maxLength(6),
  ]);

  readonly disablePasswordControl = this.fb.nonNullable.control('', Validators.required);
  readonly regeneratePasswordControl = this.fb.nonNullable.control('', Validators.required);

  readonly verifyForm = this.fb.group({ code: this.codeControl });

  readonly secretControl = this.fb.nonNullable.control({ value: '', disabled: true });

  ngOnInit(): void {
    this.loadStatus();
  }

  // ── Data loading ─────────────────────────────────────────────────────────────

  /** Carrega o status atual do 2FA do usuário autenticado. */
  loadStatus(): void {
    this.isLoading.set(true);
    this.loadError.set(false);
    this.service
      .getStatus()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.status.set(response.data);
          this.isLoading.set(false);
        },
        error: () => {
          this.loadError.set(true);
          this.isLoading.set(false);
        },
      });
  }

  // ── Setup flow ────────────────────────────────────────────────────────────────

  /** Inicia o fluxo de configuração do 2FA, obtendo o secret e o QR Code. */
  startSetup(): void {
    if (this.isProcessing()) return;
    this.isProcessing.set(true);
    this.errorMessage.set('');

    this.service
      .setup()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.setupData.set(response.data);
          this.secretControl.setValue(response.data.secret);
          this.generateQrCode(response.data.qr_code_url);
          this.isProcessing.set(false);
          this.step.set('setup');
        },
        error: (error) => {
          this.isProcessing.set(false);
          this.errorMessage.set(error.error?.message || 'Não foi possível iniciar a configuração.');
        },
      });
  }

  /** Avança para a etapa de verificação do código TOTP. */
  goToVerify(): void {
    this.errorMessage.set('');
    this.codeControl.reset('');
    this.step.set('verify');
  }

  /** Valida o código TOTP digitado e ativa o 2FA em caso de sucesso. */
  validateCode(): void {
    if (this.verifyForm.invalid || this.isProcessing()) {
      this.verifyForm.markAllAsTouched();
      return;
    }

    const code = this.codeControl.value.trim();
    const setup = this.setupData();
    if (!setup?.secret) return;

    this.isProcessing.set(true);
    this.errorMessage.set('');

    this.service
      .validate(code)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isProcessing.set(false);
          this.recoveryCodes.set(response.data.recovery_codes ?? []);
          this.authStore.updateUser({ two_factor_enabled: true });
          this.step.set('recovery');
        },
        error: (error) => {
          this.isProcessing.set(false);
          this.errorMessage.set(
            error.error?.message || 'Código inválido. Verifique e tente novamente.',
          );
        },
      });
  }

  /** Conclui o fluxo de configuração avançando para a etapa final. */
  completeSetup(): void {
    this.step.set('complete');
  }

  // ── Utility actions ───────────────────────────────────────────────────────────

  /** Copia o secret TOTP para a área de transferência. */
  copySecret(): void {
    const secret = this.setupData()?.secret;
    if (secret) navigator.clipboard.writeText(secret).catch(() => null);
  }

  /** Copia os códigos de recuperação para a área de transferência. */
  copyCodes(): void {
    const text = this.recoveryCodes().join('\n');
    navigator.clipboard.writeText(text).catch(() => null);
  }

  /** Faz download dos códigos de recuperação como arquivo de texto. */
  downloadCodes(): void {
    const text = this.recoveryCodes().join('\n');
    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'recovery-codes.txt';
    a.click();
    URL.revokeObjectURL(url);
  }

  // ── Disable 2FA ───────────────────────────────────────────────────────────────

  /** Abre o modal de desativação do 2FA. */
  openDisableModal(): void {
    this.disablePasswordControl.reset('');
    this.disableError.set('');
    this.disableModalOpen.set(true);
  }

  /** Envia a senha para confirmar e desativar o 2FA. */
  disableTwoFactor(): void {
    if (this.disablePasswordControl.invalid || this.isProcessing()) {
      this.disablePasswordControl.markAsTouched();
      return;
    }

    this.isProcessing.set(true);
    this.disableError.set('');

    this.service
      .disable(this.disablePasswordControl.value)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isProcessing.set(false);
          this.disableModalOpen.set(false);
          this.authStore.updateUser({ two_factor_enabled: false });
          this.loadStatus();
        },
        error: (error) => {
          this.isProcessing.set(false);
          this.disableError.set(error.error?.message || 'Não foi possível desativar o 2FA.');
        },
      });
  }

  // ── Regenerate codes ──────────────────────────────────────────────────────────

  /** Abre o modal de regeneração dos códigos de recuperação. */
  openRegenerateModal(): void {
    this.regeneratePasswordControl.reset('');
    this.regenerateError.set('');
    this.regenerateModalOpen.set(true);
  }

  /** Confirma com a senha e regenera os códigos de recuperação. */
  regenerateCodes(): void {
    if (this.regeneratePasswordControl.invalid || this.isProcessing()) {
      this.regeneratePasswordControl.markAsTouched();
      return;
    }

    this.isProcessing.set(true);
    this.regenerateError.set('');

    this.service
      .regenerateRecoveryCodes(this.regeneratePasswordControl.value)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isProcessing.set(false);
          this.regenerateModalOpen.set(false);
          this.recoveryCodes.set(response.data.recovery_codes ?? []);
          this.step.set('recovery');
        },
        error: (error) => {
          this.isProcessing.set(false);
          this.regenerateError.set(
            error.error?.message || 'Não foi possível regenerar os códigos.',
          );
        },
      });
  }

  // ── Error getter ──────────────────────────────────────────────────────────────

  /** Retorna a mensagem de erro do campo de código TOTP quando tocado e inválido. */
  codeError(): string {
    const c = this.codeControl;
    if (!c.touched || !c.errors) return '';
    if (c.errors['required']) return 'Código é obrigatório.';
    if (c.errors['minlength'] || c.errors['maxlength'])
      return 'O código deve ter exatamente 6 dígitos.';
    return '';
  }

  // ── QR Code generation ────────────────────────────────────────────────────────

  private generateQrCode(value: string): void {
    if (!value) {
      this.qrCodeDataUrl.set(null);
      return;
    }

    if (value.startsWith('data:image/')) {
      this.qrCodeDataUrl.set(value);
      return;
    }

    try {
      const result = encode(value, { ecc: 'M' });
      const moduleSize = Math.max(1, Math.floor(220 / result.size));
      const actualSize = moduleSize * result.size;
      const canvas = document.createElement('canvas');
      canvas.width = actualSize;
      canvas.height = actualSize;
      const ctx = canvas.getContext('2d');
      if (!ctx) {
        this.qrCodeDataUrl.set(null);
        return;
      }

      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, actualSize, actualSize);

      ctx.fillStyle = '#000000';
      for (let y = 0; y < result.size; y++) {
        for (let x = 0; x < result.size; x++) {
          const qrData = result.data as (number | boolean)[] | (number | boolean)[][];
          const row = qrData[y];
          const isDark = Array.isArray(row)
            ? Boolean(row[x])
            : Boolean(qrData[y * result.size + x]);

          if (isDark) {
            ctx.fillRect(x * moduleSize, y * moduleSize, moduleSize, moduleSize);
          }
        }
      }

      this.qrCodeDataUrl.set(canvas.toDataURL('image/png'));
    } catch {
      this.qrCodeDataUrl.set(null);
    }
  }

  /** Limpa o QR Code quando ocorre erro no carregamento da imagem. */
  onQrImageError(): void {
    this.qrCodeDataUrl.set(null);
  }
}
