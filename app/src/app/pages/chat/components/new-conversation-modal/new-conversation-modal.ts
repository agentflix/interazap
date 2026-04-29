import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { forkJoin, of } from 'rxjs';
import { catchError, map, switchMap } from 'rxjs/operators';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { toast } from 'ngx-sonner';
import { HttpClient } from '@angular/common/http';
import { ModalComponent } from 'src/app/shared/components/modal/modal';
import { type Contact } from 'src/app/core/models/contact.model';
import { ContactService } from 'src/app/core/services/contact.service';
import { CalledService } from 'src/app/core/services/called.service';
import { CalledMessageService } from 'src/app/core/services/called-message.service';
import { type Instance, InstanceService } from 'src/app/core/services/instance.service';
import { WindowVerificationService } from './services/window-verification.service';
import { TemplateSelectorComponent, type TemplateSelectedEvent } from '@shared/components/template-selector/template-selector';
import { ButtonComponent, LoadingButtonComponent } from 'src/app/shared/components/buttons';
import {
  SelectInputComponent,
  TextInputComponent,
  TextareaInputComponent,
  type SelectOption,
} from 'src/app/shared/components/inputs';
import { AfScrollAreaComponent } from 'src/app/shared/components/scroll-area/scroll-area';
import { environment } from '@env/environment';

const CONNECTED_STATUSES = new Set([
  'connected',
  'online',
  'ready',
  'open',
  'authorized',
  'authenticated',
  'authenticated_connected',
]);

const INSTANCE_SELECTION_STORAGE = 'chat:selectedInstanceId';

/**
 * Modal para iniciar um novo atendimento com um contato.
 *
 * Gerencia busca de contatos, seleção de instância WhatsApp e envio
 * da mensagem inicial ao criar ou reutilizar um ticket existente.
 *
 * @example
 * ```html
 * <app-new-conversation-modal #modal />
 * <button (click)="modal.open()">Novo</button>
 * ```
 */
