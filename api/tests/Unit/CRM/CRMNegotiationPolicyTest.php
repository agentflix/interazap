<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Policies\CRMNegotiationPolicy;

test('crm negotiation policy requires tenant for viewAny', function (): void {
    $policy = app(CRMNegotiationPolicy::class);

    $userWithoutTenant = new AuthUser;
    $userWithoutTenant->tenant_id = null;

    $userWithTenant = new AuthUser;
    $userWithTenant->tenant_id = 'tenant-1';

    expect($policy->viewAny($userWithoutTenant))->toBeFalse();
    expect($policy->viewAny($userWithTenant))->toBeTrue();
});

test('crm negotiation policy enforces tenant match on view', function (): void {
    $policy = app(CRMNegotiationPolicy::class);

    $user = new AuthUser;
    $user->tenant_id = 'tenant-a';

    $negotiationSameTenant = new CRMNegotiation;
    $negotiationSameTenant->tenant_id = 'tenant-a';

    $negotiationOtherTenant = new CRMNegotiation;
    $negotiationOtherTenant->tenant_id = 'tenant-b';

    expect($policy->view($user, $negotiationSameTenant))->toBeTrue();
    expect($policy->view($user, $negotiationOtherTenant))->toBeFalse();
});
