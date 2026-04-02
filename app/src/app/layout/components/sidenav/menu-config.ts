/**
 * Sidebar menu configuration for AgentFlix.
 * Defines all navigation items, grouped by section.
 *
 * To add a new menu item:
 * 1. Add the item to the appropriate section below
 * 2. Use `type: 'item'` for direct links, `type: 'accordion'` for groups
 * 3. Use `type: 'title'` for section headers
 * 4. `iconName` is a Lucide icon name (kebab-case)
 */

/**
 * Represents a single item in the sidebar menu hierarchy.
 * Can be a section title, a direct navigation link, or an accordion (expandable group).
 *
 * @example
 * ```typescript
 * // Direct link item
 * { type: 'item', label: 'Dashboard', link: '/dashboard', iconName: 'layout-dashboard' }
 *
 * // Expandable accordion
 * {
 *   type: 'accordion',
 *   label: 'CRM',
 *   iconName: 'briefcase',
 *   children: [
 *     { type: 'item', label: 'Contatos', link: '/crm/contacts', iconName: 'users' }
 *   ]
 * }
 * ```
 */
export interface SidebarMenuItem {
  type: 'title' | 'item' | 'accordion';
  label: string;
  link?: string;
  iconName?: string;
  children?: SidebarMenuItem[];
  requiresAiEnabled?: boolean;
  requiresAiDisabled?: boolean;
  requiredAiFeature?: AiFeatureFlag;
  requiredAnyAiFeatures?: AiFeatureFlag[];
  /** Permission string required to display this item. Guard is still enforced on the route. */
  requiredPermission?: string;
}

/**
 * Available AI feature flags that can be required by menu items.
 * Used with `requiredAiFeature` and `requiredAnyAiFeatures` on SidebarMenuItem.
 *
 * @example
 * ```typescript
 * const flag: AiFeatureFlag = 'ai_agents_v2';
 * ```
 */
export type AiFeatureFlag =
  | 'ai_agents_v2'
  | 'ai_prompts_governance'
  | 'ai_knowledge_base'
  | 'ai_usage_tracking';

/**
 * Complete sidebar menu structure for AgentFlix.
 * Organized by sections: Overview, Operations (Chat, CRM, Billing), AI, Administration, and Settings.
 * Some items have conditional visibility based on AI features and tenant plan.
 *
 * @example
 * ```typescript
 * // Use in a component to render the menu
 * const menu = SIDEBAR_MENU;
 * ```
 */
