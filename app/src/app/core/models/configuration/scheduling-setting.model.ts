/**
 * Configurações de agendamento do tenant.
 * Espelha a tabela `configuration_scheduling_settings` do backend.
 *
 * Contexto: usado no módulo de Configurações para gerenciar o comportamento
 * de confirmação e lembretes de eventos de agendamento via WhatsApp/Webchat.
 */

/**
 * Configuração de confirmação e notificação de eventos agendados.
 */
export interface SchedulingSettings {
  id: string;
  tenant_id: string;
  /** Antecedência em minutos para envio da confirmação do evento. */
  event_confirmation_advance_minutes: number;
  /** Indica se o envio de confirmação de evento está habilitado. */
  event_confirmation_enabled: boolean;
  /** Indica se a notificação de confirmação deve aparecer na interface. */
  event_confirmation_notify_ui: boolean;
  /** Indica se a notificação de confirmação deve ser enviada via push. */
  event_confirmation_notify_push: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * Envelope de resposta da API para configurações de agendamento.
 */
export interface SchedulingSettingsResponse {
  success: boolean;
  message: string;
  data: SchedulingSettings;
}
