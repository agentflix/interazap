export interface ChatRoutingQueue {
  id: string;
  tenant_id: string;
  instance_id: string | null;
  name: string;
  is_enabled: boolean;
  strategy: 'round_robin' | 'least_busy' | 'skill_based';
  max_open_tickets_per_agent: number | null;
  agents: ChatRoutingQueueAgent[];
  created_at: string;
  updated_at: string;
}

export interface ChatRoutingQueueAgent {
  id: string;
  queue_id: string;
  user_id: string;
  position: number;
  last_assigned_at: string | null;
  is_active: boolean;
  skills: string[];
  created_at: string;
  updated_at: string;
}
