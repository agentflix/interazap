/**
 * Sidebar menu configuration for InteraZap.
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
 * Complete sidebar menu structure for InteraZap.
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
  { type: 'item', label: 'Dashboard', link: '/dashboard', iconName: 'layout-dashboard', requiredPermission: 'dashboard.view' },

  // ── Operations ───────────────────────────────────────────────────
  { type: 'title', label: 'Operação' },
  {
    type: 'accordion',
    label: 'Chat',
    iconName: 'message-square',
    children: [
      { type: 'item', label: 'Conversas', link: '/chat', iconName: 'message-square', requiredPermission: 'chat.called.view' },
      {
        type: 'item',
        label: 'Tickets',
        link: '/chat/ticket',
        iconName: 'ticket',
        requiredPermission: 'chat.called.view',
      },
      {
        type: 'item',
        label: 'Chat Externo',
        link: '/chat/external',
        iconName: 'external-link',
      },
      {
        type: 'item',
        label: 'Resposta Automática',
        link: '/chat/auto-reply',
        iconName: 'bot',
        requiresAiDisabled: true,
        requiredPermission: 'chat.called.view',
      },
      {
        type: 'item',
        label: 'Listas de Transmissão',
        link: '/chat/transmission-list',
        iconName: 'megaphone',
        requiredPermission: 'chat.transmission_lists.view',
      },
      { type: 'item', label: 'Respostas Rápidas', link: '/chat/quick-answers', iconName: 'zap', requiredPermission: 'chat.called.view' },
      { type: 'item', label: 'Canais', link: '/chat/channel', iconName: 'cable', requiredPermission: 'chat.channel.view' },
      {
        type: 'item',
        label: 'Configuração',
        link: '/chat/configuration',
        iconName: 'settings',
        requiredPermission: 'chat.routing.manage',
      },
      {
        type: 'item',
        label: 'Templates Meta',
        link: '/chat/templates',
        iconName: 'message-square-text',
        requiredPermission: 'chat.templates.manage',
      },
    ],
  },
  {
    type: 'accordion',
    label: 'CRM',
    iconName: 'briefcase',
    children: [
      { type: 'item', label: 'Contatos', link: '/crm/contacts', iconName: 'users', requiredPermission: 'crm.contact.view' },
      { type: 'item', label: 'Empresas', link: '/crm/companies', iconName: 'building-2', requiredPermission: 'crm.company.view' },
      { type: 'item', label: 'Funis', link: '/crm/funnels', iconName: 'git-branch', requiredPermission: 'crm.funnel.view' },
      { type: 'item', label: 'Negociações', link: '/crm/negotiations', iconName: 'handshake', requiredPermission: 'crm.negotiation.view' },
      { type: 'item', label: 'Agenda', link: '/crm/agenda', iconName: 'calendar', requiredPermission: 'crm.event.view' },
      {
        type: 'item',
        label: 'Tarefas',
        link: '/crm/negotiation-tasks',
        iconName: 'clipboard-list',
        requiredPermission: 'crm.task.manage',
      },
      { type: 'item', label: 'Produtos', link: '/crm/products-services', iconName: 'package', requiredPermission: 'crm.product.view' },
      { type: 'item', label: 'Motivos de Perda', link: '/crm/reason-losses', iconName: 'circle-x', requiredPermission: 'crm.negotiation.view' },
    ],
  },
  {
    type: 'accordion',
    label: 'Faturas',
    iconName: 'credit-card',
    children: [
      { type: 'item', label: 'Faturas', link: '/billing/invoices', iconName: 'credit-card', requiredPermission: 'billing.view' },
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
    requiredPermission: 'ai.autopilots.manage',
    children: [
      {
        type: 'item',
        label: 'Dashboard de Custos',
        link: '/ai/dashboard',
        requiredPermission: 'ai.autopilots.manage',
      },
      {
        type: 'item',
        label: 'Agentes',
        link: '/ai/agents',
        requiredPermission: 'ai.autopilots.manage',
      },
      { type: 'item', label: 'Simulador', link: '/ai/simulator', requiredPermission: 'ai.autopilots.manage' },
    ],
  },
  {
    type: 'accordion',
    label: 'Base de Conhecimento',
    iconName: 'file-text',
    requiresAiEnabled: true,
    requiredPermission: 'ai.autopilots.manage',
    children: [
      {
        type: 'item',
        label: 'Dashboard',
        link: '/ai/knowledge/dashboard',
        requiredPermission: 'ai.autopilots.manage',
      },
      {
        type: 'item',
        label: 'Pesquisa',
        link: '/ai/knowledge/search',
        requiredPermission: 'ai.autopilots.manage',
      },
      {
        type: 'item',
        label: 'Documentos',
        link: '/ai/knowledge/base',
        requiredPermission: 'ai.autopilots.manage',
      },
      {
        type: 'item',
        label: 'Upload',
        link: '/ai/knowledge/upload',
        requiredPermission: 'ai.autopilots.manage',
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
      { type: 'item', label: 'Funil de Vendas', link: '/reports/sales-funnel', requiredPermission: 'reports.crm.view' },
      { type: 'item', label: 'Receita e Vendas', link: '/reports/revenue-sales', requiredPermission: 'reports.crm.view' },
      {
        type: 'item',
        label: 'Performance de Vendedores',
        link: '/reports/salesperson-performance',
        requiredPermission: 'reports.crm.view',
      },
      { type: 'item', label: 'Motivos de Perda', link: '/reports/loss-reasons', requiredPermission: 'reports.crm.view' },
      { type: 'item', label: 'SLA e Resolução', link: '/reports/sla-resolution', requiredPermission: 'reports.chat.view' },
      { type: 'item', label: 'Performance de Agentes', link: '/reports/agent-performance', requiredPermission: 'reports.chat.view' },
      { type: 'item', label: 'CSAT / NPS', link: '/reports/csat-nps', requiredPermission: 'reports.chat.view' },
      { type: 'item', label: 'Volume de Chat', link: '/reports/chat-volume', requiredPermission: 'reports.chat.view' },
      { type: 'item', label: 'Uso e Custo IA', link: '/reports/ai-usage-cost', requiredPermission: 'reports.ai.view' },
      { type: 'item', label: 'Faturamento', link: '/reports/billing', requiredPermission: 'reports.billing.view' },
      { type: 'item', label: 'Produtos', link: '/reports/product-performance', requiredPermission: 'reports.crm.view' },
      { type: 'item', label: 'Automações', link: '/reports/autopilot-performance', requiredPermission: 'reports.ai.view' },
      { type: 'item', label: 'Atividade da Equipe', link: '/reports/team-activity', requiredPermission: 'reports.admin.view' },
      { type: 'item', label: 'Contatos CRM', link: '/reports/contact-crm', requiredPermission: 'reports.crm.view' },
    ],
  },
  {
    type: 'accordion',
    label: 'Monitoramento',
    iconName: 'activity',
    children: [
      { type: 'item', label: 'Dashboard de Filas', link: '/admin/queues', iconName: 'layers', requiredPermission: 'users.role.manage' },
      {
        type: 'item',
        label: 'Dead Letter Queue',
        link: '/admin/queues/dlq',
        iconName: 'alert-triangle',
        requiredPermission: 'users.role.manage',
      },
      { type: 'item', label: 'Circuit Breakers', link: '/admin/queues/circuits', iconName: 'zap', requiredPermission: 'users.role.manage' },
    ],
  },

  // ── Settings ──────────────────────────────────────────────────────
  { type: 'title', label: 'Configurações' },
  {
    type: 'accordion',
    label: 'Configurações',
    iconName: 'settings',
    children: [
      { type: 'item', label: 'Minhas Preferências', link: '/settings/preferences', requiredPermission: 'settings.general.view' },
      { type: 'item', label: 'Horário de Atendimento', link: '/settings/opening-hours', requiredPermission: 'settings.general.view' },
      { type: 'item', label: 'Departamentos', link: '/settings/departments', requiredPermission: 'departments.department.view' },
      { type: 'item', label: 'Etiquetas', link: '/settings/tags', requiredPermission: 'settings.tags.manage' },
      { type: 'item', label: 'Usuários', link: '/settings/users', requiredPermission: 'users.user.view' },
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
    requiredPermission: 'platform.tenants.manage',
    children: [
      { type: 'item', label: 'Inquilinos', link: '/platform/tenants', requiredPermission: 'platform.tenants.manage' },
      { type: 'item', label: 'Usuários', link: '/platform/users', requiredPermission: 'platform.tenants.manage' },
      {
        type: 'item',
        label: 'Leads da Plataforma',
        link: '/platform/leads',
        requiredPermission: 'platform.tenants.manage',
      },
      { type: 'item', label: 'Planos', link: '/platform/plans', requiredPermission: 'platform.tenants.manage' },
      { type: 'item', label: 'Faturas', link: '/platform/invoices', iconName: 'receipt', requiredPermission: 'platform.tenants.manage' },
      { type: 'item', label: 'Perfis de Acesso', link: '/platform/roles', requiredPermission: 'platform.tenants.manage' },
      { type: 'item', label: 'Instâncias UaZapi', link: '/platform/uazapi-instances', requiredPermission: 'platform.tenants.manage' },
      {
        type: 'accordion',
        label: 'Governança de Prompts',
        iconName: 'shield-check',
        requiredPermission: 'ai.prompts.manage',
        children: [
          { type: 'item', label: 'Master Prompts', link: '/platform/ai-governance/master-prompts', requiredPermission: 'ai.prompts.manage' },
          { type: 'item', label: 'Segmentos', link: '/platform/ai-governance/segments', requiredPermission: 'ai.prompts.manage' },
          { type: 'item', label: 'Planos', link: '/platform/ai-governance/plan-prompts', requiredPermission: 'ai.prompts.manage' },
          { type: 'item', label: 'Quarentena', link: '/platform/ai-governance/quarantine', requiredPermission: 'ai.prompts.manage' },
        ],
      },
    ],
  },
];
