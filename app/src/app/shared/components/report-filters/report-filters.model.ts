/**
 * Models and types for report-filters component.
 */

/** Payload emitted when filters are applied */
export interface AfReportFilterPayload {
  startDate: string;
  endDate: string;
  granularity?: 'day' | 'week' | 'month';
  channel?: 'whatsapp' | 'telegram' | 'webchat' | undefined;
  status?: string;
}
