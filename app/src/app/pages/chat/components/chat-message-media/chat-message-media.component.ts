import {
  type AfterViewInit,
  type ElementRef,
  type OnChanges,
  type OnDestroy,
  type SimpleChanges,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  Input,
  ViewChild,
  inject,
  signal,
} from '@angular/core';
import { NgIcon, provideIcons } from '@ng-icons/core';
import {
  lucideAlertCircle,
  lucideDownload,
  lucideFile,
  lucideImage,
  lucideMusic,
  lucidePause,
  lucidePlay,
} from '@ng-icons/lucide';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ChatMediaLoaderService } from 'src/app/core/services/chat-media-loader.service';
import { type ChatMediaGalleryItem } from './chat-message-media.component.model';
import 'photoswipe/style.css';
import type PhotoSwipeLightbox from 'photoswipe/lightbox';

export * from './chat-message-media.component.model';



/**
 * Componente responsável pela carregamento e exibição de mídias em mensagens.
 *
 * Implementa carregamento preguiçoso (lazy loading) via IntersectionObserver,
 * suporte a múltiplos tipos de arquivo (imagem, vídeo, áudio, documentos)
 * e integração com o serviço de cache e resolução de URLs de mídia.
 *
 * @class ChatMessageMediaComponent
 * @description Renderizador de mídias (fotos, vídeos, áudios e arquivos) do chat.
 */
@Component({
  selector: 'app-chat-message-media',
  standalone: true,
  imports: [NgIcon],
  templateUrl: './chat-message-media.component.html',
  styleUrl: './chat-message-media.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  viewProviders: [
    provideIcons({
      lucideAlertCircle,
      lucideDownload,
      lucideFile,
      lucideImage,
      lucideMusic,
      lucidePause,
      lucidePlay,
    }),
  ],
})
export class ChatMessageMediaComponent implements AfterViewInit, OnDestroy, OnChanges {
  /**
   * Identificador único da mensagem no backend.
   * @type {Input}
   */
  @Input({ required: true }) messageId!: string | number;

  /**
   * Identificador do atendimento (ticket) ao qual a mensagem pertence.
   * @type {Input}
   */
  @Input({ required: true }) calledId!: string | number;

  /**
   * Tipo da mídia contida na mensagem.
   * @type {Input}
   */
  @Input() type: 'image' | 'video' | 'audio' | 'document' | 'file' | 'ptt' | 'ptv' | 'sticker' =
    'image';

  /**
   * Nome original do arquivo para exibição em downloads e logs.
   * @type {Input}
   */
  @Input() fileName: string | null = null;

  /**
   * Metadados adicionais da mídia (ex: duração de áudio/vídeo).
   * @type {Input}
   */
  @Input() metadata: Record<string, unknown> | null = null;

  /**
   * Tipo MIME do arquivo para correta renderização do player de vídeo/áudio ou imagem.
   * @type {Input}
   */
  @Input() mimeType: string | null = null;

  /**
   * URL de contingência caso o carregamento normal falhe ou para antecipar o cache.
   * @type {Input}
   */
  @Input() fallbackUrl: string | null = null;

  /**
   * Itens de galeria para navegação entre mídias da conversa.
   * @type {Input}
   */
  @Input() galleryItems: readonly ChatMediaGalleryItem[] = [];

  /**
   * Identificador da mídia atual dentro da galeria.
   * @type {Input}
   */
  @Input() galleryActiveId: string | null = null;

  @ViewChild('mediaContainer') mediaContainer!: ElementRef<HTMLElement>;
  @ViewChild('audioPlayer') audioPlayerRef?: ElementRef<HTMLAudioElement>;

  /** Whether the custom audio player is currently playing. */
  readonly isAudioPlaying = signal(false);

  /** Current audio playback progress percentage (0–100). */
  readonly audioProgress = signal(0);

  /** Formatted current playback time (M:SS). */
  readonly audioCurrentTime = signal('0:00');

  private audioAnimationId: number | null = null;

  /**
   * Indica se o elemento de mídia está visível no viewport do usuário.
   * @type {WritableSignal<boolean>}
   */
  readonly isVisible = signal(false);

  /**
   * Indica se a mídia já terminou de carregar (imagem baixada ou player pronto).
   * @type {WritableSignal<boolean>}
   */
  readonly isLoaded = signal(false);

