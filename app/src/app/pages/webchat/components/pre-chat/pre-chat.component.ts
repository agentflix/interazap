import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  signal,
  output,
} from '@angular/core';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { merge } from 'rxjs';
import { debounceTime, startWith } from 'rxjs/operators';
import { AfPhoneInputComponent, AfLoadingButtonComponent } from '@shared/components';
import { WebChatService } from '../../services/webchat.service';
import { type PreChatFormState } from '../../webchat.model';

/**
 * PreChatComponent — collects visitor name and WhatsApp before starting a chat session.
 * Displays a branded welcome screen matching the FEAT-040 wireframe.
 */
@Component({
  selector: 'app-pre-chat',
  standalone: true,
  imports: [ReactiveFormsModule, AfPhoneInputComponent, AfLoadingButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './pre-chat.component.html',
})
export class PreChatComponent implements OnInit {
  private readonly webchatService = inject(WebChatService);
  private readonly destroyRef = inject(DestroyRef);

  /** Tenant ID resolved from route (/chat/external/:tenantId or /embed/:tenantId) */
  readonly tenantId = input<string | null>(null);

  /** Tenant name resolved from route query param or API validation */
  readonly tenantName = input<string | null>(null);

  /** Emits when session is created and ready to show chat */
  readonly sessionReady = output<{
    token: string;
    sessionId: string;
    contactName?: string;
    contactPhone?: string;
    protocol?: string;
  }>();

  // Form controls
  readonly nameControl = new FormControl<string>('', {
    nonNullable: true,
    validators: [Validators.required, Validators.minLength(3)],
  });
  readonly whatsappControl = new FormControl<string>('', {
    nonNullable: true,
    validators: [Validators.required],
  });

  // UI state
  readonly isLoading = signal(false);
  readonly errorMessage = signal<string | null>(null);

  // Form state derived from controls
  protected readonly formState = signal<PreChatFormState>({
    name: '',
    whatsapp: '',
    isValid: false,
    errors: {},
  });

  ngOnInit(): void {
    this.setupValidation();
  }

  private setupValidation(): void {
    const name$ = this.nameControl.valueChanges.pipe(startWith(this.nameControl.value ?? ''));
    const whatsapp$ = this.whatsappControl.valueChanges.pipe(
      startWith(this.whatsappControl.value ?? ''),
    );

    merge(name$, whatsapp$)
      .pipe(debounceTime(150), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.updateFormState();
      });
  }

  private updateFormState(): void {
    const name = this.nameControl.value ?? '';
    const whatsapp = this.whatsappControl.value ?? '';
    const errors: PreChatFormState['errors'] = {};

    if (this.nameControl.invalid && this.nameControl.touched) {
      errors.name = this.getNameError();
    }
    if (this.whatsappControl.invalid && this.whatsappControl.touched) {
      errors.whatsapp = 'Número de WhatsApp inválido';
    }

    this.formState.set({
      name,
      whatsapp,
      isValid: this.nameControl.valid && this.whatsappControl.valid,
      errors,
    });
  }

  private getNameError(): string {
    const errors = this.nameControl.errors;
    if (errors?.['required']) return 'Nome é obrigatório';
    if (errors?.['minlength']) return 'Nome deve ter pelo menos 3 caracteres';
    return 'Nome inválido';
  }

  /** Called when the form is submitted */
  onSubmit(): void {
    // Mark all fields as touched to show validation errors
    this.nameControl.markAsTouched();
    this.whatsappControl.markAsTouched();

    if (this.nameControl.invalid || this.whatsappControl.invalid) {
      return;
    }

    const name = this.nameControl.value ?? '';
    const whatsapp = this.whatsappControl.value ?? '';
    const tenantId = this.resolveTenantId();

    if (tenantId === '') {
      this.errorMessage.set('tenant_id é obrigatório');
      return;
    }

    this.startSession(tenantId, name, whatsapp);
  }

  private resolveTenantId(): string {
    const inputTenantId = this.tenantId();
    if (typeof inputTenantId === 'string' && inputTenantId.trim() !== '') {
      return inputTenantId.trim();
    }

    // Legacy fallback based on pathname
    const segments = window.location.pathname.split('/').filter(Boolean);

    // /chat/external/:tenantId
    if (segments[0] === 'chat' && segments[1] === 'external' && segments[2]) {
      return segments[2];
    }

    // /embed/:tenantId
    if (segments[0] === 'embed' && segments[1]) {
      return segments[1];
    }

    // Legacy route: /chat/:tenantSlug
    if (segments[0] === 'chat' && segments[1]) {
      return segments[1];
    }

    return '';
  }

  private startSession(tenantId: string, name: string, whatsapp: string): void {
    if (this.isLoading()) return;

    this.isLoading.set(true);
    this.errorMessage.set(null);

    this.webchatService.createSession(tenantId, name, whatsapp).subscribe({
      next: (response) => {
        this.webchatService.saveSession(response.token, response.sessionId, {
          contactName: response.contactName,
          contactPhone: response.contactPhone,
          protocol: response.protocol,
        });
        this.webchatService.connectWebSocket(response.token);
        this.isLoading.set(false);
        this.sessionReady.emit({
          token: response.token,
          sessionId: response.sessionId,
          contactName: response.contactName,
          contactPhone: response.contactPhone,
          protocol: response.protocol,
        });
      },
      error: (err) => {
        this.isLoading.set(false);
        const message =
          err?.error?.message ?? 'Não foi possível iniciar a conversa. Tente novamente.';
        this.errorMessage.set(message);
      },
    });
  }

  /** Track by index for @for loops */
  trackByIndex(index: number): number {
    return index;
  }
}