@Component({
  selector: 'app-new-conversation-modal',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    ModalComponent,
    ButtonComponent,
    LoadingButtonComponent,
    SelectInputComponent,
    TextInputComponent,
    TextareaInputComponent,
    AfScrollAreaComponent,
    TemplateSelectorComponent,
  ],
  templateUrl: './new-conversation-modal.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NewConversationModalComponent {
  private readonly contactService = inject(ContactService);
  private readonly instanceService = inject(InstanceService);
  private readonly calledService = inject(CalledService);
  private readonly messageService = inject(CalledMessageService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly windowVerificationService = inject(WindowVerificationService);
  private readonly http = inject(HttpClient);

  /** Controla se o modal está aberto. */
  readonly isOpen = signal(false);

  /** Mensagem de erro no processo de início do atendimento. */
  readonly startError = signal<string | null>(null);

  /** Indica se o processo de criação está em andamento. */
  readonly isStarting = signal(false);

  /** Termo de busca de contatos (espelhado do FormControl). */
  readonly contactSearch = signal('');

  /** Lista de contatos encontrados. */
  readonly contacts = signal<Contact[]>([]);

  /** Indica se a requisição de contatos está em andamento. */
  readonly isLoadingContacts = signal(false);

  /** Lista de instâncias WhatsApp conectadas. */
  readonly instances = signal<Instance[]>([]);

  /** Indica se as instâncias estão sendo carregadas. */
  readonly isLoadingInstances = signal(false);

  /** Erro de carregamento de instâncias. */
  readonly instancesError = signal<string | null>(null);

  /** ID do contato selecionado. */
  readonly selectedContactId = signal<string>('');

  /** ID da instância selecionada. */
  readonly selectedInstanceId = signal<string>('');

  /** Mensagem inicial a ser enviada. */
  readonly startMessage = signal('');

  /** FormControl do campo busca de contato. */
  readonly contactSearchControl = new FormControl<string>('', { nonNullable: true });

  /** FormControl do select de instâncias. */
  readonly instanceControl = new FormControl<string>('', { nonNullable: true });

  /** FormControl da mensagem inicial. */
  readonly startMessageControl = new FormControl<string>('', { nonNullable: true });

  /** Modo de envio: 'freeText' ou 'template' */
  readonly sendMode = signal<'freeText' | 'template'>('freeText');

  /** Template selecionado para envio (quando sendMode='template') */
  readonly selectedTemplate = signal<TemplateSelectedEvent | null>(null);

  /** ID do canal Meta selecionado (para buscar templates) */
  readonly selectedChannelId = signal<string>('');

  /** Opções do select de instâncias. */
  readonly instanceOptions = computed<readonly SelectOption[]>(() =>
    this.instances().map((inst) => ({
      value: String(inst.id),
      label: inst.name ?? inst.phone ?? `Instância ${inst.id}`,
    })),
  );

  /** True se o termo de busca tem ao menos 3 caracteres. */
  readonly isContactSearchReady = computed(() => this.contactSearch().trim().length >= 3);

  /** True se não há instâncias disponíveis (após carga). */
  readonly showInstanceEmptyState = computed(
    () => !this.isLoadingInstances() && !this.instancesError() && this.instances().length === 0,
  );

  /** True se a seleção de instância é obrigatória (múltiplas disponíveis). */
  readonly requiresInstanceSelection = computed(
    () => this.instances().length > 1 && !this.selectedInstanceId(),
  );

  /** True se o select de instância deve ser exibido. */
  readonly shouldDisplayInstanceSelect = computed(() => this.instances().length > 1);

  /** True se deve mostrar modo template (Meta + fora da janela 24h) */
  readonly isTemplateMode = computed(() => this.sendMode() === 'template');

  /** Instância selecionada com provider info */
  readonly selectedInstance = computed(() => {
    const id = this.selectedInstanceId();
    return this.instances().find((inst) => String(inst.id) === id) ?? null;
  });

  /** True se o provider é Meta */
  readonly isMetaProvider = computed(() => this.selectedInstance()?.provider === 'meta');

  private searchTimeoutId: number | undefined;

  constructor() {
    this.contactSearchControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.onContactSearch(value ?? ''));

    this.instanceControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.selectInstance(value ?? ''));

    this.startMessageControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        this.startMessage.set(value);
      });
  }

  /**
   * Abre o modal e carrega as instâncias disponíveis.
   */
  open(): void {
    this.startError.set(null);
    this.contacts.set([]);
    this.isLoadingContacts.set(false);
    this.selectedContactId.set('');
    this.startMessage.set('');
    this.sendMode.set('freeText');
    this.selectedTemplate.set(null);
    this.contactSearchControl.setValue('', { emitEvent: false });
    this.startMessageControl.setValue('', { emitEvent: false });
    this.restorePersistedInstanceSelection();
    this.loadInstances();
    this.isOpen.set(true);
  }

  /**
   * Fecha o modal e limpa todo o estado do formulário.
   */
  close(): void {
    this.isOpen.set(false);
    this.startError.set(null);
    this.contactSearch.set('');
    this.contacts.set([]);
    this.isLoadingContacts.set(false);
    this.selectedContactId.set('');
    this.startMessage.set('');
    this.sendMode.set('freeText');
    this.selectedTemplate.set(null);
    this.contactSearchControl.setValue('', { emitEvent: false });
    this.instanceControl.setValue('', { emitEvent: false });
    this.startMessageControl.setValue('', { emitEvent: false });
    if (this.searchTimeoutId !== undefined) {
      window.clearTimeout(this.searchTimeoutId);
      this.searchTimeoutId = undefined;
    }
  }

  /**
   * Seleciona um contato da lista de resultados.
   * @param id ID do contato.
   */
  selectContact(id: string | number): void {
    this.selectedContactId.set(String(id));

    // If Meta provider is selected, check window status
    if (this.isMetaProvider()) {
      this.checkWindowStatus(String(id));
    }
  }

  /**
   * Manipula a seleção de um template.
   * @param event Evento com template selecionado.
   */
  handleTemplateSelected(event: TemplateSelectedEvent): void {
    this.selectedTemplate.set(event);
  }

  /**
   * Verifica o status da janela 24h para o contato.
   * Atualiza o modo de envio baseado no resultado.
   * @param contactId ID do contato.
   */
  private checkWindowStatus(contactId: string): void {
    this.windowVerificationService
      .checkStatus(contactId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((status) => {
        if (status.canSendFreeText) {
          this.sendMode.set('freeText');
        } else {
          this.sendMode.set('template');
          // Also set the channel ID for template loading
          const channelId = this.selectedChannelId();
          if (channelId) {
            // TemplateSelector will load templates via its own input
          }
        }
      });
  }

  /**
   * Inicia o atendimento: verifica ticket existente ou cria novo,
   * envia a mensagem inicial e navega para o ticket.
   */
  startChat(): void {
    const contactId = this.selectedContactId();
    const message = this.startMessage().trim();
    const firstInstance = this.instances()[0];
    const instanceId =
      this.selectedInstanceId() ||
      (firstInstance?.id !== undefined ? String(firstInstance.id) : '');
    const mode = this.sendMode();
    const template = this.selectedTemplate();
    const channelId = this.selectedChannelId();

    if (!contactId) {
      this.startError.set('Selecione um contato.');
      return;
    }

    if (!instanceId) {
      this.startError.set('Selecione uma instância conectada.');
      return;
    }

    // Validate based on mode
    if (mode === 'freeText' && !message) {
      this.startError.set('Digite uma mensagem inicial.');
      return;
    }

    if (mode === 'template' && !template) {
      this.startError.set('Selecione um template.');
      return;
    }

    this.startError.set(null);
    this.isStarting.set(true);

    // Helper to send message based on mode
    const sendMessage = (calledId: string) => {
      if (mode === 'template' && template) {
        // Send template via gateway API
        const payload = {
          contact_id: contactId,
          template_name: template.templateName,
          parameters: template.parameters,
        };
        return this.http
          .post(`${environment.gateway.url}/channels/${channelId}/send-template`, payload)
          .pipe(map(() => ({ calledId, reused: false })));
      } else {
        // Send free text via message service
        return this.messageService.send(calledId, message).pipe(
          map(() => ({ calledId, reused: false })),
        );
      }
    };

    forkJoin({
      open: this.calledService.list({ contact_id: contactId, status: 'open', per_page: 1 }),
      pending: this.calledService.list({ contact_id: contactId, status: 'pending', per_page: 1 }),
      inProgress: this.calledService.list({
        contact_id: contactId,
        status: 'in_progress',
        per_page: 1,
      }),
    })
      .pipe(
        map(({ open, pending, inProgress }) => {
          const existing = open.data?.[0] ?? pending.data?.[0] ?? inProgress.data?.[0];
          return existing ? { calledId: String(existing.id), reused: true } : null;
        }),
        switchMap((existing) => {
          if (existing !== null) return of(existing);

          return this.calledService
            .create({ contact_id: contactId, channel: 'whatsapp', instance_id: instanceId })
            .pipe(
              switchMap((created) => {
                const newId = String(created.data.id);
                return sendMessage(newId).pipe(
                  switchMap((result) =>
                    this.calledService.open(result.calledId).pipe(
                      catchError(() => of(null)),
                      map(() => result),
                    ),
                  ),
                );
              }),
            );
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (result) => {
          this.isStarting.set(false);
          this.close();
          if (result.reused) {
            toast.info('Chamado já existente. Abrindo atendimento.');
          } else {
            toast.success('Atendimento iniciado com sucesso.');
          }
          void this.router.navigate(['/chat', result.calledId]);
        },
        error: () => {
          this.isStarting.set(false);
          this.startError.set('Não foi possível iniciar o atendimento. Tente novamente.');
        },
      });
  }

  /** Processa a busca de contato com debounce de 250ms. */
  private onContactSearch(value: string): void {
    this.contactSearch.set(value);
    if (this.searchTimeoutId !== undefined) {
      window.clearTimeout(this.searchTimeoutId);
    }
    const trimmed = value.trim();
    if (trimmed.length < 3) {
      this.contacts.set([]);
      this.isLoadingContacts.set(false);
      return;
    }
    this.isLoadingContacts.set(true);
    this.searchTimeoutId = window.setTimeout(() => {
      this.loadContacts();
    }, 250);
  }

  /** Carrega contatos que correspondem ao termo de busca. */
  private loadContacts(): void {
    const term = this.contactSearch().trim();
    if (term.length < 3) {
      this.contacts.set([]);
      this.isLoadingContacts.set(false);
      return;
    }
    this.contactService
      .list({ search: term, is_active: true, per_page: 10 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.contacts.set(response.data ?? []);
          this.isLoadingContacts.set(false);
        },
        error: () => {
          this.contacts.set([]);
          this.isLoadingContacts.set(false);
        },
      });
  }

  /** Carrega instâncias WhatsApp conectadas. */
  private loadInstances(): void {
    this.isLoadingInstances.set(true);
    this.instancesError.set(null);
    this.instanceService
      .list({ is_active: true, per_page: 50 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const connected = (response.data ?? []).filter((inst) => this.isInstanceConnected(inst));
          this.instances.set(connected);
          this.syncSelectedInstance(connected);
          this.isLoadingInstances.set(false);
        },
        error: () => {
          this.instances.set([]);
          this.instancesError.set('Não foi possível carregar as conexões de WhatsApp.');
          this.isLoadingInstances.set(false);
        },
      });
  }

  /** Seleciona e persiste a instância escolhida. */
  private selectInstance(value: string): void {
    this.selectedInstanceId.set(value);

    // Find the selected instance to get its integration_id (channel id for Meta)
    const instance = this.instances().find((inst) => String(inst.id) === value);
    if (instance?.integration_id) {
      this.selectedChannelId.set(String(instance.integration_id));
    }

    try {
      localStorage.setItem(INSTANCE_SELECTION_STORAGE, value);
    } catch {
      // ignore storage errors
    }
    if (this.instanceControl.value !== value) {
      this.instanceControl.setValue(value, { emitEvent: false });
    }

    // If Meta provider and contact is already selected, recheck window status
    if (this.isMetaProvider() && this.selectedContactId()) {
      this.checkWindowStatus(this.selectedContactId());
    }
  }

  /** Restaura a instância persistida no localStorage. */
  private restorePersistedInstanceSelection(): void {
    try {
      const stored = localStorage.getItem(INSTANCE_SELECTION_STORAGE);
      if (stored) {
        this.selectedInstanceId.set(stored);
      }
    } catch {
      // ignore storage errors
    }
  }

  /**
   * Sincroniza a instância selecionada com a lista disponível.
   * Seleciona a única disponível se houver apenas uma.
   */
  private syncSelectedInstance(items: Instance[]): void {
    const current = this.selectedInstanceId();
    const stillAvailable = current ? items.some((inst) => String(inst.id) === current) : false;

    if (!stillAvailable) {
      if (items.length === 1 && items[0] !== undefined) {
        this.selectInstance(String(items[0].id));
      } else {
        this.selectedInstanceId.set('');
        this.instanceControl.setValue('', { emitEvent: false });
      }
    } else {
      this.instanceControl.setValue(current, { emitEvent: false });
    }
  }

  /** Verifica se uma instância está conectada e ativa. */
  private isInstanceConnected(inst: Instance): boolean {
    const status = inst.connection_status ?? inst.status ?? '';
    return CONNECTED_STATUSES.has(String(status).toLowerCase());
  }
}