  /**
   * Indica se ocorreu algum erro durante o carregamento ou processamento da URL da mídia.
   * @type {WritableSignal<boolean>}
   */
  readonly hasError = signal(false);

  /**
   * URL final resolvida e autorizada para carregamento da mídia.
   * @type {WritableSignal<string | null>}
   */
  readonly resolvedUrl = signal<string | null>(null);

  private readonly mediaLoader = inject(ChatMediaLoaderService);
  private readonly destroyRef = inject(DestroyRef);

  private observer: IntersectionObserver | null = null;
  private hasRequestedLoad = false;
  private imageLightbox: PhotoSwipeLightbox | null = null;

  /**
   * Monitorar mudanças nas URLs de fallback para pré-carregar (prime) o cache de mídia.
   * @param changes Objeto contendo as alterações nas propriedades de entrada.
   */
  ngOnChanges(changes: SimpleChanges): void {
    if (changes['fallbackUrl'] && this.fallbackUrl && this.messageId && this.calledId) {
      this.mediaLoader.prime(this.calledId, this.messageId, this.fallbackUrl);

      // Update local state directly to render it immediately
      // Se a URL mudou (ex: via webhook), forçamos a renderização ou novo load
      if (this.resolvedUrl() !== this.fallbackUrl) {
        this.isLoaded.set(false);
        this.hasError.set(false);
        this.resolvedUrl.set(this.fallbackUrl);

        const mediaType = this.type ?? 'image';
        if (this.shouldAutoMarkLoaded(mediaType)) {
          this.isLoaded.set(true);
        }
      }
    }
  }

  /**
   * Inicializar o observador de interseção para carregamento preguiçoso após a visualização.
   */
  ngAfterViewInit(): void {
    this.setupObserver();
  }

  /**
   * Limpeza do observador de interseção para evitar vazamento de memória.
   */
  ngOnDestroy(): void {
    this.destroyImageLightbox();
    this.disconnectObserver();
    this.stopAudioTracking();
  }

  /**
   * Toggle play/pause on the custom audio player element.
   */
  toggleAudioPlay(): void {
    const audio = this.audioPlayerRef?.nativeElement;
    if (!audio) return;

    if (audio.paused) {
      void audio.play();
      this.isAudioPlaying.set(true);
      this.startAudioTracking(audio);
    } else {
      audio.pause();
      this.isAudioPlaying.set(false);
      this.stopAudioTracking();
    }

    audio.onended = () => {
      this.isAudioPlaying.set(false);
      this.audioProgress.set(0);
      this.audioCurrentTime.set(this.formatAudioTime(0));
      this.stopAudioTracking();
    };
  }

  /**
   * Seek audio to the clicked position on the progress bar.
   * @param event Mouse click event on the progress bar container.
   */
  seekAudio(event: UIEvent): void {
    const audio = this.audioPlayerRef?.nativeElement;
    if (!audio || !audio.duration) return;
    const target = event.currentTarget;
    if (!(target instanceof HTMLElement)) return;
    const clientX = 'clientX' in event ? (event as MouseEvent).clientX : 0;
    const rect = target.getBoundingClientRect();
    const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
    audio.currentTime = ratio * audio.duration;
    this.audioProgress.set(ratio * 100);
    this.audioCurrentTime.set(this.formatAudioTime(audio.currentTime));
  }

  /**
   * Abre a imagem em visualização com zoom.
   * @param event Evento de clique na imagem renderizada.
   */
  async openImageZoom(event: Event): Promise<void> {
    const url = this.resolvedUrl();
    if (!url) {
      return;
    }

    if (typeof window === 'undefined') {
      return;
    }

    const target = event.currentTarget;
    if (!(target instanceof HTMLImageElement)) {
      return;
    }

    const width = target.naturalWidth > 0 ? target.naturalWidth : undefined;
    const height = target.naturalHeight > 0 ? target.naturalHeight : undefined;

    this.destroyImageLightbox();

    const { default: PhotoSwipeLightbox } = await import('photoswipe/lightbox');

    const { dataSource, index } = this.resolveGalleryData(url, width, height);

    const lightbox = new PhotoSwipeLightbox({
      dataSource,
      pswpModule: () => import('photoswipe'),
      showHideAnimationType: 'zoom',
      bgOpacity: 0.9,
    });

    lightbox.on('destroy', () => {
      this.imageLightbox = null;
    });

    lightbox.init();
    lightbox.loadAndOpen(index);
    this.imageLightbox = lightbox;
  }

