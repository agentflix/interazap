import { type Called } from '@core/services/called.service';
import { type CalledMessage } from '@core/services/called-message.service';

export interface ChatStoreState {
  tickets: Map<string, Called>;
  messages: Map<string, CalledMessage[]>;
  selectedTicketId: string | null;
  ticketListFilters: {
    status?: 'open' | 'pending' | 'in_progress' | 'closed';
    search?: string;
  };
  countsBaseline: {
    all: number;
    pending: number;
    open: number;
    closed: number;
    in_progress?: number;
  };
  countsDelta: {
    all: number;
    pending: number;
    open: number;
    closed: number;
    in_progress?: number;
  };
  loadingSelectedTicket: boolean;
  loadingMessages: boolean;
}

export interface ChatRealtimeAdapterEvent {
  type: string;
  data: unknown;
  timestamp: number;
}
