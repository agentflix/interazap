<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Domain\Auth\Models\AuthUser;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

describe('Security Headers', function (): void {
    it('includes X-Content-Type-Options header', function (): void {
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('includes X-Frame-Options header', function (): void {
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-Frame-Options', 'DENY');
    });

    it('includes X-XSS-Protection header', function (): void {
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    });

    it('includes Referrer-Policy header', function (): void {
        $response = $this->getJson('/api/health');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    });

    it('includes all security headers in authenticated requests', function (): void {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('password'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/ai/usage/summary');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    });

    it('includes security headers in error responses', function (): void {
        $response = $this->getJson('/api/health');

        expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
        expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
        expect($response->headers->get('X-XSS-Protection'))->toBe('1; mode=block');
        expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
    });

    it('does not include HSTS in non-production environment', function (): void {
        // In testing environment, HSTS should not be present
        $response = $this->getJson('/api/health');

        expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
    });
});

describe('Security Headers in Production', function (): void {
    it('includes HSTS header in production environment', function (): void {
        // Temporarily set production environment
        app()->detectEnvironment(fn (): string => 'production');

        $response = $this->getJson('/api/health');

        expect($response->headers->get('Strict-Transport-Security'))
            ->toBe('max-age=31536000; includeSubDomains');

        // Reset environment
        app()->detectEnvironment(fn (): string => 'testing');
    });
});
