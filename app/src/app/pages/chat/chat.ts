import {
  ChangeDetectionStrategy,
  Component,
  ViewChild,
  signal,
  computed,
  effect,
  inject,
  type OnInit,
  type OnDestroy,
  DestroyRef,
  type ElementRef,
  HostListener,
  viewChild,
} from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, NavigationEnd, Router } from '@angular/router';
import { debounceTime } from 'rxjs/operators';
import { filter, startWith } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NgIcon, provideIcons } from '@ng-icons/core';
import {
  lucideArrowRightLeft,
  lucideBot,
  lucideCheck,
  lucideClock3,
  lucideFile,
  lucideFilter,
  lucideHandshake,
  lucideImage,
  lucideInbox,
  lucideLogOut,
  lucideMapPin,
  lucideMessagesSquare,
  lucideMic,
  lucidePaperclip,
  lucidePause,
  lucidePlay,
  lucidePlus,
  lucideRefreshCw,
  lucideSend,
  lucideSmile,
  lucideTrash2,
  lucideUploadCloud,
  lucideUser,
  lucideUsers,
  lucideVideo,
  lucideVolume2,
  lucideVolumeX,
  lucideX,
} from '@ng-icons/lucide';
import { ButtonComponent } from 'src/app/shared/components/buttons';
import { TextInputComponent } from 'src/app/shared/components/inputs';
import { ModalComponent } from 'src/app/shared/components/modal/modal';
import { ConfirmModalComponent } from 'src/app/shared/components/confirm-modal/confirm-modal';
import { type AfTabItem } from 'src/app/shared/components/tabs/tabs';
import { AppShellService } from 'src/app/core/services/app-shell.service';
import {
  CalledService,
  type Called,
  type CalledCounts,
  type CalledStatus,
  type CalledContactSummary,
  type CalledSentiment,
} from 'src/app/core/services/called.service';
import {
  CalledMessageService,
  type CalledMessage,
} from 'src/app/core/services/called-message.service';
import { NewConversationModalComponent } from './components/new-conversation-modal/new-conversation-modal';
import { ChatTransferModalComponent } from './components/chat-transfer-modal/chat-transfer-modal';
import { ChatSoundService } from 'src/app/core/services/chat-sound.service';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { ChatRealtimeListenerService } from './services/chat-realtime-listener.service';
import { ChatTicketTransferService } from './services/chat-ticket-transfer.service';
import { ChatTicketCloseService } from './services/chat-ticket-close.service';
import { ChatRecordingDispatcher } from './services/chat-recording-dispatcher.service';
import {
  ChatMediaBatchService,
  type MediaBatchEvent,
} from 'src/app/core/services/chat-media-batch.service';
import { ChatRecorderService } from './services/chat-recorder.service';
import { ChatTicketListService } from './services/chat-ticket-list.service';
import { ChatStore } from './chat.store';
import { MediaPreviewComponent } from './components/media-preview/media-preview';
import {
  type MediaPreviewItem,
  type MediaPreviewType,
} from 'src/app/shared/models/media-preview.model';
import { toast } from 'ngx-sonner';
import { ChatListComponent } from './components/chat-list-component/chat-list-component';
import { ChatConversationComponent } from './components/chat-conversation-component/chat-conversation-component';
import { ChatSidebarComponent } from './components/chat-sidebar-component/chat-sidebar-component';
import { type Contact } from 'src/app/core/models/contact.model';
import { ChatMessageCacheService } from 'src/app/core/services/chat-message-cache.service';
import { NativeBridgeService } from 'src/app/core/services/platform/native-bridge.service';
import { PlatformService } from 'src/app/core/services/platform/platform.service';
import { MessageSendService } from './services/message-send.service';

