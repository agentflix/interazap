<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Http\Resources\ChatInstanceResource;
use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Http\Resources\PlatformUazapiInstanceResource;
use Domain\Platform\Models\PlatformUazapiInstance;
use Illuminate\Http\Request;

describe('Resource Output Sanitization', function (): void {
    it('ChatInstanceResource does not expose token directly', function (): void {
        $user = AuthUser::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'settings_json' => [
                'token' => 'super-secret-token-12345',
                'baseUrl' => 'https://api.example.com',
            ],
        ]);

        $resource = new ChatInstanceResource($instance);
        $data = $resource->toArray(new Request);

        // Token should NOT be present
        expect($data)->not->toHaveKey('token');

        // has_token flag should indicate presence
        expect($data)->toHaveKey('has_token');
        expect($data['has_token'])->toBeTrue();

        // Settings should not contain token
        expect($data['settings'])->not->toHaveKey('token');
    });

    it('ChatInstanceResource sanitizes settings array', function (): void {
        $user = AuthUser::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'settings_json' => [
                'token' => 'secret',
                'password' => 'secret',
                'api_key' => 'secret',
                'secret' => 'secret',
                'private_key' => 'secret',
                'access_token' => 'secret',
                'refresh_token' => 'secret',
                'baseUrl' => 'https://api.example.com',
            ],
        ]);

        $resource = new ChatInstanceResource($instance);
        $data = $resource->toArray(new Request);

        $sensitiveKeys = ['token', 'password', 'api_key', 'secret', 'private_key', 'access_token', 'refresh_token'];

        foreach ($sensitiveKeys as $key) {
            expect($data['settings'])->not->toHaveKey($key);
        }

        // Non-sensitive data should remain
        expect($data['settings'])->toHaveKey('baseUrl');
    });

    it('PlatformUazapiInstanceResource does not expose full token', function (): void {
        $user = AuthUser::factory()->create();
        $uniqueToken = 'super-secret-api-token-'.uniqid();
        $instance = PlatformUazapiInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'token' => $uniqueToken,
        ]);

        $request = new Request;
        $request->setUserResolver(fn () => $user);

        $resource = new PlatformUazapiInstanceResource($instance);
        $data = $resource->toArray($request);

        // Full token should NOT be present
        expect($data)->not->toHaveKey('token');

        // has_token flag should be present
        expect($data)->toHaveKey('has_token');
        expect($data['has_token'])->toBeTrue();

        // token_preview should only show last 4 chars (masked)
        expect($data)->toHaveKey('token_preview');
        expect(str_starts_with((string) $data['token_preview'], '****'))->toBeTrue();
    });
});
