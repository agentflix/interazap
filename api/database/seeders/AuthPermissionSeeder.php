<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AuthPermissionSeeder extends Seeder
{
    /**
     * Base permissions used across modules (align with menu/guards).
     *
     * @var array<int, string>
     */
    private array $permissions = [
        'users.user.view',
        'users.user.manage',
        'users.role.view',
        'users.role.manage',
        'chat.called.view',
        'chat.channel.view',
        'crm.contact.view',
        'crm.negotiation.view',
        'crm.company.view',
        'reports.view',
        'reports.crm.view',
        'reports.chat.view',
        'reports.ai.view',
        'reports.billing.view',
        'reports.admin.view',
        'reports.export',
        // Reports - granular (PLAN-005)
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
        'billing.view',
        'billing.plan.manage',
        'platform.plans.manage',
        'platform.tenants.manage',
        'settings.general.view',
        'departments.department.view',
        'settings.tags.manage',
        'ai.autopilot.view',
        'ai.autopilot.manage',
        'ai.knowledge.view',
        'ai.knowledge.manage',
        'ai.prompts.view',
        'ai.prompts.manage',
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
        'chat.channel.manage',
        'chat.tickets.manage',
        'chat.messages.update',
        'chat.messages.delete',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $name) {
            AuthPermission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::uuid()]
            );
        }
    }
}