  /**
   * Resolve datasource e índice inicial da galeria para o PhotoSwipe.
   */
  private resolveGalleryData(
    currentUrl: string,
    width?: number,
    height?: number,
  ): {
    dataSource: { src: string; width?: number; height?: number; alt?: string }[];
    index: number;
  } {
    const normalized = this.galleryItems
      .filter((item) => item.src.trim().length > 0)
      .map((item) => ({
        id: item.id,
        src: item.src,
        alt: item.alt ?? 'Imagem do chat',
      }));

    const currentId = this.galleryActiveId;
    const currentFromGalleryIndex =
      currentId === null ? -1 : normalized.findIndex((item) => item.id === currentId);

    if (currentFromGalleryIndex === -1 && normalized.length === 0) {
      return {
        dataSource: [
          {
            src: currentUrl,
            width,
            height,
            alt: this.fileName ?? 'Imagem do chat',
          },
        ],
        index: 0,
      };
    }

    const dataSource: { src: string; width?: number; height?: number; alt?: string }[] =
      normalized.map((item) => ({
        src: item.src,
        alt: item.alt,
      }));

    if (currentFromGalleryIndex > -1) {
      dataSource[currentFromGalleryIndex] = {
        src: currentUrl,
        width,
        height,
        alt: this.fileName ?? dataSource[currentFromGalleryIndex]?.alt ?? 'Imagem do chat',
      };

      return {
        dataSource,
        index: currentFromGalleryIndex,
      };
    }

    const currentByUrlIndex = dataSource.findIndex((item) => item.src === currentUrl);

    return {
      dataSource,
      index: currentByUrlIndex > -1 ? currentByUrlIndex : 0,
    };
  }

  /**
   * Callback disparado quando uma imagem termina de carregar.
   */
  onLoad(): void {
    this.isLoaded.set(true);
  }

  /**
   * Callback disparado quando o player de áudio ou vídeo está pronto para reprodução.
   */
  onMediaReady(): void {
    this.isLoaded.set(true);
  }

  /**
   * Calcular a duração formatada (MM:SS) a partir dos metadados da mídia.
   * @returns {string} Duração formatada.
   */
  metadataDuration(): string {
    const durationValue = (this.metadata as { duration?: number | string } | null)?.duration;
    const duration = Number(durationValue);
    if (!duration || Number.isNaN(duration)) {
      return '00:00';
    }
    const minutes = Math.floor(duration / 60);
    const seconds = Math.floor(duration % 60);
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
  }

  /**
   * Força o download do documento sem abrir o arquivo no navegador.
   * Usa fetch + blob para contornar Content-Disposition: inline do servidor.
   */
  openDocument(): void {
    const url = this.resolvedUrl();
    if (!url || typeof window === 'undefined') return;
    this.forceDownload(url, this.cleanFileName());
  }

