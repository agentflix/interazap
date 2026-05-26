/** Modelos e tipos do componente de mídia de mensagem do chat. */

/** Item de galeria de mídia exibido no lightbox. */
export interface ChatMediaGalleryItem {
  id: string;
  /** URL da mídia a exibir. */
  src: string;
  /** Texto alternativo para acessibilidade. */
  alt?: string;
}