@Component({
  selector: 'app-chat',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    NgIcon,
    ButtonComponent,
    TextInputComponent,
    ModalComponent,
    ConfirmModalComponent,
    NewConversationModalComponent,
    ChatTransferModalComponent,
    MediaPreviewComponent,
    ChatListComponent,
    ChatConversationComponent,
    ChatSidebarComponent,
  ],
  templateUrl: './chat.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  viewProviders: [
    provideIcons({
      lucideArrowRightLeft,
      lucideBot,
      lucideCheck,
      lucideClock3,
      lucideFile,
      lucideFilter,
      lucideHandshake,
      lucideImage,
      lucideInbox,
      lucideLogOut,
      lucideMapPin,
      lucideMessagesSquare,
      lucideMic,
      lucidePaperclip,
      lucidePause,
      lucidePlay,
      lucidePlus,
      lucideRefreshCw,
      lucideSend,
      lucideSmile,
      lucideTrash2,
      lucideUploadCloud,
      lucideUser,
      lucideUsers,
      lucideVideo,
      lucideVolume2,
      lucideVolumeX,
      lucideX,
    }),
  ],
  host: {
    class: 'block h-full',
  },
})
export class Chat implements OnInit, OnDestroy {
  @ViewChild(NewConversationModalComponent)
  private readonly newConversationModal?: NewConversationModalComponent;

