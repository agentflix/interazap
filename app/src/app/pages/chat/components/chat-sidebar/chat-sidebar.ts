import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  ViewChild,
  computed,
  effect,
  inject,
  signal,
} from '@angular/core';
import { FormControl } from '@angular/forms';
import { Router } from '@angular/router';
import { forkJoin, of } from 'rxjs';
import { catchError, map, switchMap } from 'rxjs/operators';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { toast } from 'ngx-sonner';
import { ChatList } from '../chat-list/chat-list';
import { ModalComponent } from 'src/app/shared/components/modal/modal';
import { type Contact } from 'src/app/core/models/contact.model';
import { ContactService } from 'src/app/core/services/contact.service';
import { CalledService } from 'src/app/core/services/called.service';
import { CalledMessageService } from 'src/app/core/services/called-message.service';
import { type Instance, InstanceService } from 'src/app/core/services/instance.service';
import { ChatStartService } from 'src/app/core/services/chat-start.service';
import { ChatSoundService } from 'src/app/core/services/chat-sound.service';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { RealtimeService } from 'src/app/core/services/realtime.service';
import { tocarNotificacao } from 'src/app/shared/utils/notifications/chat-audio';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import {
  ButtonComponent,
  IconButtonComponent,
  LoadingButtonComponent,
} from 'src/app/shared/components/buttons';
import {
  SelectInputComponent,
  TextInputComponent,
  TextareaInputComponent,
  type SelectOption,
} from 'src/app/shared/components/inputs';
import { AfScrollAreaComponent } from 'src/app/shared/components/scroll-area/scroll-area';

const CHAT_NOTIFICATION_SOUND_URL = '/assets/audio/chat-notification.mp3';
const MESSAGE_RECEIVED_EVENT = 'message.received';
const NOTIFICATION_COOLDOWN_MS = 600;
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

interface MessageReceivedEvent {
  data?: {
    ticket_id?: string;
    direction?: string | null;
  };
}

/**
 * Componente da barra lateral do chat.
 *
 * Responsável por gerenciar a listagem de atendimentos, busca de contatos,
 * seleção de instâncias WhatsApp e inicialização de novas conversas.
 *
 * @class ChatSidebar
 * @description Barra lateral de navegação e ações do chat.
 */
@Component({
  selector: 'app-chat-sidebar',
  standalone: true,
  imports: [
    ChatList,
    ModalComponent,
    ButtonComponent,
    IconButtonComponent,
    LoadingButtonComponent,
    SelectInputComponent,
    TextInputComponent,
    TextareaInputComponent,
    AfScrollAreaComponent,
  ],
  templateUrl: './chat-sidebar.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    class: 'block h-full',
  },
})
export class ChatSidebar {
  /**
   * Referência ao componente de listagem de atendimentos.
   * @private
   */
  @ViewChild(ChatList) private readonly chatList?: ChatList;

