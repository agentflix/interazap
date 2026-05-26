/**
 * Modelos e tipos do componente de filtros de relatório.
 */

/** Payload emitido quando os filtros são aplicados. */
export interface AfReportFilterPayload {
  startDate: string;
  endDate: string;
  granularity?: 'day' | 'week' | 'month';
  channel?: 'whatsapp' | 'telegram' | 'webchat' | undefined;
  status?: string;
}