  private readonly appShell = inject(AppShellService);
  private readonly calledService = inject(CalledService);
  private readonly calledMessageService = inject(CalledMessageService);
  private readonly mediaBatchService = inject(ChatMediaBatchService);
  private readonly chatSound = inject(ChatSoundService);
  private readonly chatRefresh = inject(ChatRefreshService);
  private readonly realtimeListener = inject(ChatRealtimeListenerService);
  private readonly ticketTransfer = inject(ChatTicketTransferService);
  private readonly ticketClose = inject(ChatTicketCloseService);
  private readonly recordingDispatcher = inject(ChatRecordingDispatcher);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);
  private readonly cacheService = inject(ChatMessageCacheService);
  private readonly chatRecorder = inject(ChatRecorderService);
  private readonly ticketList = inject(ChatTicketListService);
  private readonly chatStore = inject(ChatStore);
  private readonly nativeBridge = inject(NativeBridgeService);
  private readonly platform = inject(PlatformService);
  private readonly messageSend = inject(MessageSendService);

  readonly activeTab = signal<'chat' | 'contact' | 'negotiation'>('chat');
  readonly ticketFilter = signal<'pending' | 'open' | 'all'>('pending');
  readonly emergencyFilter = signal<CalledSentiment | null>(null);
  readonly isEmergencyMenuOpen = signal(false);
  readonly isSearchModalOpen = signal(false);
  readonly searchTerm = signal('');
  readonly tickets = this.ticketList.tickets;
  readonly counts = this.ticketList.counts;
  readonly loadingTickets = this.ticketList.loadingTickets;
  readonly isRefreshing = signal(false);
  /** Alias for ChatStore.selectedCalledId - source of truth for selected ticket ID. */
  readonly selectedTicketId = this.chatStore.selectedCalledId;
  readonly isStartingTicket = signal(false);
  readonly isSendingMessage = signal(false);
  /** Estado do fluxo de fechamento (delegado ao ChatTicketCloseService). */
  readonly isClosingTicket = this.ticketClose.isClosing;
  readonly isCloseTicketConfirmOpen = this.ticketClose.isConfirmOpen;
  /** Estado do fluxo de transferência (delegado ao ChatTicketTransferService). */
  readonly isTransferModalOpen = this.ticketTransfer.isModalOpen;
  readonly isTransferLoading = this.ticketTransfer.isLoading;
  readonly transferError = this.ticketTransfer.error;

  /** Estado de silenciamento do som das notificações. */
  readonly isSoundMuted = this.chatSound.mutedState;

  /** FormControl do campo de pesquisa no modal. */
  readonly searchQueryControl = new FormControl<string>('', { nonNullable: true });

  /** FormControl da mensagem enviada no footer da conversa. */
  readonly messageControl = new FormControl<string>('', { nonNullable: true });

  // ── Audio recording ────────────────────────────────────────────────────────
  /** Estado de gravação (delegado ao ChatRecorderService). */
  readonly recordingState = this.chatRecorder.state;
  readonly recordingDurationSeconds = computed(() =>
    Math.floor(this.chatRecorder.duration() / 1000),
  );
  /** Estado de envio de áudio (delegado ao ChatRecordingDispatcher). */
  readonly isSendingAudio = this.recordingDispatcher.isSending;

  // ── File upload / attachment ──────────────────────────────────────────────
  /** Referência ao input de arquivo oculto. */
  private readonly fileInputRef = viewChild<ElementRef<HTMLInputElement>>('fileInput');

  /** Referência ao textarea de mensagem (WhatsApp-style). */
  private readonly messageTextareaRef =
    viewChild<ElementRef<HTMLTextAreaElement>>('messageTextarea');

  /** Referência ao wrapper da conversa para operar mensagens otimistas. */
  @ViewChild(ChatConversationComponent)
  private readonly conversationRef?: ChatConversationComponent;

  /** Whether the attachment dropdown is open. */
  readonly isAttachmentMenuOpen = signal(false);
  readonly isMobile = this.platform.isMobile;
  readonly attachmentErrorMessage = signal<string | null>(null);
  readonly pendingQueueCount = computed(() =>
    this.messageSend.pendingCountForTicket(this.selectedTicketId()),
  );
  readonly isQueueFlushing = this.messageSend.isFlushing;

  /** Whether the drag-over visual state is active. */
  readonly isDragOver = signal(false);

  /** Whether the media preview modal is open. */
  readonly isMediaPreviewOpen = signal(false);

  /** Items staged for upload in the media preview modal. */
  readonly mediaPreviewItems = signal<MediaPreviewItem[]>([]);

  /** Currently selected item ID in the preview. */
  readonly mediaPreviewSelectedId = signal<string | null>(null);

  /** Global upload progress (0-1). */
  readonly mediaUploadProgress = signal(0);

  /** Whether a batch upload is running right now. */
  readonly isMediaSending = signal(false);

  /** Accepted MIME types per attachment type. */
  private readonly mimeTypesByType: Record<string, string> = {
    document: '.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.ppt,.pptx,.odt,.ods,.odp,.zip,.rar',
    image: 'image/*',
    video: 'video/*',
  };

  /** Currently targeted MIME filter for the file input. */
  private fileAccept = '';

  constructor() {
    void this.messageSend.initialize();

    this.searchQueryControl.valueChanges
      .pipe(debounceTime(250), takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        this.ticketList.setSearchTerm(value);
      });

    // Reload via ChatRefreshService (disparado por realtime do sidebar)
    effect(() => {
      const tick = this.chatRefresh.refreshTick();
      if (tick > 0) {
        this.ticketList.loadTickets();
      }
    });

    // Handle audio recording completion — upload and send (delegated)
    this.chatRecorder.recordingCompleted$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(({ blob }) => {
        const ticket = this.selectedTicket();
        if (!ticket) return;
        this.recordingDispatcher.dispatch(blob, ticket.id, this.destroyRef);
      });

    this.messageSend.queueDelivered$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((event) => {
        this.cacheService.replace(event.calledId, event.clientMessageId, event.message);
        if (this.selectedTicketId() === event.calledId) {
          this.conversationRef?.replaceMessage(event.clientMessageId, event.message);
        }
      });

    this.messageSend.queueFailed$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((event) => {
      this.cacheService.updateStatus(event.calledId, {
        external_id: event.clientMessageId,
        status: 'failed',
      });
    });

    this.realtimeListener.start(this.destroyRef);
  }

  readonly ticketTabs = computed<AfTabItem[]>(() => {
    const counts = this.counts();
    return [
      { id: 'pending', label: 'Fila', icon: 'lucideClock3', badge: counts.pending },
      { id: 'open', label: 'Atendimento', icon: 'lucideMessagesSquare', badge: counts.open },
      { id: 'all', label: 'Todos', icon: 'lucideInbox', badge: counts.all },
    ];
  });

  readonly filteredTickets = computed(() => {
    const filter = this.ticketFilter();
    const items = this.tickets();

    if (filter === 'pending') {
      return items.filter((ticket) => ticket.status === 'pending');
    }

    if (filter === 'open') {
      return items.filter((ticket) => ticket.status === 'open' || ticket.status === 'in_progress');
    }

    return items;
  });

  readonly visibleTickets = computed(() => {
    const sentiment = this.emergencyFilter();
    const items = this.filteredTickets();

    if (sentiment === null) {
      return items;
    }

    return items.filter((ticket) => ticket.sentiment === sentiment);
  });

  readonly selectedTicket = computed(() => {
    const selectedId = this.selectedTicketId();
    if (!selectedId) return null;
    return this.tickets().find((ticket) => String(ticket.id) === selectedId) ?? null;
  });

  readonly isSelectedTicketStarted = computed(() => {
    const ticket = this.selectedTicket();
    if (!ticket) return false;
    return ticket.status === 'open' || ticket.status === 'in_progress';
  });

  /** ID do contato selecionado para uso no CRM section. */
  readonly selectedContactId = computed<string | null>(() => {
    const contact = this.selectedTicket()?.contact;
    return contact ? String(contact.id) : null;
  });

  ngOnInit(): void {
    this.appShell.disableContentScroll();
    this.ticketList.loadTickets();

    this.router.events
      .pipe(
        filter((event): event is NavigationEnd => event instanceof NavigationEnd),
        startWith(undefined),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe(() => this.syncSelectedTicketFromRoute());
  }

  ngOnDestroy(): void {
    this.appShell.enableContentScroll();
  }

  setTab(tab: 'chat' | 'contact' | 'negotiation'): void {
    this.activeTab.set(tab);
  }

  setTicketFilter(filter: string): void {
    if (filter === 'pending' || filter === 'open' || filter === 'all') {
      this.ticketFilter.set(filter);
    }
  }

  setEmergencyFilter(filter: CalledSentiment | null): void {
    this.emergencyFilter.set(filter);
    this.isEmergencyMenuOpen.set(false);
  }

  /** Abre o modal de pesquisa de atendimentos. */
  openSearchModal(): void {
    this.isSearchModalOpen.set(true);
  }

  /** Fecha o modal de pesquisa. */
  closeSearchModal(): void {
    this.isSearchModalOpen.set(false);
  }

  /** Limpa a pesquisa ativa e recarrega a lista completa. */
  clearSearch(): void {
    this.ticketList.setSearchTerm('');
    this.searchQueryControl.setValue('', { emitEvent: false });
  }

  /** Alterna o estado de silenciamento das notificações sonoras. */
  toggleSound(): void {
    this.chatSound.toggle();
  }

  /** Recarrega manualmente a lista com feedback visual no botão. */
  refreshList(): void {
    this.isRefreshing.set(true);
    this.ticketList.loadTickets(() => {
      setTimeout(() => this.isRefreshing.set(false), 400);
    });
  }

  /** Abre o modal de iniciar novo atendimento. */
  openNewConversationModal(): void {
    this.newConversationModal?.open();
  }

  /** Seleciona um ticket na lista e navega para sua rota. */
  selectTicket(ticketId: string | number): void {
    const id = String(ticketId);
    this.selectedTicketId.set(id);
    this.activeTab.set('chat');
    this.ticketList.hydrateTicket(id);
    void this.router.navigate(['/chat', id]);
  }

  /** Inicia atendimento de um ticket pendente (status -> in_progress). */
  startSelectedTicket(): void {
    const ticket = this.selectedTicket();
    if (!ticket || this.isSelectedTicketStarted()) {
      return;
    }

    const ticketId = String(ticket.id);
    this.isStartingTicket.set(true);

    this.calledService
      .open(ticketId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const opened = response.data;
          this.ticketList.setTransientStatusOverride(ticketId, 'in_progress');

          this.tickets.update((items) => {
            const index = items.findIndex((item) => String(item.id) === ticketId);
            if (index < 0) {
              return items;
            }

            const current = items[index];
            const merged: Called = {
              ...current,
              ...opened,
              status: 'in_progress',
              started_at: opened.started_at ?? current.started_at,
              updated_at: opened.updated_at ?? current.updated_at,
            };

            const next = [...items];
            next.splice(index, 1);
            next.unshift(merged);
            return next;
          });

          this.counts.update((current) => ({
            ...current,
            pending: Math.max(0, current.pending - 1),
            open: current.open + 1,
            in_progress: (current.in_progress ?? 0) + 1,
          }));

          this.isStartingTicket.set(false);
          this.ticketList.loadTickets();
        },
        error: () => {
          this.isStartingTicket.set(false);
        },
      });
  }

  /** Envia mensagem no footer quando ticket já está em atendimento. */
  sendMessage(): void {
    const ticket = this.selectedTicket();
    if (!ticket || !this.isSelectedTicketStarted()) {
      return;
    }

    const content = this.messageControl.value.trim();
    if (!content) {
      return;
    }

    const ticketId = String(ticket.id);
    const tempId = crypto.randomUUID();
    const optimisticMessage: CalledMessage = {
      id: tempId,
      called_id: ticket.id,
      ticket_id: ticket.id,
      content,
      type: 'text',
      direction: 'outgoing',
      status: 'pending',
      external_id: tempId,
      created_at: new Date().toISOString(),
    };

    this.cacheService.append(ticketId, optimisticMessage);

    this.isSendingMessage.set(true);

    // Limpa o campo e foca antecipadamente para melhor UX
    this.messageControl.setValue('', { emitEvent: false });
    this.resetTextareaHeight();
    setTimeout(() => {
      this.conversationRef?.focusMessageInput();
    }, 10);

    this.messageSend
      .sendText(ticketId, content, tempId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (result) => {
          if (result.status === 'sent') {
            this.conversationRef?.replaceMessage(tempId, result.message);
          } else {
            toast.info('Sem conexao no momento. Mensagem enfileirada para envio automatico.');
          }
          this.isSendingMessage.set(false);
        },
        error: () => {
          this.cacheService.updateStatus(ticketId, {
            external_id: tempId,
            status: 'failed',
          });
          this.isSendingMessage.set(false);
        },
      });
  }

  /** Auto-resize textarea to fit content (WhatsApp-style). */
  autoResizeTextarea(event: Event): void {
    const textarea = event.target as HTMLTextAreaElement;
    textarea.style.height = 'auto';
    textarea.style.height = `${textarea.scrollHeight}px`;
  }

  /** Handle keydown on message textarea: Enter sends, Shift+Enter adds newline. */
  onMessageKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      this.sendMessage();
    }
  }

  // ── Audio recording methods ────────────────────────────────────────────────

  /** Format seconds into mm:ss display string. */
  formatRecordingTime(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  }

  /** Start recording audio from the microphone. */
  startRecording(): void {
    void this.chatRecorder.start();
  }

  /** Pause the current recording. */
  pauseRecording(): void {
    this.chatRecorder.pause();
  }

  /** Resume recording after pause. */
  resumeRecording(): void {
    this.chatRecorder.resume();
  }

  /** Cancel recording and discard audio. */
  cancelRecording(): void {
    this.chatRecorder.cancel();
  }

  /** Stop recording and send the audio message. */
  sendRecording(): void {
    const ticket = this.selectedTicket();
    if (!ticket || this.recordingState() === 'text') {
      return;
    }
    this.chatRecorder.stop();
  }

  /** Reset textarea height after sending a message. */
  private resetTextareaHeight(): void {
    const el = this.messageTextareaRef()?.nativeElement;
    if (el) {
      el.style.height = 'auto';
    }
  }

  /** Verifica se o ticket informado está selecionado. */
  isSelectedTicket(ticketId: string | number): boolean {
    return this.selectedTicketId() === String(ticketId);
  }

  /**
   * Retorna as iniciais do contato do ticket selecionado.
   *
   * @returns String com até 2 caracteres maiúsculos.
   */
  getSelectedTicketInitials(): string {
    const ticket = this.selectedTicket();
    const name = ticket?.contact?.name ?? ticket?.contact?.phone ?? '';
    return name.slice(0, 2).toUpperCase() || '??';
  }

  /**
   * Retorna a URL da foto de perfil do contato do ticket selecionado.
   *
   * @returns URL da imagem ou null.
   */
  getSelectedTicketProfilePicture(): string | null {
    const ticket = this.selectedTicket();
    return ticket?.profile_picture_url ?? ticket?.contact?.profile_picture_url ?? null;
  }

  /** Hides img element on load error so initials fallback shows through. */
  handleProfilePictureError(event: Event): void {
    (event.target as HTMLImageElement).style.display = 'none';
  }

  /** Handler para contato atualizado pelo sidebar component. */
  onContactUpdated(contact: Contact): void {
    const ticket = this.selectedTicket();
    if (!ticket) return;
    this.tickets.update((items) =>
      items.map((t) =>
        String(t.id) === String(ticket.id)
          ? {
              ...t,
              contact: {
                ...t.contact!,
                name: contact.name,
                email: contact.email ?? t.contact?.email,
                phone: contact.phone ?? t.contact?.phone,
              },
            }
          : t,
      ),
    );
  }

  /** Deseleciona o ticket atual e navega para /chat. */
  closeSelectedTicket(): void {
    this.selectedTicketId.set(null);
    void this.router.navigate(['/chat']);
  }

  /** Abre modal de transferência do atendimento selecionado. */
  openTransferModal(): void {
    this.ticketTransfer.openModal(this.selectedTicket());
  }

  /** Fecha modal de transferência. */
  closeTransferModal(): void {
    this.ticketTransfer.closeModal();
  }

  /** Handler para transferência confirmada pelo ChatTransferModalComponent. */
  onTransferConfirmed(event: { ticketId: string; toUserId: string; reason: string }): void {
    this.ticketTransfer.confirm(event, this.destroyRef, this.tickets);
  }

  /** Abre o modal de confirmação para encerrar o atendimento selecionado. */
  openCloseTicketConfirm(): void {
    this.ticketClose.openConfirm(this.selectedTicket());
  }

  /** Fecha o modal de confirmação de encerramento. */
  closeCloseTicketConfirm(): void {
    this.ticketClose.closeConfirm();
  }

  /** Confirma o encerramento do atendimento atual. */
  confirmCloseSelectedTicket(): void {
    this.ticketClose.confirm(
      this.selectedTicket(),
      this.destroyRef,
      this.tickets,
      this.counts,
      this.selectedTicketId,
    );
  }

  /** Sincroniza ticket selecionado a partir da rota ativa (`/chat/:calledId`). */
  private syncSelectedTicketFromRoute(): void {
    this.ticketList.syncSelectedTicketFromRoute(this.selectedTicketId, (ticketId) => {
      /* hydrate is handled inside the service */
    });
  }

  // ── Attachment Dropdown ───────────────────────────────────────────────────

  /** Abre/fecha o dropdown de anexos. */
  toggleAttachmentMenu(): void {
    this.isAttachmentMenuOpen.update((v) => !v);
  }

  /** Fecha o dropdown de anexos. */
  closeAttachmentMenu(): void {
    this.isAttachmentMenuOpen.set(false);
  }

  /** Seleciona um tipo de anexo e abre o seletor de arquivos. */
  selectAttachmentType(
    type: 'document' | 'image' | 'video' | 'location' | 'camera' | 'gallery' | 'file',
  ): void {
    this.isAttachmentMenuOpen.set(false);
    this.attachmentErrorMessage.set(null);

    if (type === 'location') {
      this.sendCurrentLocation();
      return;
    }

    if (type === 'camera') {
      void this.handleNativePhotoSelection('camera');
      return;
    }

    if (type === 'gallery') {
      void this.handleNativePhotoSelection('gallery');
      return;
    }

    if (type === 'file') {
      this.openFileSelector('*/*');
      return;
    }

    this.openFileSelector(this.mimeTypesByType[type] ?? '');
  }

  clearAttachmentError(): void {
    this.attachmentErrorMessage.set(null);
  }

  private async handleNativePhotoSelection(source: 'camera' | 'gallery'): Promise<void> {
    const result =
      source === 'camera'
        ? await this.nativeBridge.capturePhoto()
        : await this.nativeBridge.pickPhotoFromGallery();

    if (result.status === 'success') {
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(result.file);
      this.stageFiles(dataTransfer.files);
      return;
    }

    if (result.status === 'permission-denied' || result.status === 'unavailable') {
      this.attachmentErrorMessage.set(result.message);
      this.openFileSelector('image/*');
      return;
    }

    // Cancelamento intencional pelo usuário: mantém a UI sem erro.
  }

  private openFileSelector(accept: string): void {
    this.fileAccept = accept;

    const input = this.fileInputRef()?.nativeElement;
    if (input) {
      input.accept = this.fileAccept;
      input.click();
    }
  }

  /** Handler do input de arquivo. */
  onFileInputChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;
    this.attachmentErrorMessage.set(null);
    this.stageFiles(input.files);
    input.value = '';
  }

  // ── Drag & Drop ──────────────────────────────────────────────────────────

  /** Host listener para dragover — ativa o visual de drop zone. */
  @HostListener('dragover', ['$event'])
  onDragOver(event: DragEvent): void {
    if (!this.isSelectedTicketStarted()) return;
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver.set(true);
  }

  /** Host listener para dragleave — desativa o visual de drop zone. */
  @HostListener('dragleave', ['$event'])
  onDragLeave(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver.set(false);
  }

  /** Host listener para drop — captura os arquivos e abre preview. */
  @HostListener('drop', ['$event'])
  onDrop(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver.set(false);
    if (!this.isSelectedTicketStarted()) return;
    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
      this.stageFiles(files);
    }
  }

  /** Host listener para paste — captura arquivos do clipboard. */
  @HostListener('paste', ['$event'])
  onPaste(event: ClipboardEvent): void {
    if (!this.isSelectedTicketStarted()) return;
    const items = event.clipboardData?.items;
    if (!items) return;

    const files: File[] = [];
    for (const item of Array.from(items)) {
      if (item.kind === 'file') {
        const file = item.getAsFile();
        if (file) files.push(file);
      }
    }

    if (files.length > 0) {
      event.preventDefault();
      const dt = new DataTransfer();
      files.forEach((f) => dt.items.add(f));
      this.stageFiles(dt.files);
    }
  }

  // ── Media Preview (staging) ──────────────────────────────────────────────

  /** Converte FileList em MediaPreviewItem[] e abre a modal de preview. */
  private stageFiles(files: FileList): void {
    const items: MediaPreviewItem[] = Array.from(files).map((file) => ({
      id: crypto.randomUUID(),
      file,
      type: this.detectMediaType(file),
      caption: '',
      previewUrl: this.createPreviewUrl(file),
      status: 'pending' as const,
      progress: { loaded: 0, total: file.size },
    }));

    this.mediaPreviewItems.update((prev) => [...prev, ...items]);
    if (!this.mediaPreviewSelectedId() && items.length > 0) {
      this.mediaPreviewSelectedId.set(items[0].id);
    }
    this.isMediaPreviewOpen.set(true);
  }

  /** Detecta o tipo de mídia a partir do MIME type do arquivo. */
  private detectMediaType(file: File): MediaPreviewType {
    const mime = file.type.toLowerCase();
    if (mime.startsWith('image/')) return 'image';
    if (mime.startsWith('video/')) return 'video';
    if (mime.startsWith('audio/')) return 'audio';
    return 'document';
  }

  /** Gera URL de preview para imagens e vídeos. */
  private createPreviewUrl(file: File): string | null {
    const mime = file.type.toLowerCase();
    if (mime.startsWith('image/') || mime.startsWith('video/') || mime.startsWith('audio/')) {
      return URL.createObjectURL(file);
    }
    return null;
  }

  /** Seleciona um item no preview. */
  onMediaPreviewSelect(id: string): void {
    this.mediaPreviewSelectedId.set(id);
  }

  /** Remove um item do staging. */
  onMediaPreviewRemove(id: string): void {
    const items = this.mediaPreviewItems().filter((item) => item.id !== id);
    this.mediaPreviewItems.set(items);

    if (this.mediaPreviewSelectedId() === id) {
      this.mediaPreviewSelectedId.set(items[0]?.id ?? null);
    }
    if (items.length === 0) {
      this.isMediaPreviewOpen.set(false);
    }
  }

  /** Adiciona mais arquivos ao staging. */
  onMediaPreviewAddFiles(files: FileList): void {
    this.stageFiles(files);
  }

  /** Atualiza a caption de um item no staging. */
  onMediaPreviewUpdateCaption(event: { id: string; caption: string }): void {
    this.mediaPreviewItems.update((items) =>
      items.map((item) => (item.id === event.id ? { ...item, caption: event.caption } : item)),
    );
  }

  /** Fecha a modal de preview e limpa o staging. */
  closeMediaPreview(): void {
    // Revoke preview URLs to prevent memory leaks
    for (const item of this.mediaPreviewItems()) {
      if (item.previewUrl) {
        URL.revokeObjectURL(item.previewUrl);
      }
    }
    this.mediaPreviewItems.set([]);
    this.mediaPreviewSelectedId.set(null);
    this.isMediaPreviewOpen.set(false);
    this.mediaUploadProgress.set(0);
  }

  // ── Upload & Send ────────────────────────────────────────────────────────

  /** Envia todos os arquivos do staging com progress tracking. */
  sendMediaFiles(): void {
    const ticket = this.selectedTicket();
    if (!ticket || !this.isSelectedTicketStarted()) return;

    const items = this.mediaPreviewItems();
    if (items.length === 0) return;

    const calledId = String(ticket.id);
    this.isMediaSending.set(true);

    // Mark all items as uploading
    this.mediaPreviewItems.update((prev) =>
      prev.map((item) => ({ ...item, status: 'uploading' as const })),
    );

    let completedCount = 0;
    const totalItems = items.length;

    // Create optimistic bubbles for each item
    for (const item of items) {
      const tempMessage = this.createOptimisticMediaMessage(item);
      this.conversationRef?.appendMessage(tempMessage);
    }

    this.mediaBatchService
      .dispatchBatch(calledId, items)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (event: MediaBatchEvent) => {
          this.handleMediaBatchEvent(event, totalItems, completedCount);
          if (event.phase === 'sent' || event.phase === 'failed') {
            completedCount += 1;
            this.mediaUploadProgress.set(completedCount / totalItems);
          }
        },
        complete: () => {
          this.isMediaSending.set(false);
          // Close preview after a short delay to show final state
          setTimeout(() => this.closeMediaPreview(), 600);
        },
        error: () => {
          this.isMediaSending.set(false);
        },
      });
  }

  /** Cria uma mensagem otimista (placeholder) para exibir o bubble com progress. */
  private createOptimisticMediaMessage(item: MediaPreviewItem): CalledMessage {
    return {
      id: `temp-${item.id}`,
      content: item.caption || item.file.name,
      type: item.type,
      direction: 'outgoing',
      status: 'pending',
      file_url: item.previewUrl,
      file_name: item.file.name,
      mime_type: item.file.type,
      file_size: item.file.size,
      created_at: new Date().toISOString(),
    };
  }

  /** Processa eventos do batch upload e atualiza o estado dos itens. */
  private handleMediaBatchEvent(
    event: MediaBatchEvent,
    _totalItems: number,
    _completedCount: number,
  ): void {
    this.mediaPreviewItems.update((items) =>
      items.map((item) => {
        if (item.id !== event.id) return item;

        if (event.phase === 'uploading') {
          return {
            ...item,
            status: 'uploading' as const,
            progress: { loaded: event.loaded ?? 0, total: event.total ?? item.file.size },
          };
        }
        if (event.phase === 'uploaded') {
          return {
            ...item,
            status: 'uploading' as const,
            progress: { loaded: item.file.size, total: item.file.size },
          };
        }
        if (event.phase === 'sent') {
          // Replace optimistic message with real one
          if (event.message) {
            this.conversationRef?.replaceMessage(`temp-${item.id}`, event.message);
          }
          return { ...item, status: 'done' as const };
        }
        if (event.phase === 'failed') {
          return { ...item, status: 'failed' as const };
        }
        return item;
      }),
    );
  }

  // ── Location ─────────────────────────────────────────────────────────────

  /** Envia a localização atual do usuário via API de geolocalização do browser. */
  private sendCurrentLocation(): void {
    const ticket = this.selectedTicket();
    if (!ticket || !this.isSelectedTicketStarted()) return;

    if (!navigator.geolocation) {
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) => {
        this.calledMessageService
          .sendLocation(String(ticket.id), {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            name: 'Minha localização',
          })
          .pipe(takeUntilDestroyed(this.destroyRef))
          .subscribe({
            next: (response) => {
              this.conversationRef?.appendMessage(response.data);
            },
          });
      },
      () => {
        // Geolocation denied or unavailable — silent fail
      },
    );
  }
}
