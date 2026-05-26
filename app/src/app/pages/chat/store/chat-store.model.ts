import { type Called } from '@core/services/called.service';
import { type CalledMessage } from '@core/services/called-message.service';

/** Estado interno do ChatStore principal (lista de tickets + mensagens + filtros). */
export interface ChatStoreState {
  /** Mapa de tickets indexados por ID. */
  tickets: Map<string, Called>;
  /** Mapa de mensagens indexadas por ID do ticket. */
  messages: Map<string, CalledMessage[]>;
  selectedTicketId: string | null;
  ticketListFilters: {
    status?: 'open' | 'pending' | 'in_progress' | 'closed';
    search?: string;
  };
  /** Contagens base carregadas da API. */
  countsBaseline: {
    all: number;
    pending: number;
    open: number;
    closed: number;
    in_progress?: number;
  };
  /** Variações acumuladas via eventos realtime desde o último carregamento. */
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

/** Evento normalizado pelo adaptador realtime para processamento em lote no ChatStore. */
export interface ChatRealtimeAdapterEvent {
  type: string;
  data: unknown;
  timestamp: number;
}
