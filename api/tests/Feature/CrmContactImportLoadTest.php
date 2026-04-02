<?php

declare(strict_types=1);

use Domain\CRM\Actions\CRMContactImportActions;
use Domain\CRM\DTOs\CRMContactImportDTO;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMContactPhone;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Models\PlatformUazapiInstance;
use Domain\Platform\Services\UazapiGatewayService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

it('processes 5000 rows with 50% duplicates', function (): void {
    $tenant = PlatformTenant::factory()->create();

    $instance = PlatformUazapiInstance::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenant->id,
        'name' => 'Uazapi Test',
        'system_name' => 'uazapi-test',
        'token' => 'test-token',
        'status' => 'connected',
        'last_status_at' => now(),
    ]);

    $uazapi = Mockery::mock(UazapiGatewayService::class);
    $uazapi->shouldReceive('listContacts')->andReturn([]);
    $uazapi->shouldReceive('syncContactsList')->andReturn([]);

    $actions = new CRMContactImportActions($uazapi);

    $dir = storage_path('app/imports/contacts');
    File::ensureDirectoryExists($dir);
    $filePath = $dir.'/load-test.csv';

    $handle = fopen($filePath, 'w');
    fputcsv($handle, ['name', 'phone', 'email', 'company']);

    $duplicates = 2500;
    for ($i = 0; $i < 5000; $i++) {
        $phone = '5511'.str_pad((string) $i, 8, '0', STR_PAD_LEFT);
        $name = 'Contato '.$i;
        fputcsv($handle, [$name, $phone, 'email'.$i.'@mail.com', 'Empresa '.$i]);

        if ($i < $duplicates) {
            $contact = CRMContact::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'name' => $name,
                'email' => 'email'.$i.'@mail.com',
                'phone' => $phone,
                'whatsapp' => $phone,
                'is_active' => true,
            ]);

            CRMContactPhone::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'crm_contact_id' => $contact->id,
                'label' => 'mobile',
                'phone_e164' => $phone,
                'is_primary' => true,
                'valid_from' => now(),
            ]);
        }
    }
    fclose($handle);

    $dto = new CRMContactImportDTO(
        tenantId: $tenant->id,
        filePath: $filePath,
        mapping: [
            'name' => 'name',
            'number' => 'phone',
            'email' => 'email',
            'company' => 'company',
        ],
        delimiter: ',',
        hasHeader: true,
        instanceId: $instance->id,
    );

    $summary = $actions->process($dto);

    expect($summary['processed'])->toBe(5000)
        ->and($summary['skipped'])->toBe($duplicates)
        ->and($summary['imported'])->toBe(5000 - $duplicates)
        ->and($summary['failed'])->toBe(0);
})->group('slow');
