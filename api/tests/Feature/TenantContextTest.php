<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Http\Middleware\TenantContextMiddleware;
use Domain\Shared\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery as m;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    TenantContext::clear();
});

afterEach(function (): void {
    TenantContext::clear();
    m::close();
});

it('sets tenant context via middleware and returns header', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
    $request = Request::create('/api/test', 'GET');
    $request->setUserResolver(static fn () => $user);

    $middleware = new TenantContextMiddleware;
    $response = $middleware->handle($request, static fn () => response()->json(['ok' => true]));

    expect($response->headers->get('X-Tenant-ID'))->toBe($tenant->id);
    expect($request->attributes->get('tenant_id'))->toBe($tenant->id);
    expect(TenantContext::get())->toBeNull();
});

it('blocks cross-tenant requests when header does not match user', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

    $request = Request::create('/api/test', 'GET', [], [], [], [
        'HTTP_X_TENANT_ID' => (string) Str::orderedUuid(),
    ]);
    $request->setUserResolver(static fn () => $user);

    Log::spy();
    $middleware = new TenantContextMiddleware;

    $this->expectException(HttpException::class);
    $this->expectExceptionMessage('Tenant mismatch detected');

    $middleware->handle($request, static fn () => response()->json(['ok' => true]));
});

it('filters eloquent queries using tenant context without auth', function (): void {
    $tenantA = PlatformTenant::factory()->create();
    $tenantB = PlatformTenant::factory()->create();

    ChatTicket::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
    ChatTicket::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

    TenantContext::set($tenantA->id);
    $visible = ChatTicket::query()->pluck('tenant_id')->unique()->all();
    expect($visible)->toBe([$tenantA->id]);

    TenantContext::set($tenantB->id);
    $otherVisible = ChatTicket::query()->pluck('tenant_id')->unique()->all();
    expect($otherVisible)->toBe([$tenantB->id]);
});

it('falls back to authenticated user tenant when context is empty', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
    ChatTicket::factory()->count(2)->create(['tenant_id' => $tenant->id]);
    ChatTicket::factory()->create(['tenant_id' => PlatformTenant::factory()->create()->id]);

    $this->be($user);
    TenantContext::clear();

    $visible = ChatTicket::query()->pluck('tenant_id')->unique()->all();
    expect($visible)->toBe([$tenant->id]);
});

it('restores tenant context for queued jobs', function (): void {
    $tenant = PlatformTenant::factory()->create();

    $job = m::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('payload')->andReturn([
        'trace_id' => 'trace-test',
        'tenant_id' => $tenant->id,
    ]);
    $job->shouldReceive('resolveName')->andReturn('TestJob');
    $job->shouldReceive('getQueue')->andReturn('default');

    event(new JobProcessing('sync', $job));
    expect(TenantContext::get())->toBe($tenant->id);

    event(new JobProcessed('sync', $job, []));
    expect(TenantContext::get())->toBeNull();
});
