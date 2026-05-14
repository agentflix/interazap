<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CRMTagSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        $tags = [
            'Quente',
            'Morno',
            'Frio',
            'VIP',
            'Renovação',
            'Upsell',
            'Cross-sell',
            'Prioritário',
            'Acompanhamento',
            'Em Risco',
        ];

        foreach ($tenants as $tenant) {
            foreach ($tags as $tag) {
                CRMTag::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $tag],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'color' => '#'.str_pad(dechex(random_int(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT),
                    ]
                );
            }
        }

        $this->command->info(sprintf('Tags created: %d', count($tags)));
    }
}
