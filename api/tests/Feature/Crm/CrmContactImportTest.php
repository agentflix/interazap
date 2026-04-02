<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Models\PlatformUazapiInstance;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
});

test('can upload csv for import', function (): void {
    Storage::fake('local');
    $csv = UploadedFile::fake()->createWithContent('contacts.csv', "name,number,email\nJohn Doe,+5511999999999,john@example.com\nJane Doe,+5511888888888,jane@example.com");

    $response = $this->postJson('/api/crm/contacts/import/upload', [
        'file' => $csv,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['path', 'import_id', 'rows']]);

    // import_id should be relative (not absolute path)
    $importId = $response->json('data.import_id');
    expect($importId)->not->toStartWith('/');
    expect($importId)->toContain($this->tenant->id);
});

test('import creates contacts', function (): void {
    // Create connected Uazapi instance for tenant
    PlatformUazapiInstance::factory()->connected()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    // Mock external HTTP calls
    Http::fake([
        '*' => Http::response(['contacts' => []], 200),
    ]);

    $csvContent = "name,number,email\nJohn Doe,+5511999999999,john@example.com\nJane Doe,+5511888888888,jane@example.com";
    $absolutePath = storage_path('app/imports/contacts.csv');

    if (! is_dir(dirname($absolutePath))) {
        mkdir(dirname($absolutePath), 0755, true);
    }
    file_put_contents($absolutePath, $csvContent);

    $payload = [
        'import_id' => $absolutePath,
        'mapping' => [
            'name' => 'name',
            'number' => 'number',
            'email' => 'email',
        ],
    ];

    $response = $this->postJson('/api/crm/contacts/import', $payload);

    $response->assertOk()
        ->assertJsonFragment(['imported' => 2]);

    $this->assertDatabaseHas('crm_contacts', [
        'tenant_id' => $this->tenant->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $this->assertDatabaseHas('crm_contacts', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
});

test('import handles duplicates', function (): void {
    // Create connected Uazapi instance for tenant
    PlatformUazapiInstance::factory()->connected()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    // Mock external HTTP calls - return John Doe's number as already existing remotely
    Http::fake([
        '*' => Http::response(['contacts' => [['number' => '5511999999999']]], 200),
    ]);

    $csvContent = "name,number,email\nJohn Doe,+5511999999999,john@example.com\nJane Doe,+5511888888888,jane@example.com";
    $absolutePath = storage_path('app/imports/contacts_dup.csv');

    if (! is_dir(dirname($absolutePath))) {
        mkdir(dirname($absolutePath), 0755, true);
    }
    file_put_contents($absolutePath, $csvContent);

    $payload = [
        'import_id' => $absolutePath,
        'mapping' => [
            'name' => 'name',
            'number' => 'number',
            'email' => 'email',
        ],
        'skip_duplicates' => true,
    ];

    $response = $this->postJson('/api/crm/contacts/import', $payload);

    $response->assertOk()
        ->assertJsonFragment(['imported' => 1, 'skipped' => 1]);
});

test('import validates required fields', function (): void {
    $csvContent = "name\nJohn Doe\nJane Doe";
    $absolutePath = storage_path('app/imports/contacts_no_num.csv');

    if (! is_dir(dirname($absolutePath))) {
        mkdir(dirname($absolutePath), 0755, true);
    }
    file_put_contents($absolutePath, $csvContent);

    $payload = [
        'import_id' => $absolutePath,
        'mapping' => [
            'name' => 'name',
        ],
    ];

    $response = $this->postJson('/api/crm/contacts/import', $payload);

    $response->assertStatus(422);
});
