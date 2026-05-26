/** Fila de roteamento automático de atendimentos. */
export interface ChatRoutingQueue {
  id: string;
  tenant_id: string;
  /** ID da instância/canal vinculado, ou `null` para fila global. */
  instance_id: string | null;
  name: string;
  is_enabled: boolean;
  /** Estratégia de distribuição: rodízio, menor carga ou por habilidade. */
  strategy: 'round_robin' | 'least_busy' | 'skill_based';
  /** Limite de tickets abertos por agente (usado na estratégia `least_busy`). */
  max_open_tickets_per_agent: number | null;
  agents: ChatRoutingQueueAgent[];
  created_at: string;
  updated_at: string;
}

/** Agente pertencente a uma fila de roteamento. */
export interface ChatRoutingQueueAgent {
  id: string;
  queue_id: string;
  user_id: string;
  /** Posição na ordem de rodízio (1-based). */
  position: number;
  last_assigned_at: string | null;
  is_active: boolean;
  /** Lista de habilidades para roteamento por skill. */
  skills: string[];
  created_at: string;
  updated_at: string;
}
