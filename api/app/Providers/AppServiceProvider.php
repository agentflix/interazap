<?php

declare(strict_types=1);

namespace App\Providers;

use Domain\Ai\Contracts\AiChunkingServiceInterface;
use Domain\Ai\Contracts\AiEmbeddingServiceInterface;
use Domain\Ai\Contracts\AiGuardianServiceInterface;
use Domain\Ai\Contracts\AiPromptHashServiceInterface;
use Domain\Ai\Contracts\AiRagServiceInterface;
use Domain\Ai\Contracts\AiStorageLimitServiceInterface;
use Domain\Ai\Observers\TenantPromptObserver;
use Domain\Ai\Services\AiChunkingService;
use Domain\Ai\Services\AiEmbeddingService;
use Domain\Ai\Services\AiGuardianService;
use Domain\Ai\Services\AiPromptHashService;
use Domain\Ai\Services\AiRagService;
use Domain\Ai\Services\AiStorageLimitService;
use Domain\Auth\Models\AuthPersonalAccessToken;
use Domain\Auth\Repositories\AuthUserRepository;
use Domain\Auth\Repositories\EloquentAuthUserRepository;
use Domain\Chat\Repositories\ChatInstanceRepository;
use Domain\Chat\Repositories\EloquentChatInstanceRepository;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Contracts\ActivityBroadcastService;
use Domain\Shared\Http\Middleware\TraceIdMiddleware;
use Domain\Shared\Infrastructure\Gateway\GatewayHttpClient;
use Domain\Shared\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuthUserRepository::class, EloquentAuthUserRepository::class);
        $this->app->bind(ChatInstanceRepository::class, EloquentChatInstanceRepository::class);
        $this->app->bind(ActivityBroadcastService::class, ChatActivityBroadcastService::class);
        $this->app->singleton(GatewayHttpClient::class);

        // AI Services - Prompt Governance
        $this->app->singleton(AiGuardianService::class, static fn (): AiGuardianService => AiGuardianService::fromConfig());
        $this->app->bind(AiGuardianServiceInterface::class, AiGuardianService::class);
        $this->app->bind(AiPromptHashServiceInterface::class, AiPromptHashService::class);

        // AI Services - RAG / Knowledge Base
        $this->app->bind(AiChunkingServiceInterface::class, AiChunkingService::class);
        $this->app->bind(AiEmbeddingServiceInterface::class, AiEmbeddingService::class);
        $this->app->bind(AiStorageLimitServiceInterface::class, AiStorageLimitService::class);
        $this->app->bind(AiRagServiceInterface::class, AiRagService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRateLimiters();

        Queue::createPayloadUsing(static function (): array {
            $traceId = Log::sharedContext()[TraceIdMiddleware::CONTEXT_KEY] ?? null;
            $tenantId = TenantContext::get();

            if (! $traceId && ! app()->runningInConsole() && app()->bound('request')) {
                /** @var Request $request */
                $request = app('request');
                $traceId = $request->attributes->get(TraceIdMiddleware::CONTEXT_KEY);
            }

            if (! $tenantId && ! app()->runningInConsole() && app()->bound('request')) {
                /** @var Request $request */
                $request = app('request');
                $tenantId = $request->attributes->get('tenant_id');
            }

            return array_filter([
                'trace_id' => $traceId,
                'tenant_id' => $tenantId,
            ], static fn ($value): bool => $value !== null);
        });

        Event::listen(JobProcessing::class, static function (JobProcessing $event): void {
            $payload = $event->job->payload();
            if (isset($payload['trace_id'])) {
                Log::shareContext([
                    TraceIdMiddleware::CONTEXT_KEY => $payload['trace_id'],
                ]);
            }

            if (array_key_exists('tenant_id', $payload)) {
                TenantContext::push($payload['tenant_id']);
            } else {
                TenantContext::push(null);
            }
        });

        Event::listen([JobProcessed::class, JobFailed::class], static function (): void {
            Log::flushSharedContext();
            TenantContext::pop();
        });

        Sanctum::usePersonalAccessTokenModel(AuthPersonalAccessToken::class);

        Gate::before(static function ($user): ?bool {
            if (is_object($user) && method_exists($user, 'isInquilino') && $user->isInquilino()) {
                return true;
            }

            return null;
        });

        // Register Observers
        PlatformTenant::observe(TenantPromptObserver::class);

        // Register Cache Invalidation Observer
        $cacheObserver = app(\Domain\Shared\Observers\CacheInvalidationObserver::class);
        PlatformTenant::observe($cacheObserver);
        \Domain\Platform\Models\PlatformPlan::observe($cacheObserver);
        \Domain\CRM\Models\CRMNegotiationFunnel::observe($cacheObserver);
        \Domain\Chat\Models\ChatQuickAnswer::observe($cacheObserver);
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(60)->by((string) ($request->user()?->id ?: $request->ip() ?: 'guest-api')));

        RateLimiter::for('login', static function (Request $request): Limit {
            $email = strtolower((string) $request->input('email', 'guest@example.com'));
            $fingerprint = $request->ip() ?: 'unknown-ip';

            return Limit::perMinute(5)->by($email.'|'.$fingerprint);
        });

        RateLimiter::for('webhooks', static function (Request $request): Limit {
            $token = (string) ($request->route('token') ?? $request->input('token') ?? 'global-webhook');

            return Limit::perMinute(100)->by($token ?: ($request->ip() ?: 'webhook'));
        });

        RateLimiter::for('public', static fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip() ?: 'public'));

        RateLimiter::for('observability', static fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip() ?: 'observability'));

        RateLimiter::for('chat', static fn (Request $request): Limit => Limit::perMinute(180)->by((string) ($request->user()?->id ?: $request->ip() ?: 'guest-chat')));

        RateLimiter::for('ai-knowledge-bulk', static fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip() ?: 'ai-knowledge-bulk'));

        RateLimiter::for('webchat', static fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip() ?: 'webchat'));
    }
}