export const SIDEBAR_MENU: SidebarMenuItem[] = [
  // ── Overview ──────────────────────────────────────────────────────
  { type: 'title', label: 'Visão Geral' },
  { type: 'item', label: 'Dashboard', link: '/dashboard', iconName: 'layout-dashboard' },

  // ── Operations ────────────────────────────────────────────────────
  { type: 'title', label: 'Operação' },
  {
    type: 'accordion',
    label: 'Chat',
    iconName: 'message-square',
    children: [
      { type: 'item', label: 'Conversas', link: '/chat', iconName: 'message-square' },
      {
        type: 'item',
        label: 'Tickets',
        link: '/chat/ticket',
        iconName: 'ticket',
      },
      {
        type: 'item',
        label: 'Chatbot',
        link: '/chat/chatbot',
        iconName: 'bot',
        requiresAiDisabled: true,
      },
      { type: 'item', label: 'Campanhas', link: '/chat/campaigns', iconName: 'megaphone' },
      { type: 'item', label: 'Respostas Rápidas', link: '/chat/quick-answers', iconName: 'zap' },
      { type: 'item', label: 'Integrações', link: '/chat/integration', iconName: 'cable' },
    ],
  },
  {
    type: 'accordion',
    label: 'CRM',
    iconName: 'briefcase',
    children: [
      { type: 'item', label: 'Contatos', link: '/crm/contacts', iconName: 'users' },
      { type: 'item', label: 'Empresas', link: '/crm/companies', iconName: 'building-2' },
      { type: 'item', label: 'Funis', link: '/crm/funnels', iconName: 'git-branch' },
      { type: 'item', label: 'Negociações', link: '/crm/negotiations', iconName: 'handshake' },
      { type: 'item', label: 'Agenda', link: '/crm/agenda', iconName: 'calendar' },
      {
        type: 'item',
        label: 'Tarefas',
        link: '/crm/negotiation-tasks',
        iconName: 'clipboard-list',
      },
      { type: 'item', label: 'Produtos', link: '/crm/products-services', iconName: 'package' },
      { type: 'item', label: 'Motivos de Perda', link: '/crm/reason-losses', iconName: 'circle-x' },
    ],
  },
  {
    type: 'accordion',
    label: 'Faturas',
    iconName: 'credit-card',
    children: [
      { type: 'item', label: 'Faturas', link: '/billing/invoices', iconName: 'credit-card' },
    ],
  },

  // ── AI ────────────────────────────────────────────────────────────
  {
    type: 'title',
    label: 'IA',
    requiresAiEnabled: true,
  },
  {
    type: 'accordion',
    label: 'IA',
    iconName: 'brain',
    requiresAiEnabled: true,
    children: [
      {
        type: 'item',
        label: 'Dashboard de Custos',
        link: '/ai/dashboard',
      },
      {
        type: 'item',
        label: 'Agentes',
        link: '/ai/agents',
      },
      { type: 'item', label: 'Simulador', link: '/ai/simulator' },
    ],
  },
  {
    type: 'accordion',
    label: 'Base de Conhecimento',
    iconName: 'file-text',
    requiresAiEnabled: true,
    children: [
      {
        type: 'item',
        label: 'Dashboard',
        link: '/ai/knowledge/dashboard',
      },
      {
        type: 'item',
        label: 'Pesquisa',
        link: '/ai/knowledge/search',
      },
      {
        type: 'item',
        label: 'Documentos',
        link: '/ai/knowledge/base',
      },
      {
        type: 'item',
        label: 'Upload',
        link: '/ai/knowledge/upload',
      },
    ],
  },

  // ── Administration ────────────────────────────────────────────────
  { type: 'title', label: 'Administração' },
  {
    type: 'accordion',
    label: 'Relatórios',
    iconName: 'activity',
    children: [
      { type: 'item', label: 'Funil de Vendas', link: '/reports/sales-funnel' },
      { type: 'item', label: 'Receita e Vendas', link: '/reports/revenue-sales' },
      {
        type: 'item',
        label: 'Performance de Vendedores',
        link: '/reports/salesperson-performance',
      },
      { type: 'item', label: 'Motivos de Perda', link: '/reports/loss-reasons' },
      { type: 'item', label: 'SLA e Resolução', link: '/reports/sla-resolution' },
      { type: 'item', label: 'Performance de Agentes', link: '/reports/agent-performance' },
      { type: 'item', label: 'CSAT / NPS', link: '/reports/csat-nps' },
      { type: 'item', label: 'Volume de Chat', link: '/reports/chat-volume' },
      { type: 'item', label: 'Uso e Custo IA', link: '/reports/ai-usage-cost' },
      { type: 'item', label: 'Faturamento', link: '/reports/billing' },
      { type: 'item', label: 'Produtos', link: '/reports/product-performance' },
      { type: 'item', label: 'Automações', link: '/reports/autopilot-performance' },
      { type: 'item', label: 'Atividade da Equipe', link: '/reports/team-activity' },
      { type: 'item', label: 'Contatos CRM', link: '/reports/contact-crm' },
    ],
  },
  {
    type: 'accordion',
    label: 'Monitoramento',
    iconName: 'activity',
    children: [
      { type: 'item', label: 'Dashboard de Filas', link: '/admin/queues', iconName: 'layers' },
      {
        type: 'item',
        label: 'Dead Letter Queue',
        link: '/admin/queues/dlq',
        iconName: 'alert-triangle',
      },
      { type: 'item', label: 'Circuit Breakers', link: '/admin/queues/circuits', iconName: 'zap' },
    ],
  },

  // ── Settings ──────────────────────────────────────────────────────
  { type: 'title', label: 'Configurações' },
  {
    type: 'accordion',
    label: 'Configurações',
    iconName: 'settings',
    children: [
      { type: 'item', label: 'Minhas Preferências', link: '/settings/preferences' },
      { type: 'item', label: 'Horário de Atendimento', link: '/settings/opening-hours' },
      { type: 'item', label: 'Departamentos', link: '/settings/departments' },
      { type: 'item', label: 'Etiquetas', link: '/settings/tags' },
      { type: 'item', label: 'Usuários', link: '/settings/users' },
      {
        type: 'item',
        label: 'Configurações do Inquilino',
        link: '/settings/tenant',
        iconName: 'building',
        requiredPermission: 'platform.tenants.manage',
      },
    ],
  },
  {
    type: 'accordion',
    label: 'Plataforma',
    iconName: 'building-2',
    children: [
      { type: 'item', label: 'Inquilinos', link: '/platform/tenants' },
      { type: 'item', label: 'Usuários', link: '/platform/users' },
      { type: 'item', label: 'Planos', link: '/platform/plans' },
      { type: 'item', label: 'Faturas', link: '/platform/invoices', iconName: 'receipt' },
      { type: 'item', label: 'Perfis de Acesso', link: '/platform/roles' },
      { type: 'item', label: 'Instâncias UaZapi', link: '/platform/uazapi-instances' },
      {
        type: 'accordion',
        label: 'Governança de Prompts',
        iconName: 'shield-check',
        children: [
          { type: 'item', label: 'Master Prompts', link: '/platform/ai-governance/master-prompts' },
          { type: 'item', label: 'Segmentos', link: '/platform/ai-governance/segments' },
          { type: 'item', label: 'Planos', link: '/platform/ai-governance/plan-prompts' },
          { type: 'item', label: 'Quarentena', link: '/platform/ai-governance/quarantine' },
        ],
      },
    ],
  },
];
