<?php

declare(strict_types=1);

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

test('models should use BelongsToTenant trait unless exempted', function (): void {
    // List of models that are global and do NOT need tenant isolation
    $exemptedModels = [
        \Domain\Auth\Models\AuthUser::class,
        \Domain\Platform\Models\PlatformTenant::class,
        \Domain\Platform\Models\PlatformPlan::class,
        \Domain\Platform\Models\PlatformPlanFeature::class,
        \Domain\Configuration\Models\ConfigurationNotification::class, // Notifications might be system-wide or user-scoped? Check later.
        // Add others as needed after first run failure
    ];

    expect('Domain')
        ->classes()
        ->ignoring($exemptedModels)
        ->extending(Model::class)
        ->toUse(BelongsToTenant::class);
});
