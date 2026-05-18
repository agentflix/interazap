import type { Called } from '@core/models/called.model';
import type { CalledMessage } from '@core/models/called-message.model';

export type ComposerMode = 'free' | 'mixed' | 'template-only';

export interface ChatState {
  selectedCalledId: string | null;
  selectedCalled: Called | null;
  isLoadingCalled: boolean;
  isSending: boolean;
  replyingTo: CalledMessage | null;
  isMediaBatchSending: boolean;
}