  /**
   * Força download via fetch+blob, ignorando Content-Disposition do servidor.
   * @param {string} url - URL do arquivo a baixar.
   * @param {string} name - Nome sugerido para o arquivo salvo.
   */
  private forceDownload(url: string, name: string): void {
    fetch(url)
      .then((res) => res.blob())
      .then((blob) => {
        const blobUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = name;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(blobUrl), 10_000);
      })
      .catch(() => {
        // fallback: abre em nova aba se fetch falhar (CORS, etc.)
        window.open(url, '_blank', 'noopener,noreferrer');
      });
  }

  /**
   * Extrai o nome real do arquivo removendo prefixos de hash gerados pelo WhatsApp/storage.
   * Ex: "5514996448268:AC84620A5EE65AF1....pdf" → "AC84620A5EE65AF1....pdf"
   * Ex: "hash_ABC123_relatorio.pdf" → "relatorio.pdf"
   * @returns {string} Nome legível do arquivo.
   */
  cleanFileName(): string {
    const name = this.fileName;
    if (!name) return this.type === 'document' ? 'Documento' : 'Arquivo';

    // Remove prefixo de telefone/ID separado por ":" (ex: "5514996448268:ABC.pdf" → "ABC.pdf")
    const afterColon = name.includes(':') ? name.slice(name.indexOf(':') + 1) : name;

    // Remove prefixos de hash separados por "_" se o restante tiver extensão
    const parts = afterColon.split('_');
    const withExt = parts.find((p) => p.includes('.'));
    const cleaned = withExt ?? afterColon;

    return cleaned.trim() || afterColon;
  }

  /**
   * Retorna nome legível do arquivo, ocultando hashes e IDs longos.
   * @returns {string} Nome amigável do arquivo.
   * @deprecated Use cleanFileName() para exibição.
   */
  displayFileName(): string {
    const name = this.cleanFileName();
    if (name.length > 40) {
      const ext = name.lastIndexOf('.') > -1 ? name.slice(name.lastIndexOf('.')) : '';
      return name.slice(0, 30) + '…' + ext;
    }
    return name;
  }

  /**
   * Obter o nome do ícone correspondente ao tipo de mídia para exibição no estado de carregamento.
   * @returns {string} Nome do ícone Lucide.
   */
  getIconName(): string {
    switch (this.type) {
      case 'image':
      case 'sticker':
        return 'lucideImage';
      case 'video':
      case 'ptv':
        return 'lucidePlay';
      case 'audio':
      case 'ptt':
        return 'lucideMusic';
      default:
        return 'lucideFile';
    }
  }

  /**
   * Configurar e iniciar a observação da visibilidade do elemento no viewport.
   * @private
   */
  private setupObserver(): void {
    if (!this.mediaContainer?.nativeElement) return;

    this.observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            this.isVisible.set(true);
            this.requestMedia();
            this.disconnectObserver();
          }
        });
      },
      { rootMargin: '80px' },
    );

    this.observer.observe(this.mediaContainer.nativeElement);
  }

  /**
   * Desconectar do observador de interseção.
   * @private
   */
  private disconnectObserver(): void {
    if (this.observer) {
      this.observer.disconnect();
      this.observer = null;
    }
  }

  /**
   * Destroi a instância de lightbox ativa para evitar vazamento de memória.
   */
  private destroyImageLightbox(): void {
    if (!this.imageLightbox) {
      return;
    }

    this.imageLightbox.destroy();
    this.imageLightbox = null;
  }

  /**
   * Start a requestAnimationFrame loop to track audio playback progress.
   * @param audio The HTMLAudioElement to track.
   */
  private startAudioTracking(audio: HTMLAudioElement): void {
    this.stopAudioTracking();
    const tick = (): void => {
      if (!audio.paused && audio.duration) {
        this.audioProgress.set((audio.currentTime / audio.duration) * 100);
        this.audioCurrentTime.set(this.formatAudioTime(audio.currentTime));
      }
      this.audioAnimationId = requestAnimationFrame(tick);
    };
    this.audioAnimationId = requestAnimationFrame(tick);
  }

  /** Stop the audio progress tracking animation loop. */
  private stopAudioTracking(): void {
    if (this.audioAnimationId !== null) {
      cancelAnimationFrame(this.audioAnimationId);
      this.audioAnimationId = null;
    }
  }

  /**
   * Format seconds into M:SS display string.
   * @param seconds Playback time in seconds.
   * @returns Formatted time string.
   */
  private formatAudioTime(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
  }

  /**
   * Solicitar o carregamento dos dados binários da mídia através do serviço de mediaLoader.
   * @private
   */
  private requestMedia(): void {
    if (this.hasRequestedLoad) {
      return;
    }

    if (!this.calledId || !this.messageId) {
      this.hasError.set(true);
      return;
    }

    const mediaType = this.type ?? 'image';
    const isTempMessage = String(this.messageId).startsWith('temp-');
    if (isTempMessage && this.fallbackUrl) {
      this.resolvedUrl.set(this.fallbackUrl);
      this.hasRequestedLoad = true;
      if (this.shouldAutoMarkLoaded(mediaType)) {
        this.isLoaded.set(true);
      }
      return;
    }

    this.hasRequestedLoad = true;
    this.mediaLoader
      .load(this.calledId, this.messageId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (url) => {
          if (!url) {
            this.hasError.set(true);
            return;
          }
          this.resolvedUrl.set(url);
          if (this.shouldAutoMarkLoaded(mediaType)) {
            this.isLoaded.set(true);
          }
        },
        error: () => {
          this.hasError.set(true);
        },
      });
  }

  private shouldAutoMarkLoaded(mediaType: ChatMessageMediaComponent['type']): boolean {
    return !['image', 'audio', 'video', 'ptt', 'ptv'].includes(mediaType);
  }
}