  private readonly contactService = inject(ContactService);
  private readonly instanceService = inject(InstanceService);
  private readonly calledService = inject(CalledService);
  private readonly messageService = inject(CalledMessageService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly chatStart = inject(ChatStartService);
  private readonly chatSound = inject(ChatSoundService);
  private readonly chatRefresh = inject(ChatRefreshService);
  private readonly realtime = inject(RealtimeService);
  private readonly authStore = inject(AuthStoreService);

  /**
   * Timestamp da última notificação sonora disparada para evitar repetições rápidas.
   * @private
   */
  private lastNotificationAt = 0;

  /**
   * Termo de busca para filtrar a lista de atendimentos.
   * @type {WritableSignal<string>}
   */
  readonly searchTerm = signal('');

  /** Form control do campo de busca */
  readonly searchQueryControl = new FormControl<string>('', { nonNullable: true });

  /**
   * Controla se o modal de início de conversa está aberto.
   * @type {WritableSignal<boolean>}
   */
  readonly isStartModalOpen = signal(false);

  /**
   * Mensagem de erro ao tentar iniciar uma conversa.
   * @type {WritableSignal<string | null>}
   */
  readonly startError = signal<string | null>(null);

  /**
   * Indica se um processo de criação de atendimento está em curso.
   * @type {WritableSignal<boolean>}
   */
  readonly isStarting = signal(false);

  /**
   * Termo de busca utilizado para localizar contatos no banco de dados.
   * @type {WritableSignal<string>}
   */
  readonly contactSearch = signal('');

  /** Form control do campo de contato */
  readonly contactSearchControl = new FormControl<string>('', { nonNullable: true });

  /**
   * Lista de contatos resultantes da busca.
   * @type {WritableSignal<Contact[]>}
   */
  readonly contacts = signal<Contact[]>([]);

  /**
   * Indica se a busca por contatos está em processamento.
   * @type {WritableSignal<boolean>}
   */
  readonly isLoadingContacts = signal(false);

  /**
   * Verifica se o termo de busca de contato tem o tamanho mínimo para disparo.
   * @type {Signal<boolean>}
   */
  readonly isContactSearchReady = computed(() => this.contactSearch().trim().length >= 3);

  /**
   * Estado de silenciamento das notificações sonoras.
   * @type {Signal<boolean>}
   */
  readonly isSoundMuted = this.chatSound.mutedState;

  /**
   * Lista de instâncias WhatsApp conectadas e disponíveis.
   * @type {WritableSignal<Instance[]>}
   */
  readonly instances = signal<Instance[]>([]);

  /**
   * Indica se as instâncias estão sendo carregadas.
   * @type {WritableSignal<boolean>}
   */
  readonly isLoadingInstances = signal(false);

  /**
   * Mensagem de erro no carregamento das instâncias.
   * @type {WritableSignal<string | null>}
   */
  readonly instancesError = signal<string | null>(null);

  /**
   * ID do contato selecionado para iniciar nova conversa.
   * @type {WritableSignal<string>}
   */
  readonly selectedContactId = signal<string>('');

  /**
   * ID da instância WhatsApp selecionada para o envio.
   * @type {WritableSignal<string>}
   */
  readonly selectedInstanceId = signal<string>('');

  /** Form control do select de instancias */
  readonly instanceControl = new FormControl<string>('', { nonNullable: true });

  /** Opcoes para select de instancias */
  readonly instanceOptions = computed<readonly SelectOption[]>(() =>
    this.instances().map((instance) => ({
      value: String(instance.id),
      label: instance.name || instance.phone || `Instancia ${instance.id}`,
    })),
  );

  /**
   * Mensagem inicial a ser enviada ao contato.
   * @type {WritableSignal<string>}
   */
  readonly startMessage = signal('');

  /** Form control da mensagem inicial */
  readonly startMessageControl = new FormControl<string>('', { nonNullable: true });

  /**
   * Controla se o modal de busca global está aberto.
   * @type {WritableSignal<boolean>}
   */
  readonly isSearchModalOpen = signal(false);

  /**
   * Consulta de busca digitada no modal de pesquisa.
   * @type {WritableSignal<string>}
   */
  readonly searchQuery = signal('');

  /**
   * Verifica se deve exibir o estado vazio de instâncias.
   * @type {Signal<boolean>}
   */
  readonly showInstanceEmptyState = computed(
    () => !this.isLoadingInstances() && !this.instancesError() && this.instances().length === 0,
  );

  /**
   * Verifica se é obrigatório selecionar uma instância (quando há múltiplas).
   * @type {Signal<boolean>}
   */
  readonly requiresInstanceSelection = computed(
    () => this.instances().length > 1 && !this.selectedInstanceId(),
  );

  /**
   * Verifica se o componente de seleção de instância deve ser exibido.
   * @type {Signal<boolean>}
   */
  readonly shouldDisplayInstanceSelect = computed(() => this.instances().length > 1);

  /**
   * Identificador do timeout para a função de busca de contatos.
   * @private
   */
  private searchTimeoutId: number | undefined;

  /**
   * Inicializa o componente e configura efeitos reativos para abertura de modais e recarregamento.
   */
  constructor() {
    effect(() => {
      if (this.chatStart.requestOpen()) {
        this.openStartModal();
        this.chatStart.clear();
      }
    });

    effect(() => {
      this.chatRefresh.refreshTick();
      this.reloadCalleds();
    });

    // Atualização local quando um ticket é marcado como lido (sem reload completo)
    effect(() => {
      const ticketId = this.chatRefresh.readTicketId();
      if (ticketId) {
        this.chatList?.markTicketAsRead(ticketId);
      }
    });

    this.contactSearchControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.onContactSearch(value ?? ''));

    this.instanceControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.selectInstance(value ?? ''));

