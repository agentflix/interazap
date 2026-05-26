/**
 * Modelos e tipos do componente de exportação de relatório.
 */

/** Payload de formato de exportação. */
export interface AfReportExportPayload {
  format: 'csv' | 'xlsx';
}
