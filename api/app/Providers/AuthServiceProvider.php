<?php

declare(strict_types=1);

namespace App\Providers;

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiPromptMaster;
use Domain\Ai\Models\AiPromptPlan;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Ai\Policies\AiAgentPolicy;
use Domain\Ai\Policies\AiPromptMasterPolicy;
use Domain\Ai\Policies\AiPromptPlanPolicy;
use Domain\Ai\Policies\AiPromptSegmentPolicy;
use Domain\Ai\Policies\AiPromptTenantPolicy;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Auth\Models\AuthDeviceToken;
use Domain\Auth\Policies\AuthDeviceTokenPolicy;
use Domain\Auth\Policies\AuthRolePolicy;
use Domain\Auth\Policies\AuthUserPolicy;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Policies\BillingInvoicePolicy;
use Domain\Chat\Models\ChatAutoReplyRule;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Chat\Models\ChatQuickAnswer;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTransmissionList;
use Domain\Chat\Policies\ChatAutoReplyRulePolicy;
use Domain\Chat\Policies\ChatInstancePolicy;
use Domain\Chat\Policies\ChatMessagePolicy;
use Domain\Chat\Policies\ChatMessageTemplatePolicy;
use Domain\Chat\Policies\ChatQuickAnswerPolicy;
use Domain\Chat\Policies\ChatTicketPolicy;
use Domain\Chat\Policies\ChatTransmissionListPolicy;
use Domain\Configuration\Models\ConfigurationNotificationPreference;
use Domain\Configuration\Models\ConfigurationOpeningHour;
use Domain\Configuration\Policies\ConfigurationNotificationPreferencePolicy;
use Domain\Configuration\Policies\ConfigurationOpeningHourPolicy;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMCustomField;
use Domain\CRM\Models\CRMDepartment;
use Domain\CRM\Models\CRMEvent;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNote;
use Domain\CRM\Models\CRMProduct;
use Domain\CRM\Models\CRMProposal;
use Domain\CRM\Models\CRMReasonLoss;
use Domain\CRM\Models\CRMTag;
use Domain\CRM\Policies\CRMCompanyPolicy;
use Domain\CRM\Policies\CRMContactPolicy;
use Domain\CRM\Policies\CRMCustomFieldPolicy;
use Domain\CRM\Policies\CRMDepartmentPolicy;
use Domain\CRM\Policies\CRMEventPolicy;
use Domain\CRM\Policies\CRMFunnelPolicy;
use Domain\CRM\Policies\CRMNegotiationPolicy;
use Domain\CRM\Policies\CRMNotePolicy;
use Domain\CRM\Policies\CRMProductPolicy;
use Domain\CRM\Policies\CRMProposalPolicy;
use Domain\CRM\Policies\CRMReasonLossPolicy;
use Domain\CRM\Policies\CRMTagPolicy;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Models\PlatformUazapiInstance;
use Domain\Platform\Policies\PlatformBillingInvoicePolicy;
use Domain\Platform\Policies\PlatformPlanPolicy;
use Domain\Platform\Policies\PlatformTenantPolicy;
use Domain\Platform\Policies\PlatformUazapiInstancePolicy;
use Domain\Shared\Models\SharedMedia;
use Domain\Shared\Policies\SharedMediaPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Auth
        AuthDeviceToken::class => AuthDeviceTokenPolicy::class,
        AuthRole::class => AuthRolePolicy::class,
        AuthUser::class => AuthUserPolicy::class,

        // Billing
        BillingInvoice::class => BillingInvoicePolicy::class,

        // Chat
        ChatTicket::class => ChatTicketPolicy::class,
        ChatMessage::class => ChatMessagePolicy::class,
        ChatMessageTemplate::class => ChatMessageTemplatePolicy::class,
        ChatQuickAnswer::class => ChatQuickAnswerPolicy::class,
        ChatAutoReplyRule::class => ChatAutoReplyRulePolicy::class,
        ChatTransmissionList::class => ChatTransmissionListPolicy::class,
        ChatInstance::class => ChatInstancePolicy::class,

        // Configuration
        ConfigurationNotificationPreference::class => ConfigurationNotificationPreferencePolicy::class,
        ConfigurationOpeningHour::class => ConfigurationOpeningHourPolicy::class,

        // CRM
        CRMCompany::class => CRMCompanyPolicy::class,
        CRMContact::class => CRMContactPolicy::class,
        CRMCustomField::class => CRMCustomFieldPolicy::class,
        CRMDepartment::class => CRMDepartmentPolicy::class,
        CRMEvent::class => CRMEventPolicy::class,
        CRMNegotiation::class => CRMNegotiationPolicy::class,
        CRMNegotiationFunnel::class => CRMFunnelPolicy::class,
        CRMNote::class => CRMNotePolicy::class,
        CRMProduct::class => CRMProductPolicy::class,
        CRMProposal::class => CRMProposalPolicy::class,
        CRMReasonLoss::class => CRMReasonLossPolicy::class,
        CRMTag::class => CRMTagPolicy::class,

        // AI
        AiAgent::class => AiAgentPolicy::class,
        AiPromptMaster::class => AiPromptMasterPolicy::class,
        AiPromptSegment::class => AiPromptSegmentPolicy::class,
        AiPromptPlan::class => AiPromptPlanPolicy::class,
        AiPromptTenant::class => AiPromptTenantPolicy::class,

        // Platform
        PlatformPlan::class => PlatformPlanPolicy::class,
        PlatformTenant::class => PlatformTenantPolicy::class,
        PlatformUazapiInstance::class => PlatformUazapiInstancePolicy::class,

        // Shared
        SharedMedia::class => SharedMediaPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(static function (AuthUser $user): ?bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            return null;
        });

        Gate::define('reports.viewCrm', [\Domain\Reports\Policies\ReportsPolicy::class, 'viewCrm']);
        Gate::define('reports.viewChat', [\Domain\Reports\Policies\ReportsPolicy::class, 'viewChat']);
        Gate::define('reports.viewAi', [\Domain\Reports\Policies\ReportsPolicy::class, 'viewAi']);
        Gate::define('reports.viewBilling', [\Domain\Reports\Policies\ReportsPolicy::class, 'viewBilling']);
        Gate::define('reports.viewAdmin', [\Domain\Reports\Policies\ReportsPolicy::class, 'viewAdmin']);
        Gate::define('reports.export', [\Domain\Reports\Policies\ReportsPolicy::class, 'export']);
        Gate::define('platform.billing.invoice.delete', [PlatformBillingInvoicePolicy::class, 'delete']);
        Gate::define('search', static fn (AuthUser $user): bool => $user->exists);
    }
}
