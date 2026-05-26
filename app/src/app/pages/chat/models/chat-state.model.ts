import type { Called } from '@core/models/called.model';
import type { CalledMessage } from '@core/models/called-message.model';

/**
 * Modo do composer de mensagens.
 * - `free`: texto livre (canal não-Meta ou dentro da janela 24h)
 * - `mixed`: Meta dentro da janela — permite texto livre e template
 * - `template-only`: Meta fora da janela — somente template aprovado
 */
export type ComposerMode = 'free' | 'mixed' | 'template-only';

/** Estado do chat para o ticket selecionado atualmente. */
export interface ChatState {
  selectedCalledId: string | null;
  selectedCalled: Called | null;
  isLoadingCalled: boolean;
  isSending: boolean;
  /** Mensagem sendo citada/respondida, ou `null` se não há reply ativo. */
  replyingTo: CalledMessage | null;
  isMediaBatchSending: boolean;
}