    this.startMessageControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.onMessageInput(value ?? ''));

    this.searchQueryControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.onSearchQueryInput(value ?? ''));

    this.setupRealtimeListeners();
  }

  /**
   * Processar a alteração no campo de busca de atendimentos.
   * @param value Termo de busca digitado.
   */
  onSearchInput(value: string): void {
    this.searchTerm.set(value);
  }

  /**
   * Abrir o modal de busca avançada.
   */
  openSearchModal(): void {
    this.isSearchModalOpen.set(true);
    this.searchQuery.set(this.searchTerm());
    this.searchQueryControl.setValue(this.searchTerm(), { emitEvent: false });
  }

  /**
   * Fechar o modal de busca avançada.
   */
  closeSearchModal(): void {
    this.isSearchModalOpen.set(false);
    this.searchQuery.set(this.searchTerm());
    this.searchQueryControl.setValue(this.searchTerm(), { emitEvent: false });
  }

  /**
   * Processar a entrada de texto no campo de consulta do modal de busca.
   * @param value Valor da consulta.
   */
  onSearchQueryInput(value: string): void {
    const query = value ?? '';
    this.searchQuery.set(query);
    this.searchTerm.set(query.trim());
  }

  /**
   * Limpar todos os filtros de busca aplicados.
   */
  clearSearch(): void {
    this.searchQuery.set('');
    this.searchTerm.set('');
    this.searchQueryControl.setValue('', { emitEvent: false });
  }

  /**
   * Preparar e abrir o modal para iniciar uma nova conversa.
   */
  openStartModal(): void {
    this.isStartModalOpen.set(true);
    this.startError.set(null);
    this.restorePersistedInstanceSelection();
    this.loadInstances();
    this.contacts.set([]);
    this.isLoadingContacts.set(false);
    this.contactSearchControl.setValue(this.contactSearch(), { emitEvent: false });
    this.instanceControl.setValue(this.selectedInstanceId(), { emitEvent: false });
    this.startMessageControl.setValue(this.startMessage(), { emitEvent: false });
  }

  /**
   * Encerrar e limpar os dados do modal de início de conversa.
   */
  closeStartModal(): void {
    this.isStartModalOpen.set(false);
    this.startError.set(null);
    this.contactSearch.set('');
    this.contacts.set([]);
    this.isLoadingContacts.set(false);
    this.selectedContactId.set('');
    this.startMessage.set('');
    this.contactSearchControl.setValue('', { emitEvent: false });
    this.instanceControl.setValue('', { emitEvent: false });
    this.startMessageControl.setValue('', { emitEvent: false });
    if (this.searchTimeoutId) {
      window.clearTimeout(this.searchTimeoutId);
      this.searchTimeoutId = undefined;
    }
  }

  /**
   * Alternar o estado de silenciamento das notificações sonoras.
   */
  toggleSound(): void {
    this.chatSound.toggle();
  }

  /**
   * Solicitar o recarregamento da lista de atendimentos.
   */
  reloadCalleds(): void {
    this.chatList?.reload();
  }

  /**
   * Processar a busca de contatos com debounce.
   * @param value Termo de busca do contato.
   */
  onContactSearch(value: string): void {
    this.contactSearch.set(value);
    if (this.searchTimeoutId) {
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

  /**
   * Selecionar um contato da lista de resultados.
   * @param id Identificador único do contato.
   */
  selectContact(id: string | number): void {
    this.selectedContactId.set(String(id));
  }

  /**
   * Selecionar uma instância específica para iniciar a conversa.
   * Persiste a escolha localmente para futuros usos.
   * @param value ID da instância selecionada.
   */
  selectInstance(value: string): void {
    this.selectedInstanceId.set(value);
    this.persistSelectedInstance(value);
    if (this.instanceControl.value !== value) {
      this.instanceControl.setValue(value, { emitEvent: false });
    }
  }

  /**
   * Atualizar a mensagem inicial que será enviada ao contato.
   * @param value Texto da mensagem.
   */
  onMessageInput(value: string): void {
    this.startMessage.set(value);
    if (this.startMessageControl.value !== value) {
      this.startMessageControl.setValue(value, { emitEvent: false });
    }
  }

  /**
   * Carregar contatos do servidor que correspondam ao termo de busca.
   * @private
   */
  private loadContacts(): void {
    const term = this.contactSearch().trim();
    if (term.length < 3) {
      this.contacts.set([]);
      this.isLoadingContacts.set(false);
      return;
    }
    this.isLoadingContacts.set(true);
    this.contactService
      .list({
        search: term,
        is_active: true,
        per_page: 10,
      })
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

  /**
   * Carrega a lista de instâncias WhatsApp disponíveis, filtra as conectadas e sincroniza a seleção atual.
   */
  loadInstances(): void {
    this.isLoadingInstances.set(true);
    this.instancesError.set(null);
    this.instanceService
      .list({
        is_active: true,
        per_page: 50,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const items = this.getConnectedInstances(response.data ?? []);
          this.instances.set(items);
          this.syncSelectedInstance(items);
          this.isLoadingInstances.set(false);
        },
        error: () => {
          this.instances.set([]);
          this.instancesError.set('Não foi possível carregar as conexões de WhatsApp.');
          this.isLoadingInstances.set(false);
        },
      });
  }

  /**
   * Executar a lógica de iniciar um novo chat.
   * Verifica se já existe um atendimento aberto para o contato ou cria um novo,
   * enviando a mensagem inicial caso necessário.
   */
  startChat(): void {
    const contactId = this.selectedContactId();
    const message = this.startMessage().trim();
    const instanceId =
      this.selectedInstanceId() || (this.instances()[0]?.id ? String(this.instances()[0]?.id) : '');

    if (!contactId) {
      this.startError.set('Selecione um contato.');
      return;
    }

    if (!instanceId) {
      this.startError.set('Selecione uma instância conectada.');
      return;
    }

    if (!message) {
      this.startError.set('Digite uma mensagem inicial.');
      return;
    }

    this.startError.set(null);
    this.isStarting.set(true);

    forkJoin({
      open: this.calledService.list({
        contact_id: contactId,
        status: 'open',
        per_page: 1,
      }),
      pending: this.calledService.list({
        contact_id: contactId,
        status: 'pending',
        per_page: 1,
      }),
      inProgress: this.calledService.list({
        contact_id: contactId,
        status: 'in_progress',
        per_page: 1,
      }),
    })
      .pipe(
        map(({ open, pending, inProgress }) => {
          const existing = open.data?.[0] || pending.data?.[0] || inProgress.data?.[0];
          return existing ? { calledId: String(existing.id), reused: true } : null;
        }),
        switchMap((existing) => {
          if (existing) return of(existing);
          return this.calledService
            .create({
              contact_id: contactId,
              channel: 'whatsapp',
              instance_id: instanceId,
            })
            .pipe(
              switchMap((created) => {
                const newId = String(created.data.id);
                return this.messageService.send(newId, message).pipe(
                  switchMap(() => this.calledService.open(newId).pipe(catchError(() => of(null)))),
                  map(() => ({ calledId: newId, reused: false })),
                );
              }),
            );
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (result) => {
          this.isStarting.set(false);
          this.closeStartModal();
          if (result.reused) {
            toast.info('Chamado já existente. Abrindo atendimento.');
          } else {
            toast.success('Atendimento iniciado.');
          }
          void this.router.navigate(['/chat', result.calledId]);
        },
        error: () => {
          this.isStarting.set(false);
          this.startError.set('Não foi possível iniciar o atendimento.');
        },
      });
  }

  /**
   * Configurar escutas de eventos em tempo real via WebSocket.
   * Ativa a conexão e define o tratamento para mensagens recebidas.
   * @private
   */
  private setupRealtimeListeners(): void {
    this.realtime.connect();
    this.realtime
      .on<MessageReceivedEvent>(MESSAGE_RECEIVED_EVENT)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((event) => this.handleIncomingMessage(event));
  }

  /**
   * Tratar o recebimento de uma nova mensagem via WebSocket.
   * Dispara notificações sonoras e solicita atualização da lista.
   * @param event Dados do evento de mensagem recebida.
   * @private
   */
  private handleIncomingMessage(event: MessageReceivedEvent | null): void {
    const payload = event?.data;
    if (!payload) return;

    const direction = (payload.direction ?? '').toLowerCase();
    if (direction && direction !== 'inbound') {
      return;
    }

    const now = Date.now();
    if (now - this.lastNotificationAt < NOTIFICATION_COOLDOWN_MS) {
      return;
    }

    this.lastNotificationAt = now;
    void tocarNotificacao(CHAT_NOTIFICATION_SOUND_URL);
    this.chatRefresh.request();
  }

  /**
   * Filtra a lista de instâncias retornando apenas aquelas que estão com status de conectado.
   * @param items Lista total de instâncias.
   * @returns {Instance[]} Lista de instâncias conectadas.
   * @private
   */
  private getConnectedInstances(items: Instance[]): Instance[] {
    return items.filter((instance) => this.isInstanceConnected(instance));
  }

  /**
   * Verifica se uma instância específica está conectada.
   * Analisa propriedades booleanas, objetos de configurações aninhados e rótulos de status.
   *
   * @param instance Objeto da instância WhatsApp.
   * @returns {boolean} True se estiver operacional e conectada.
   * @private
   */
  private isInstanceConnected(instance: Instance): boolean {
    if (typeof instance.is_connected === 'boolean') {
      return instance.is_connected;
    }

    const settings = (instance as { settings?: unknown }).settings as
      | { last_connection?: { connected?: boolean; logged_in?: boolean } }
      | undefined;
    const lastConnection = settings?.last_connection;
    if (lastConnection?.connected === true || lastConnection?.logged_in === true) {
      return true;
    }

    const status = String(instance.connection_status ?? instance.status ?? '')
      .trim()
      .toLowerCase();
    if (!status) {
      return false;
    }
    return CONNECTED_STATUSES.has(status);
  }

  /**
   * Sincroniza a instância selecionada no sinal com a lista de instâncias disponíveis e o ID persistido.
   * @param instances Lista de instâncias conectadas.
   * @private
   */
  private syncSelectedInstance(instances: Instance[]): void {
    if (instances.length === 0) {
      this.selectedInstanceId.set('');
      this.persistSelectedInstance('');
      return;
    }

    const persisted = this.readPersistedInstanceId();
    const match = persisted ? instances.find((item) => String(item.id) === persisted) : undefined;
    if (match) {
      this.selectedInstanceId.set(String(match.id));
      return;
    }

    if (instances.length === 1) {
      const onlyId = String(instances[0].id);
      this.selectedInstanceId.set(onlyId);
      this.persistSelectedInstance(onlyId);
      return;
    }

    this.selectedInstanceId.set('');
    this.persistSelectedInstance('');
  }

  /**
   * Recupera a seleção de instância do armazenamento local e atualiza o sinal reativo.
   * @private
   */
  private restorePersistedInstanceSelection(): void {
    const persisted = this.readPersistedInstanceId();
    this.selectedInstanceId.set(persisted);
  }

  /**
   * Lê o ID da instância persistida no armazenamento local utilizando uma chave baseada no usuário/tenant.
   * @returns {string} ID da instância ou string vazia.
   * @private
   */
  private readPersistedInstanceId(): string {
    const storage = this.getLocalStorage();
    const key = this.getInstanceStorageKey();
    if (!storage || !key) {
      return '';
    }
    try {
      return storage.getItem(key) ?? '';
    } catch {
      return '';
    }
  }

  /**
   * Persiste o ID da instância selecionada no armazenamento local (localStorage).
   * @param value ID da instância a ser persistida.
   * @private
   */
  private persistSelectedInstance(value: string): void {
    const storage = this.getLocalStorage();
    const key = this.getInstanceStorageKey();
    if (!storage || !key) {
      return;
    }
    try {
      if (value) {
        storage.setItem(key, value);
      } else {
        storage.removeItem(key);
      }
    } catch {
      // ignore storage errors silently
    }
  }

  /**
   * Obtém a referência ao LocalStorage de forma segura, verificando o ambiente de execução.
   * @returns {Storage | null}
   * @private
   */
  private getLocalStorage(): Storage | null {
    if (typeof window === 'undefined') {
      return null;
    }
    try {
      return window.localStorage;
    } catch {
      return null;
    }
  }

  /**
   * Gera uma chave única de armazenamento para a instância baseada no ID da empresa e do usuário.
   * @returns {string | null} Chave formatada ou null se o usuário não estiver autenticado.
   * @private
   */
  private getInstanceStorageKey(): string | null {
    const user = this.authStore.user();
    if (!user) {
      return null;
    }
    const tenantId = user.company_id ?? 'tenant';
    const userId = user.id ?? 'user';
    return `${INSTANCE_SELECTION_STORAGE}:${tenantId}:${userId}`;
  }
}
