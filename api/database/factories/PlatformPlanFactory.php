<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformReportsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlatformPlan>
 */
class PlatformPlanFactory extends Factory
{
    protected $model = PlatformPlan::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'id' => (string) Str::orderedUuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'limit_users' => 5,
            'storage_mode' => PlatformStorageMode::LIMITED,
            'storage_limit_bytes' => 1024 * 1024 * 1024,
            'ai_enabled' => false,
            'chat_channels_limit' => 1,
            'negotiations_mode' => PlatformNegotiationsMode::LIMITED,
            'negotiations_limit' => 50,
            'reports_mode' => PlatformReportsMode::BASIC,
            'price_monthly' => 99.0,
            'is_active' => true,
        ];
    }
}
