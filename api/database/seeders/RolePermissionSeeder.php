<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthRole;
use Illuminate\Database\Seeder;

/**
 * Assigns default permissions to tenant-scoped roles.
 *
 * Roles: Inquilino (master/owner), Gerente (manager), Atendente (agent).
 * This seeder is idempotent — safe to run multiple times.
 */
final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $inquilino = AuthRole::query()->where('id', AuthRole::INQUILINO_ID)->first();
        $gerente = AuthRole::query()->where('id', AuthRole::GERENTE_ID)->first();
        $atendente = AuthRole::query()->where('id', AuthRole::ATENDENTE_ID)->first();

        // ── inquilino — master / owner of the tenant ──────────────────
        $inquilino->syncPermissions([
            // Chat
            'chat.called.view',
            'chat.tickets.manage',
            'chat.messages.update',
            'chat.messages.delete',
            'chat.transmission_lists.view',
            'chat.transmission_lists.create',
            'chat.transmission_lists.update',
            'chat.transmission_lists.delete',
            // Channels
            'chat.channel.view',
            'chat.channel.manage',
            // Routing
            'chat.routing.view',
            'chat.routing.manage',
            // Templates
            'chat.templates.manage',
            // CRM
            'crm.contact.view',
            'crm.company.view',
            'crm.negotiation.view',
            'crm.event.view',
            'crm.event.manage',
            'crm.funnel.view',
            'crm.funnel.manage',
            'crm.product.view',
            'crm.product.manage',
            'crm.proposal.view',
            'crm.proposal.manage',
            'crm.note.manage',
            'crm.task.manage',
            'crm.import.manage',
            // Reports - FULL mode (PLAN-005)
            'reports.chat.volume',
            'reports.chat.agent_performance',
            'reports.crm.funnel',
            'reports.crm.salesperson_performance',
            'reports.crm.loss_reason',
            'reports.crm.contact_crm',
            'reports.ai.autopilot_performance',
            'reports.ai.sentiment',
            'reports.ai.usage_cost',
            'reports.billing.revenue',
            'reports.sla.resolution',
            'reports.csat_nps',
            'reports.export',
            // Billing
            'billing.view',
            'billing.plan.manage',
            // Settings
            'settings.general.view',
            'departments.department.view',
            'settings.tags.manage',
            // Users
            'users.user.view',
            'users.user.manage',
            'users.role.view',
            // AI
            'ai.autopilot.view',
            'ai.autopilot.manage',
            'ai.knowledge.view',
            'ai.knowledge.manage',
        ]);

        // ── gerente — manager: CRM + Chat + Reports, no user management ─
        $gerente->syncPermissions([
            // Chat
            'chat.called.view',
            'chat.tickets.manage',
            'chat.messages.update',
            'chat.transmission_lists.view',
            'chat.transmission_lists.create',
            'chat.transmission_lists.update',
            'chat.transmission_lists.delete',
            // Channels
            'chat.channel.view',
            // Routing
            'chat.routing.view',
            // Templates
            'chat.templates.manage',
            // CRM
            'crm.contact.view',
            'crm.company.view',
            'crm.negotiation.view',
            'crm.event.view',
            'crm.funnel.view',
            'crm.product.view',
            'crm.proposal.view',
            'crm.note.manage',
            'crm.task.manage',
            // Reports - ADVANCED mode (PLAN-005)
            'reports.chat.volume',
            'reports.chat.agent_performance',
            'reports.crm.funnel',
            'reports.crm.salesperson_performance',
            'reports.crm.loss_reason',
            'reports.crm.contact_crm',
            'reports.ai.autopilot_performance',
            'reports.ai.sentiment',
            // Settings
            'settings.general.view',
            'departments.department.view',
            'settings.tags.manage',
            // AI (read-only)
            'ai.autopilot.view',
            'ai.knowledge.view',
        ]);

        // ── atendente — agent: basic operation only ───────────────────
        $atendente->syncPermissions([
            // Chat
            'chat.called.view',
            'chat.tickets.manage',
            'chat.messages.update',
            // CRM
            'crm.contact.view',
            'crm.product.view',
            // Reports - BASIC mode (PLAN-005)
            'reports.chat.volume',
        ]);
    }
}
