import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Bolha de mensagem de chat com estilo direcional (entrada vs. saída),
 * horário e confirmações de leitura. Suporta texto, imagem, vídeo, áudio e arquivos.
 *
 * Contexto: utilizado na janela de conversa do chat para renderizar cada
 * mensagem individual enviada ou recebida.
 *
 * @example
 * ```html
 * <af-chat-bubble
 *   message="Olá, como posso ajudar?"
 *   timestamp="14:32"
 *   senderName="Maria"
 *   direction="in"
 * />
 * ```
 */
@Component({
  selector: 'af-chat-bubble',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './chat-bubble.html',
})
export class AfChatBubbleComponent {
  /** Texto da mensagem */
  readonly message = input.required<string>();

  /** String de horário formatada */
  readonly timestamp = input.required<string>();

  /** Nome de exibição do remetente */
  readonly senderName = input<string>();

  /** Direção da mensagem: entrada ou saída */
  readonly direction = input.required<'in' | 'out'>();

  /** Status de entrega (apenas para mensagens de saída) */
  readonly status = input<'sent' | 'delivered' | 'read'>('sent');

  /** Tipo da mensagem — controla como a mídia é renderizada */
  readonly type = input<'text' | 'image' | 'video' | 'audio' | 'file' | 'document'>('text');

  /** URL do arquivo de mídia (usado quando type não é 'text') */
  readonly fileUrl = input<string | undefined>(undefined);

  /** Tipo MIME da mídia (ex.: 'image/jpeg', 'video/mp4') */
  readonly mimeType = input<string | undefined>(undefined);

  /** Nome original do arquivo (para mensagens de arquivo/documento) */
  readonly fileName = input<string | undefined>(undefined);

  /** Nome de exibição do arquivo — usa fileName, com fallback para mensagem ou basename da URL */
  protected readonly displayFileName = computed(() => {
    const name = this.fileName()?.trim() || this.message()?.trim();
    if (name) return name;
    const url = this.fileUrl();
    if (url) {
      try {
        return url.split('/').pop() || 'Arquivo';
      } catch {
        return 'Arquivo';
      }
    }
    return 'Arquivo';
  });

  /** Verdadeiro quando a bolha contém uma imagem */
  protected readonly isImage = computed(() => this.type() === 'image');

  /** Verdadeiro quando a bolha contém um vídeo */
  protected readonly isVideo = computed(() => this.type() === 'video');

  /** Verdadeiro quando a bolha contém áudio */
  protected readonly isAudio = computed(() => this.type() === 'audio');

  /** Verdadeiro quando a bolha contém um arquivo para download */
  protected readonly isFile = computed(
    () => this.type() === 'file' || this.type() === 'document',
  );

  /** Verdadeiro quando há URL de mídia disponível */
  protected readonly hasMedia = computed(
    () =>
      (this.isImage() || this.isVideo() || this.isAudio() || this.isFile()) &&
      !!this.fileUrl(),
  );

  /** Alinhamento do contêiner da bolha */
  protected readonly wrapperClasses = computed(() => {
    const base = 'flex flex-col w-full';
    return this.direction() === 'out' ? `${base} items-end` : `${base} items-start`;
  });

  /** Estilo da bolha varia por direção */
  protected readonly bubbleClasses = computed(() => {
    const base = 'px-3.5 py-2 rounded-2xl max-w-[95%] overflow-hidden';
    return this.direction() === 'out'
      ? `${base} bg-accent-500 text-white rounded-br-md`
      : `${base} bg-neutral-100 dark:bg-[#191d1a] text-neutral-900 dark:text-neutral-50 rounded-bl-md`;
  });

  /**
   * Verdadeiro quando o conteúdo da mensagem é uma legenda legível (não URL ou hash hex).
   * Evita que URLs brutas ou hashes apareçam abaixo de imagens e vídeos.
   */
  protected readonly hasReadableCaption = computed(() => {
    const content = this.message()?.trim();
    if (!content) return false;
    if (/^https?:\/\//i.test(content)) return false;
    if (content.length > 40 && /^[0-9a-f]+(\.[a-z0-9]{1,5})?$/i.test(content)) return false;
    return true;
  });

  /** Abre uma URL em nova aba (usado ao clicar em imagem para visualização em tamanho real) */
  protected openUrl(url: string): void {
    window.open(url, '_blank', 'noopener,noreferrer');
  }
}
