import {
  type ElementRef,
  type AfterViewInit,
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
  viewChild,
  HostListener,
} from '@angular/core';
import { AfButtonComponent } from '../button/button';
import { AfIconButtonComponent } from '../icon-button/icon-button';
import { LucideAngularModule } from 'lucide-angular';

type AttachmentType = 'document' | 'image' | 'video' | 'audio';

interface AttachmentOption {
  type: AttachmentType;
  label: string;
  icon: string;
}

/**
 * AfChatComposerComponent — Message composition area with auto-resizing textarea,
 * attachment button, and send action. Supports Enter to send and Shift+Enter for newline.
 *
 * @example
 * ```html
 * <af-chat-composer
 *   placeholder="Digite sua mensagem..."
 *   (messageSent)="onSend($event)"
 *   (attachmentClicked)="openFileDialog()"
 * />
 * ```
 */
@Component({
  selector: 'af-chat-composer',
  standalone: true,
  imports: [AfButtonComponent, AfIconButtonComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './chat-composer.html',
})
export class AfChatComposerComponent implements AfterViewInit {
  /** Placeholder text */
  readonly placeholder = input('Digite sua mensagem...');

  /** Whether the composer is disabled */
  readonly disabled = input(false);

  /** Maximum message length */
  readonly maxLength = input<number | null>(null);

  /** Emitted with the message text when sent */
  readonly messageSent = output<string>();

  /** Emitted when the attachment button is clicked */
  readonly attachmentClicked = output<void>();

  /** Emitted with attachment type selected from dropdown */
  readonly attachmentTypeSelected = output<AttachmentType>();

  /** Current message length */
  protected readonly messageLength = signal(0);

  /** Whether there is content to send */
  protected readonly canSend = signal(false);

  /** Attachment dropdown visibility */
  protected readonly attachmentMenuOpen = signal(false);

  protected readonly attachmentOptions: AttachmentOption[] = [
    { type: 'document', label: 'Documento', icon: 'file-text' },
    { type: 'image', label: 'Foto', icon: 'image' },
    { type: 'video', label: 'Vídeo', icon: 'video' },
    { type: 'audio', label: 'Áudio', icon: 'music' },
  ];

  private readonly textareaRef = viewChild<ElementRef<HTMLTextAreaElement>>('textarea');
  private readonly composerRootRef = viewChild<ElementRef<HTMLElement>>('composerRoot');

  ngAfterViewInit(): void {
    this.autoResize();
  }

  /** Handle input for auto-resize and state updates */
  protected onInput(): void {
    const el = this.textareaRef()?.nativeElement;
    if (el) {
      const value = el.value.trim();
      this.messageLength.set(el.value.length);
      this.canSend.set(value.length > 0);
      this.autoResize();
    }
  }

  protected toggleAttachmentMenu(): void {
    if (this.disabled()) {
      return;
    }

    this.attachmentClicked.emit();
    this.attachmentMenuOpen.update((open) => !open);
  }

  protected selectAttachment(type: AttachmentType): void {
    this.attachmentTypeSelected.emit(type);
    this.attachmentMenuOpen.set(false);
  }

  /** Handle keyboard shortcuts */
  protected onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      this.send();
    }
  }

  /** Emit the message and clear the input */
  protected send(): void {
    const el = this.textareaRef()?.nativeElement;
    if (!el) return;

    const value = el.value.trim();
    if (value.length === 0) return;

    this.messageSent.emit(value);
    el.value = '';
    this.messageLength.set(0);
    this.canSend.set(false);
    this.autoResize();
  }

  @HostListener('document:click', ['$event'])
  protected onDocumentClick(event: MouseEvent): void {
    if (!this.attachmentMenuOpen()) {
      return;
    }

    const root = this.composerRootRef()?.nativeElement;
    const target = event.target;
    if (!(target instanceof Node) || !root) {
      return;
    }

    if (!root.contains(target)) {
      this.attachmentMenuOpen.set(false);
    }
  }

  /** Auto-resize textarea based on content */
  private autoResize(): void {
    const el = this.textareaRef()?.nativeElement;
    if (el) {
      el.style.height = 'auto';
      el.style.height = `${Math.min(el.scrollHeight, 128)}px`;
    }
  }
}
