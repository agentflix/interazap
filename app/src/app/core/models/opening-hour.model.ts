/**
 * Representa uma configuração de horário de atendimento para um dia da semana.
 *
 * Contexto: usado no módulo de Configurações (settings/opening-hours) para
 * definir os horários em que a empresa aceita novos atendimentos.
 * Também é respeitado por regras de auto-reply com `respect_business_hours = true`.
 */
export interface OpeningHour {
  id: string;
  company_id: string;
  /** Dia da semana (0 = domingo, 6 = sábado). */
  day_of_week: number;
  /** Horário de abertura no formato HH:mm. */
  open_time: string;
  /** Horário de fechamento no formato HH:mm. */
  close_time: string;
  /** Indica se este dia de atendimento está ativo. */
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * Resposta da API para um único horário de atendimento.
 */
export interface OpeningHourResponse {
  success: boolean;
  message?: string;
  data: OpeningHour;
}

/**
 * Resposta da API para listagem de horários de atendimento.
 */
export interface OpeningHourListResponse {
  success: boolean;
  data: {
    opening_hours: OpeningHour[];
  };
}

/**
 * Payload para atualização em lote dos horários de atendimento.
 *
 * Contexto: enviado no endpoint de atualização em massa para salvar
 * todos os dias da semana de uma única vez.
 */
export interface BulkUpdateOpeningHoursRequest {
  opening_hours: {
    day_of_week: number;
    open_time: string;
    close_time: string;
    is_active: boolean;
  }[];
}
