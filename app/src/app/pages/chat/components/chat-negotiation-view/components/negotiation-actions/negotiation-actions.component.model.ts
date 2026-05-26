/** Modelos e tipos do componente de ações da negociação. */

/** Dados editáveis da negociação selecionada. */
export interface NegotiationDetailForm {
  funnelId: string;
  stepId: string;
  expectedClose: string;
}

/** Dados do formulário de criação de tarefa. */
export interface NegotiationTaskForm {
  title: string;
  actionType: string;
  dueDate: string;
  startTime: string;
  endTime: string;
  notifyChannel: NotifyChannel;
}

export type NotifyChannel = 'whatsapp' | 'email' | 'push' | 'none';
