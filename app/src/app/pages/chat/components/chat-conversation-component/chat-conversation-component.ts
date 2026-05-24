import {
  ChangeDetectionStrategy,
  Component,
  ViewChild,
  input,
  output,
  signal,
  type ElementRef,
  effect,
} from '@angular/core';
import { toast } from 'ngx-sonner';
import { ReactiveFormsModule, type FormControl } from '@angular/forms';
import { NgIcon } from '@ng-icons/core';
import { type Contact } from 'src/app/core/models/contact.model';
import { type Called } from 'src/app/core/services/called.service';
import { type CalledMessage } from 'src/app/core/services/called-message.service';
import { ButtonComponent, IconButtonComponent } from 'src/app/shared/components/buttons';
import { AfAlertComponent } from 'src/app/shared/components/alert/alert';
import { AfBadgeComponent } from 'src/app/shared/components/badge/badge';
import { AfBannerComponent } from 'src/app/shared/components/banner/banner';
import { AfSheetComponent } from 'src/app/shared/components/sheet/sheet';
import { AfSpinnerComponent } from 'src/app/shared/components/spinner/spinner';
import { AfModalComponent } from 'src/app/shared/components/modal/modal';
import { AfPopoverComponent } from 'src/app/shared/components/popover/popover';
import {
  TemplateSelectorComponent,
  type TemplateSelectedEvent,
} from 'src/app/shared/components/template-selector/template-selector';
import { ContactSectionComponent } from '../../chat-sidebar/contact-section/contact-section';
import { CRMSectionComponent } from '../../chat-sidebar/crm-section/crm-section';
import { UserChat } from '../user-chat/user-chat';
import { type ComposerMode } from '../../chat.store';
import { AfEmptyStateComponent } from 'src/app/shared/components/empty-state/empty-state';

/**
 * Main conversation area extracted from chat page.
 */
@Component({
  selector: 'app-chat-conversation',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    NgIcon,
    ButtonComponent,
    IconButtonComponent,
    AfAlertComponent,
    AfBadgeComponent,
    AfBannerComponent,
    AfSheetComponent,
    AfSpinnerComponent,
    AfModalComponent,
    AfPopoverComponent,
    TemplateSelectorComponent,
    UserChat,
    ContactSectionComponent,
    CRMSectionComponent,
    AfEmptyStateComponent,
  ],
  templateUrl: './chat-conversation-component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChatConversationComponent {
  @ViewChild(UserChat) private readonly userChatRef?: UserChat;
  @ViewChild('messageTextarea')
  private readonly messageTextareaRef?: ElementRef<HTMLTextAreaElement>;

  constructor() {
    effect(() => {
      const id = this.selectedTicketId();
      const isStarted = this.isSelectedTicketStarted();
      if (id && isStarted) {
        setTimeout(() => {
          this.focusMessageInput();
        }, 50);
      }
    });
  }

  readonly activeTab = input.required<'chat' | 'contact' | 'negotiation'>();
  readonly selectedTicketId = input<string | null>(null);
  readonly selectedTicket = input<Called | null>(null);
  readonly selectedContactId = input<string | null>(null);
  readonly selectedTicketInitials = input.required<string>();
  readonly selectedTicketProfilePicture = input<string | null>(null);
  readonly isSelectedTicketStarted = input.required<boolean>();
  readonly isStartingTicket = input.required<boolean>();
  readonly isTransferLoading = input.required<boolean>();
  readonly isClosingTicket = input.required<boolean>();
  readonly isRecording = input.required<boolean>();
  readonly isRecordingPaused = input.required<boolean>();
  readonly recordingDuration = input.required<number>();
  readonly isSendingAudio = input.required<boolean>();
  readonly isMobile = input.required<boolean>();
  readonly isAttachmentMenuOpen = input.required<boolean>();
  readonly attachmentErrorMessage = input<string | null>(null);
  readonly pendingQueueCount = input(0);
  readonly isQueueFlushing = input(false);
  readonly messageControl = input.required<FormControl<string>>();
  readonly isSendingMessage = input.required<boolean>();
  readonly composerMode = input<ComposerMode>('free');
  readonly chatInstanceId = input<string | null>(null);
  readonly selectedTemplate = input<TemplateSelectedEvent | null>(null);

  readonly openNewConversationModal = output<void>();
  readonly closeSelectedTicket = output<void>();
  readonly openTransferModal = output<void>();
  readonly openCloseTicketConfirm = output<void>();
  readonly startSelectedTicket = output<void>();
  readonly cancelRecording = output<void>();
  readonly pauseRecording = output<void>();
  readonly resumeRecording = output<void>();
  readonly sendRecording = output<void>();
  readonly toggleAttachmentMenu = output<void>();
  readonly closeAttachmentMenu = output<void>();
  readonly clearAttachmentError = output<void>();
  readonly selectAttachmentType = output<
    'document' | 'image' | 'video' | 'location' | 'camera' | 'gallery' | 'file'
  >();
  readonly messageKeydown = output<KeyboardEvent>();
  readonly messageInput = output<Event>();
  readonly startRecording = output<void>();
  readonly sendMessage = output<void>();
  readonly profilePictureError = output<Event>();
  readonly contactUpdated = output<Contact>();
  readonly templateSelected = output<TemplateSelectedEvent>();
  readonly clearTemplateSelected = output<void>();

  protected readonly bars = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14] as const;

  /** Estado do picker de template inline (mixed) */
  readonly isTemplatePickerOpen = signal(false);
  /** Estado do picker de template em modal (template-only) */
  readonly isTemplateModalOpen = signal(false);

  copyProtocol(protocol: string): void {
    void navigator.clipboard
      .writeText(protocol)
      .then(() => {
        toast.success(`Protocolo #${protocol} copiado!`);
      })
      .catch(() => {
        toast.error('Nao foi possivel copiar o protocolo.');
      });
  }

  appendMessage(message: CalledMessage): void {
    this.userChatRef?.appendMessage(message);
  }

  replaceMessage(tempId: string, message: CalledMessage): void {
    this.userChatRef?.replaceMessage(tempId, message);
  }

  focusMessageInput(): void {
    if (this.messageTextareaRef?.nativeElement) {
      this.messageTextareaRef.nativeElement.focus();
    }
  }

  protected formatRecordingTime(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  }
}
