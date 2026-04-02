import { type Routes } from '@angular/router';
import { permissionGuard } from '../../core/guards/permission.guard';

/** Rotas filhas do módulo de relatórios. */
export const REPORT_ROUTES: Routes = [
  { path: '', redirectTo: 'sales-funnel', pathMatch: 'full' },
  {
    path: 'sales-funnel',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/sales-funnel-report/sales-funnel-report').then(
        (m) => m.SalesFunnelReportComponent,
      ),
    data: { title: 'Funil de Vendas', permission: 'reports.crm.view' },
  },
  {
    path: 'revenue-sales',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/revenue-sales-report/revenue-sales-report').then(
        (m) => m.RevenueSalesReportComponent,
      ),
    data: { title: 'Receita e Vendas', permission: 'reports.crm.view' },
  },
  {
    path: 'salesperson-performance',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/salesperson-performance-report/salesperson-performance-report').then(
        (m) => m.SalespersonPerformanceReportComponent,
      ),
    data: { title: 'Performance de Vendedores', permission: 'reports.crm.view' },
  },
  {
    path: 'loss-reasons',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/loss-reason-report/loss-reason-report').then(
        (m) => m.LossReasonReportComponent,
      ),
    data: { title: 'Motivos de Perda', permission: 'reports.crm.view' },
  },
  {
    path: 'sla-resolution',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/sla-resolution-report/sla-resolution-report').then(
        (m) => m.SlaResolutionReportComponent,
      ),
    data: { title: 'SLA e Resolução', permission: 'reports.chat.view' },
  },
  {
    path: 'agent-performance',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/agent-performance-report/agent-performance-report').then(
        (m) => m.AgentPerformanceReportComponent,
      ),
    data: { title: 'Performance de Agentes', permission: 'reports.chat.view' },
  },
  {
    path: 'csat-nps',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/csat-nps-report/csat-nps-report').then((m) => m.CsatNpsReportComponent),
    data: { title: 'CSAT / NPS', permission: 'reports.chat.view' },
  },
  {
    path: 'chat-volume',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/chat-volume-report/chat-volume-report').then(
        (m) => m.ChatVolumeReportComponent,
      ),
    data: { title: 'Volume de Chat', permission: 'reports.chat.view' },
  },
  {
    path: 'ai-usage-cost',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/ai-usage-cost-report/ai-usage-cost-report').then(
        (m) => m.AiUsageCostReportComponent,
      ),
    data: { title: 'Uso e Custo IA', permission: 'reports.ai.view' },
  },
  {
    path: 'media-transcription',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/media-transcription-report/media-transcription-report').then(
        (m) => m.MediaTranscriptionReportComponent,
      ),
    data: { title: 'Transcrição de Mídia', permission: 'reports.ai.view' },
  },
  {
    path: 'billing',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/billing-report/billing-report').then((m) => m.BillingReportComponent),
    data: { title: 'Faturamento', permission: 'reports.billing.view' },
  },
  {
    path: 'product-performance',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/product-performance-report/product-performance-report').then(
        (m) => m.ProductPerformanceReportComponent,
      ),
    data: { title: 'Performance de Produtos', permission: 'reports.crm.view' },
  },
  {
    path: 'autopilot-performance',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/autopilot-performance-report/autopilot-performance-report').then(
        (m) => m.AutopilotPerformanceReportComponent,
      ),
    data: { title: 'Automações', permission: 'reports.ai.view' },
  },
  {
    path: 'team-activity',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/team-activity-report/team-activity-report').then(
        (m) => m.TeamActivityReportComponent,
      ),
    data: { title: 'Atividade da Equipe', permission: 'reports.admin.view' },
  },
  {
    path: 'contact-crm',
    canActivate: [permissionGuard],
    loadComponent: () =>
      import('./components/contact-crm-report/contact-crm-report').then(
        (m) => m.ContactCrmReportComponent,
      ),
    data: { title: 'Contatos CRM', permission: 'reports.crm.view' },
  },
];
