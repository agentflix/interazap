<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Models\AuditLog;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses()->group('platform', 'queue-admin');

beforeEach(function (): void {
    $this->superAdminRole = AuthRole::query()->firstOrCreate(
        ['id' => AuthRole::ADMINISTRADOR_ID, 'name' => AuthRole::ADMINISTRADOR_NAME, 'guard_name' => 'sanctum'],
    );

    $this->managerRole = AuthRole::query()->firstOrCreate(
        ['id' => AuthRole::GERENTE_ID, 'name' => AuthRole::GERENTE_NAME, 'guard_name' => 'sanctum'],
        ['id' => (string) Illuminate\Support\Str::orderedUuid()],
    );

    $this->tenant = PlatformTenant::factory()->create(['is_active' => true]);

    $this->superAdminTenant = PlatformTenant::factory()->create(['is_active' => true]);

    $this->superAdmin = AuthUser::factory()->create([
        'tenant_id' => $this->superAdminTenant->id,
        'password' => Illuminate\Support\Facades\Hash::make('super-secret'),
        'is_active' => true,
    ]);
    $this->superAdmin->assignRole($this->superAdminRole);

    $this->regularUser = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
        'password' => Illuminate\Support\Facades\Hash::make('user-secret'),
        'is_active' => true,
    ]);

    $this->gatewayUrl = 'http://localhost:3000/';
    config(['services.gateway.url' => $this->gatewayUrl]);
    config(['services.gateway.api_key' => 'test-api-key']);
});

// ── Authentication ──

test('unauthenticated user receives 401 on queue admin routes', function (): void {
    $routes = [
        ['GET', '/api/admin/queues'],
        ['GET', '/api/admin/queues/default'],
        ['POST', '/api/admin/queues/default/pause'],
        ['GET', '/api/admin/queues/dlq'],
        ['GET', '/api/admin/queues/circuits'],
    ];

    foreach ($routes as [$method, $uri]) {
        if ($method === 'GET') {
            $this->getJson($uri)->assertUnauthorized();
        } else {
            $this->postJson($uri)->assertUnauthorized();
        }
    }
});

// ── Authorization ──

test('user without permission receives 403 on queue admin routes', function (): void {
    Sanctum::actingAs($this->regularUser, abilities: ['*']);

    $routes = [
        ['GET', '/api/admin/queues'],
        ['POST', '/api/admin/queues/default/pause'],
        ['GET', '/api/admin/queues/dlq'],
        ['GET', '/api/admin/queues/circuits'],
    ];

    foreach ($routes as [$method, $uri]) {
        if ($method === 'GET') {
            $this->getJson($uri)->assertForbidden();
        } else {
            $this->postJson($uri)->assertForbidden();
        }
    }
});

test('super admin can access queue admin routes', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['success' => true], 200),
    ]);

    $this->getJson('/api/admin/queues')->assertOk();
    $this->getJson('/api/admin/queues/default')->assertOk();
    $this->getJson('/api/admin/queues/dlq')->assertOk();
    $this->getJson('/api/admin/queues/circuits')->assertOk();
});

// ── Route order: static routes before dynamic ──

test('dlq route is not captured by dynamic name route', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['jobs' => [], 'total' => 0], 200),
    ]);

    // Should hit deadLetterIndex, not show with name="dlq"
    $response = $this->getJson('/api/admin/queues/dlq');
    $response->assertOk();
    // The response should contain the DLQ structure, not a queue name
    expect($response->json('jobs'))->toBeArray();
    expect($response->json('total'))->toBe(0);
});

test('circuits route is not captured by dynamic name route', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['circuits' => []], 200),
    ]);

    $response = $this->getJson('/api/admin/queues/circuits');
    $response->assertOk();
    // The response should contain the circuits structure
    expect($response->json('circuits'))->toBeArray();
});

// ── Audit logging on mutating actions ──

