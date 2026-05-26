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
 * Área de composição de mensagens com textarea de redimensionamento automático,
 * botão de anexo e ação de envio. Suporta Enter para enviar e Shift+Enter para nova linha.
 *
 * Contexto: utilizado na janela de chat como barra de entrada de mensagens do operador.
 *
 * @example
 * ```html
 * <af-chat-composer
 *   placeholder="Digite sua mensagem..."
 *   (messageSent)="onEnviar($event)"
 *   (attachmentClicked)="abrirSeletor()"
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
  /** Texto de placeholder */
  readonly placeholder = input('Digite sua mensagem...');

  /** Indica se o compositor está desabilitado */
  readonly disabled = input(false);

  /** Comprimento máximo da mensagem */
  readonly maxLength = input<number | null>(null);

  /** Emitido com o texto da mensagem ao enviar */
  readonly messageSent = output<string>();

  /** Emitido ao clicar no botão de anexo */
  readonly attachmentClicked = output<void>();

  /** Emitido com o tipo de anexo selecionado no dropdown */
  readonly attachmentTypeSelected = output<AttachmentType>();

  /** Comprimento atual da mensagem */
  protected readonly messageLength = signal(0);

  /** Indica se há conteúdo para enviar */
  protected readonly canSend = signal(false);

  /** Visibilidade do menu de anexos */
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

  /** Trata entrada de texto para redimensionamento automático e atualização de estado */
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

  /** Seleciona um tipo de anexo no dropdown */
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

  /** Emite a mensagem e limpa o campo de entrada */
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

  /** Redimensiona automaticamente o textarea com base no conteúdo */
  private autoResize(): void {
    const el = this.textareaRef()?.nativeElement;
    if (el) {
      el.style.height = 'auto';
      el.style.height = `${Math.min(el.scrollHeight, 128)}px`;
    }
  }
}
