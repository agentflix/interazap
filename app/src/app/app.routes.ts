import { type Routes } from '@angular/router';
import { MainLayoutComponent } from './layout/main-layout/main-layout';
import { AuthLayoutComponent } from './layout/auth-layout/auth-layout';
import { adminGuard } from './core/guards/admin.guard';
import { authChildGuard, authGuard } from './core/guards/auth.guard';
import { permissionGuard } from './core/guards/permission.guard';
import { aiFeatureGuard } from './core/guards/ai-feature.guard';
import { autoReplyAvailabilityGuard } from './core/guards/auto-reply-availability.guard';
import { Starter } from './pages/platform/starter/starter';

export const routes: Routes = [
  {
    path: 'chat/evaluations/:token',
    loadComponent: () => import('./pages/public/chat-evaluation/chat-evaluation'),
    data: { title: 'Avaliação de Atendimento' },
  },
  {
    path: 'proposal/:token',
    loadComponent: () => import('./pages/public/proposal-view/proposal-view'),
    data: { title: 'Proposta' },
  },
  {
    path: '',
    component: AuthLayoutComponent,
    children: [
      {
        path: '',
        pathMatch: 'full',
        redirectTo: 'auth/login',
      },
      {
        path: 'login',
        loadComponent: () => import('./pages/auth/login/login'),
        data: { title: 'Login' },
      },
      {
        path: 'auth/login',
        loadComponent: () => import('./pages/auth/login/login'),
        data: { title: 'Login' },
      },
      {
        path: 'forgot-password',
        loadComponent: () => import('./pages/auth/reset-password/reset-password'),
        data: { title: 'Forgot Password' },
      },
      {
        path: 'auth/reset-password',
        loadComponent: () => import('./pages/auth/reset-password/reset-password'),
        data: { title: 'Forgot Password' },
      },
      {
        path: 'reset-password',
        loadComponent: () => import('./pages/auth/create-password/create-password'),
        data: { title: 'Reset Password' },
      },
      {
        path: 'auth/create-password',
        loadComponent: () => import('./pages/auth/create-password/create-password'),
        data: { title: 'Reset Password' },
      },
    ],
  },
  {
    path: 'ui-kit',
    loadComponent: () => import('./pages/ui-kit/ui-kit'),
  },
  {
    path: 'billing/lockout',
    loadComponent: () => import('./pages/billing/lockout/lockout'),
    data: { title: 'Acesso Bloqueado' },
  },
  {
    path: 'access-denied',
    loadComponent: () =>
      import('./pages/auth/access-denied/access-denied').then((m) => m.AccessDeniedComponent),
    data: { title: 'Acesso Negado' },
  },
  {
    path: '',
    component: MainLayoutComponent,
    canActivate: [authGuard],
    canActivateChild: [authChildGuard],
    canMatch: [authGuard],
    children: [
      {
        path: '',
        loadComponent: () => import('./pages/dashboard/dashboard'),
        data: { title: 'Dashboard' },
      },
      {
        path: 'dashboard',
        loadComponent: () => import('./pages/dashboard/dashboard'),
        data: { title: 'Dashboard' },
      },
      {
        path: 'pages/starter',
        component: Starter,
        data: { title: 'Starter Page' },
      },
      {
        path: 'platform/tenants',
        loadComponent: () => import('./pages/platform/tenants/tenants').then((m) => m.Tenants),
        data: { title: 'Inquilinos' },
      },
      {
        path: 'platform/plans',
        loadComponent: () =>
          import('./pages/platform/plans/plan-list/plan-list').then((m) => m.PlanListComponent),
        data: { title: 'Planos' },
      },
      {
        path: 'platform/invoices',
        canActivate: [adminGuard],
        loadComponent: () =>
          import('./pages/platform/invoices/platform-invoices').then((m) => m.PlatformInvoices),
        data: { title: 'Faturas da Plataforma' },
      },
      {
        path: 'platform/uazapi-instances',
        loadComponent: () =>
          import('./pages/platform/uazapi-instances/uazapi-instances').then(
            (m) => m.UazapiInstances,
          ),
        data: { title: 'Instâncias UaZapi' },
      },
      {
        path: 'platform/users',
        loadComponent: () =>
          import('./pages/platform/users/platform-users').then((m) => m.PlatformUsers),
        data: { title: 'Usuários' },
      },
      // ─── Platform AI Governance ────────────────────────────────────────────
      {
        path: 'platform/ai-governance',
        canActivate: [adminGuard, permissionGuard],
        data: { adminPermission: 'ai.prompts.manage', permission: 'ai.prompts.manage' },
        loadComponent: () => import('./pages/platform/ai-governance/ai-governance'),
        children: [
          { path: '', redirectTo: 'master-prompts', pathMatch: 'full' },
          {
            path: 'master-prompts',
            loadComponent: () =>
              import('./pages/platform/ai-governance/prompt-masters/prompt-masters'),
            data: { title: 'Master Prompts' },
          },
          {
            path: 'segments',
            loadComponent: () =>
              import('./pages/platform/ai-governance/prompt-segments/prompt-segments'),
            data: { title: 'Segmentos IA' },
          },
          {
            path: 'plan-prompts',
            loadComponent: () => import('./pages/platform/ai-governance/prompt-plans/prompt-plans'),
            data: { title: 'Planos IA' },
          },
          {
            path: 'quarantine',
            loadComponent: () =>
              import('./pages/platform/ai-governance/prompt-quarantine/prompt-quarantine'),
            data: { title: 'Quarentena de Prompts' },
          },
        ],
      },
      // ─── Settings ─────────────────────────────────────────────────────────
      {
        path: 'settings/tags',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/crm/tags/tags'),
        data: { title: 'Etiquetas', permission: 'settings.tags.manage' },
      },
      {
        path: 'settings/departments',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/crm/departments/departments'),
        data: { title: 'Departamentos', permission: 'departments.department.view' },
      },
      {
        path: 'settings/opening-hours',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/crm/opening-hours/opening-hours'),
        data: { title: 'Horário de Atendimento', permission: 'settings.general.view' },
      },
      {
        path: 'settings/my-plan',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/settings/my-plan/my-plan'),
        data: { title: 'Meu Plano', permission: 'billing.view' },
      },
      {
        path: 'settings/media-transcription',
        canActivate: [aiFeatureGuard, permissionGuard],
        loadComponent: () =>
          import('./pages/configuration/media-transcription/media-transcription'),
        data: { title: 'Transcrição de Mídia', permission: 'ai.autopilots.manage' },
      },
      // ─── CRM ──────────────────────────────────────────────────────────────
      {
        path: 'crm/contacts',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/crm/contacts/crm-contacts'),
        data: { title: 'Contatos', permission: 'crm.contact.view' },
      },
      {
        path: 'crm/companies',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/crm/companies/crm-companies'),
        data: { title: 'Empresas', permission: 'crm.company.view' },
      },
      {
        path: 'crm/funnels',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/crm/funnels/funnels').then((m) => m.Funnels),
        data: { title: 'Funis', permission: 'crm.funnel.view' },
      },
      {
        path: 'crm/negotiations',
        canActivate: [permissionGuard],
        loadComponent: () =>
          import('./pages/crm/negotiations/negotiations').then((m) => m.Negotiations),
        data: { title: 'Negociações', permission: 'crm.negotiation.view' },
      },
      {
        path: 'crm/agenda',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/crm/agenda/agenda').then((m) => m.Agenda),
        data: { title: 'Agenda', permission: 'crm.event.view' },
      },
      {
        path: 'crm/negotiation-tasks',
        canActivate: [permissionGuard],
        loadComponent: () =>
          import('./pages/crm/negotiation-tasks/negotiation-tasks').then((m) => m.NegotiationTasks),
        data: { title: 'Tarefas', permission: 'crm.task.manage' },
      },
      {
        path: 'crm/negotiations/:id',
        canActivate: [permissionGuard],
        loadComponent: () =>
          import('./pages/crm/negotiation-show/negotiation-show').then((m) => m.NegotiationShow),
        data: { title: 'Negociação', permission: 'crm.negotiation.view' },
      },
      {
        path: 'crm/products-services',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/crm/products-services/crm-products-services'),
        data: { title: 'Produtos e Serviços', permission: 'crm.product.view' },
      },
      {
        path: 'crm/reason-losses',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/crm/reason-losses/crm-reason-losses'),
        data: { title: 'Motivos de Perda', permission: 'crm.negotiation.view' },
      },
      // ─── Billing ──────────────────────────────────────────────────────────
      {
        path: 'billing/invoices',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/billing/invoices/invoices'),
        data: { title: 'Faturas', permission: 'billing.view' },
      },
      // ─── Admin Monitoring ─────────────────────────────────────────────────
      {
        path: 'admin/queues',
        canActivate: [adminGuard, permissionGuard],
        loadComponent: () => import('./pages/admin/queue-dashboard/queue-dashboard'),
        data: { title: 'Dashboard de Filas', permission: 'users.role.manage' },
      },
      {
        path: 'admin/queues/dlq',
        canActivate: [adminGuard, permissionGuard],
        loadComponent: () => import('./pages/admin/dead-letter-queue/dead-letter-queue'),
        data: { title: 'Dead Letter Queue', permission: 'users.role.manage' },
      },
      {
        path: 'admin/queues/circuits',
        canActivate: [adminGuard, permissionGuard],
        loadComponent: () => import('./pages/admin/circuit-breaker-status/circuit-breaker-status'),
        data: { title: 'Circuit Breakers', permission: 'users.role.manage' },
      },
      // ─── Chat Transmission Lists ────────────────────────────────────────────────────
      {
        path: 'chat/ticket/:ticketId',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/chat/ticket/ticket-detail-page'),
        data: { title: 'Detalhes do Atendimento', permission: 'chat.called.view' },
      },
      {
        path: 'chat/ticket',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/chat/ticket/ticket-page'),
        data: { title: 'Gestão de Atendimentos', permission: 'chat.called.view' },
      },
      {
        path: 'chat/auto-reply',
        canActivate: [permissionGuard, autoReplyAvailabilityGuard],
        loadComponent: () => import('./pages/chat/auto-reply/auto-reply').then((m) => m.AutoReply),
        data: { title: 'Resposta Automática', permission: 'chat.called.view' },
      },
      {
        path: 'chat/quick-answers',
        canActivate: [permissionGuard],
        loadComponent: () =>
          import('./pages/chat/quick-answers/quick-answers').then((m) => m.QuickAnswers),
        data: { title: 'Respostas Rápidas', permission: 'chat.called.view' },
      },
      {
        path: 'chat/transmission-list/new',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/chat/transmission-list/chat-transmission-list-form'),
        data: { title: 'Nova Lista de Transmissão', permission: 'chat.transmission_lists.view' },
      },
      {
        path: 'chat/transmission-list/:id',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/chat/transmission-list/chat-transmission-list-form'),
        data: { title: 'Editar Lista de Transmissão', permission: 'chat.transmission_lists.view' },
      },
      {
        path: 'chat/transmission-list',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/chat/transmission-list/chat-transmission-list'),
        data: { title: 'Listas de Transmissão', permission: 'chat.transmission_lists.view' },
      },
      {
        path: 'chat/channel',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/chat/channel').then((m) => m.ChannelPage),
        data: { title: 'Canais', permission: 'chat.channel.view' },
      },
      {
        path: 'chat',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/chat/chat').then((m) => m.Chat),
        data: { title: 'Chat', permission: 'chat.called.view' },
        children: [{ path: ':calledId', children: [] }],
      },
      // ─── Settings Auth ─────────────────────────────────────────────────────
      {
        path: 'settings/users',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/auth/users/users').then((m) => m.SettingsUsers),
        data: { title: 'Usuários', permission: 'users.user.view' },
      },
      {
        path: 'platform/roles',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/auth/roles/roles').then((m) => m.Roles),
        data: { title: 'Perfis de Acesso', permission: 'reports.admin.view' },
      },
      {
        path: 'settings/profile',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/auth/profile/profile').then((m) => m.Profile),
        data: { title: 'Meu Perfil', permission: 'settings.general.view' },
      },
      {
        path: 'settings/two-factor',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/auth/two-factor/two-factor').then((m) => m.TwoFactor),
        data: { title: '2FA', permission: 'settings.general.view' },
      },
      {
        path: 'settings/preferences',
        canActivate: [permissionGuard],
        loadChildren: () =>
          import('./pages/auth/preferences/preferences.routes').then((m) => [m.default]),
        data: { title: 'Preferências', permission: 'settings.general.view' },
      },
      {
        path: 'settings/tenant',
        canActivate: [permissionGuard],
        loadChildren: () =>
          import('./pages/platform/tenant-settings/tenant-settings.routes').then((m) => [
            m.default,
          ]),
        data: { title: 'Configurações do Inquilino', permission: 'platform.tenants.manage' },
      },
      // ─── AI Module ────────────────────────────────────────────────────────
      {
        path: 'ai/agents',
        canActivate: [aiFeatureGuard],
        loadComponent: () =>
          import('./pages/ai/pages/agents/agent-list/agent-list').then((m) => m.AgentListComponent),
        data: { title: 'Agentes IA' },
      },
      {
        path: 'ai/agents/new',
        canActivate: [aiFeatureGuard],
        loadComponent: () =>
          import('./pages/ai/pages/agents/agent-form/agent-form').then((m) => m.AgentFormComponent),
        data: { title: 'Novo Agente' },
      },
      {
        path: 'ai/agents/:id/edit',
        canActivate: [aiFeatureGuard],
        loadComponent: () =>
          import('./pages/ai/pages/agents/agent-workspace/agent-workspace').then(
            (m) => m.AgentWorkspaceComponent,
          ),
        data: { title: 'Configurar Agente' },
      },
      {
        path: 'ai/knowledge',
        redirectTo: 'ai/knowledge/base',
        pathMatch: 'full',
      },
      {
        path: 'ai/knowledge/dashboard',
        canActivate: [aiFeatureGuard],
        loadComponent: () =>
          import('./pages/ai/pages/knowledge/knowledge-dashboard/knowledge-dashboard').then(
            (m) => m.KnowledgeDashboardComponent,
          ),
        data: { title: 'Dashboard' },
      },
      {
        path: 'ai/knowledge/search',
        canActivate: [aiFeatureGuard],
        loadComponent: () =>
          import('./pages/ai/pages/knowledge/knowledge-search/knowledge-search').then(
            (m) => m.KnowledgeSearchComponent,
          ),
        data: { title: 'Pesquisa' },
      },
      {
        path: 'ai/knowledge/base',
        canActivate: [aiFeatureGuard],
        loadComponent: () =>
          import('./pages/ai/pages/knowledge/knowledge-list/knowledge-list').then(
            (m) => m.KnowledgeListComponent,
          ),
        data: { title: 'Base de Conhecimento' },
      },
      {
        path: 'ai/knowledge/upload',
        canActivate: [aiFeatureGuard],
        loadComponent: () =>
          import('./pages/ai/pages/knowledge/knowledge-upload/knowledge-upload').then(
            (m) => m.KnowledgeUploadComponent,
          ),
        data: { title: 'Upload de Documento' },
      },
      {
        path: 'ai/knowledge/:id',
        canActivate: [aiFeatureGuard],
        loadComponent: () =>
          import('./pages/ai/pages/knowledge/knowledge-detail/knowledge-detail').then(
            (m) => m.KnowledgeDetailComponent,
          ),
        data: { title: 'Detalhe do Documento' },
      },
      {
        path: 'ai/prompts',
        canActivate: [aiFeatureGuard],
        loadComponent: () =>
          import('./pages/ai/pages/prompts/prompt-editor/prompt-editor').then(
            (m) => m.PromptEditorComponent,
          ),
        data: { title: 'Editor de Prompts' },
      },
      {
        path: 'ai/simulator',
        canActivate: [aiFeatureGuard],
        loadComponent: () =>
          import('./pages/ai/simulator/simulator').then((m) => m.AiSimulatorComponent),
        data: { title: 'Simulador IA' },
      },
      {
        path: 'ai/dashboard',
        canActivate: [aiFeatureGuard],
        loadComponent: () =>
          import('./pages/ai/pages/dashboard/usage-dashboard/usage-dashboard').then(
            (m) => m.UsageDashboardComponent,
          ),
        data: { title: 'Dashboard' },
      },
      // ─── Reports ────────────────────────────────────────────────────────
      {
        path: 'reports',
        canActivate: [permissionGuard],
        loadComponent: () => import('./pages/reports/reports'),
        loadChildren: () => import('./pages/reports/reports.routes').then((m) => m.REPORT_ROUTES),
        data: { title: 'Relatórios' },
      },
    ],
  },
  {
    path: '**',
    redirectTo: '',
  },
];