test('pause queue creates audit log', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['success' => true], 200),
    ]);

    $this->postJson('/api/admin/queues/default/pause')->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'queue.paused',
        'user_id' => $this->superAdmin->id,
    ]);

    $log = AuditLog::query()->where('event', 'queue.paused')->first();
    expect($log)->not->toBeNull();
    expect($log->new_values['queue'])->toBe('default');
    expect($log->new_values['action'])->toBe('pause');
});

test('resume queue creates audit log', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['success' => true], 200),
    ]);

    $this->postJson('/api/admin/queues/default/resume')->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'queue.resumed',
        'user_id' => $this->superAdmin->id,
    ]);
});

test('clean queue creates audit log with payload', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['success' => true], 200),
    ]);

    $this->postJson('/api/admin/queues/default/clean', ['status' => 'failed'])->assertOk();

    $log = AuditLog::query()->where('event', 'queue.cleaned')->first();
    expect($log)->not->toBeNull();
    expect($log->new_values['queue'])->toBe('default');
    expect($log->new_values['action'])->toBe('clean');
    expect($log->new_values['payload'])->toBe(['status' => 'failed']);
});

test('dlq retry creates audit log', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['success' => true], 200),
    ]);

    $jobId = 'test-job-uuid';
    $this->postJson("/api/admin/queues/dlq/{$jobId}/retry")->assertOk();

    $log = AuditLog::query()->where('event', 'dlq.job_retried')->first();
    expect($log)->not->toBeNull();
    expect($log->new_values['queue'])->toBe($jobId);
    expect($log->new_values['action'])->toBe('dlq_retry');
});

test('dlq retry all creates audit log', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['success' => true], 200),
    ]);

    $this->postJson('/api/admin/queues/dlq/retry-all', ['queue' => 'default'])->assertOk();

    $log = AuditLog::query()->where('event', 'dlq.all_retried')->first();
    expect($log)->not->toBeNull();
    expect($log->new_values['action'])->toBe('dlq_retry_all');
});

test('dlq purge creates audit log', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['success' => true], 200),
    ]);

    $jobId = 'test-job-uuid';
    $this->deleteJson("/api/admin/queues/dlq/{$jobId}")->assertOk();

    $log = AuditLog::query()->where('event', 'dlq.job_purged')->first();
    expect($log)->not->toBeNull();
    expect($log->new_values['action'])->toBe('dlq_purge');
});

test('dlq purge all creates audit log', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['success' => true], 200),
    ]);

    $this->postJson('/api/admin/queues/dlq/purge-all')->assertOk();

    $log = AuditLog::query()->where('event', 'dlq.all_purged')->first();
    expect($log)->not->toBeNull();
    expect($log->new_values['action'])->toBe('dlq_purge_all');
});

test('circuit reset creates audit log', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['success' => true], 200),
    ]);

    $this->postJson('/api/admin/queues/circuits/openai/reset')->assertOk();

    $log = AuditLog::query()->where('event', 'circuit.reset')->first();
    expect($log)->not->toBeNull();
    expect($log->new_values['queue'])->toBe('openai');
    expect($log->new_values['action'])->toBe('circuit_reset');
});

test('circuit open creates audit log', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['success' => true], 200),
    ]);

    $this->postJson('/api/admin/queues/circuits/openai/open')->assertOk();

    $log = AuditLog::query()->where('event', 'circuit.forced_open')->first();
    expect($log)->not->toBeNull();
    expect($log->new_values['queue'])->toBe('openai');
    expect($log->new_values['action'])->toBe('circuit_open');
});

// ── Read-only actions do NOT create audit logs ──

test('index queues does not create audit log', function (): void {
    $before = AuditLog::query()->count();

    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['queues' => []], 200),
    ]);

    $this->getJson('/api/admin/queues')->assertOk();

    expect(AuditLog::query()->count())->toBe($before);
});

test('show queue does not create audit log', function (): void {
    $before = AuditLog::query()->count();

    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    Http::fake([
        "{$this->gatewayUrl}*" => Http::response(['name' => 'default'], 200),
    ]);

    $this->getJson('/api/admin/queues/default')->assertOk();

    expect(AuditLog::query()->count())->toBe($before);
});
