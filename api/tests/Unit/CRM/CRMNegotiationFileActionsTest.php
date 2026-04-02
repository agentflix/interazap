<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Actions\CRMNegotiationFileActions;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFile;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->negotiation = CRMNegotiation::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actions = new CRMNegotiationFileActions;

    Storage::fake('public');
});

describe('CRMNegotiationFileActions', function (): void {
    describe('list', function (): void {
        it('returns files for negotiation', function (): void {
            CRMNegotiationFile::factory()->count(3)->create([
                'tenant_id' => $this->tenant->id,
                'crm_negotiation_id' => $this->negotiation->id,
            ]);

            $result = $this->actions->list($this->tenant->id, $this->negotiation->id);

            expect($result)->toHaveCount(3);
        });

        it('excludes files from other negotiations', function (): void {
            $otherNegotiation = CRMNegotiation::factory()->create(['tenant_id' => $this->tenant->id]);

            CRMNegotiationFile::factory()->count(2)->create([
                'tenant_id' => $this->tenant->id,
                'crm_negotiation_id' => $this->negotiation->id,
            ]);

            CRMNegotiationFile::factory()->count(3)->create([
                'tenant_id' => $this->tenant->id,
                'crm_negotiation_id' => $otherNegotiation->id,
            ]);

            $result = $this->actions->list($this->tenant->id, $this->negotiation->id);

            expect($result)->toHaveCount(2);
        });

        it('throws exception for non-existent negotiation', function (): void {
            $fakeNegotiationId = (string) \Illuminate\Support\Str::orderedUuid();

            $this->actions->list($this->tenant->id, $fakeNegotiationId);
        })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        it('orders files by created_at descending', function (): void {
            CRMNegotiationFile::factory()->create([
                'tenant_id' => $this->tenant->id,
                'crm_negotiation_id' => $this->negotiation->id,
                'name' => 'older.pdf',
                'created_at' => now()->subDay(),
            ]);

            CRMNegotiationFile::factory()->create([
                'tenant_id' => $this->tenant->id,
                'crm_negotiation_id' => $this->negotiation->id,
                'name' => 'newer.pdf',
                'created_at' => now(),
            ]);

            $result = $this->actions->list($this->tenant->id, $this->negotiation->id);

            expect($result->first()->name)->toBe('newer.pdf')
                ->and($result->last()->name)->toBe('older.pdf');
        });
    });

    describe('create', function (): void {
        it('uploads file and creates record', function (): void {
            $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

            $result = $this->actions->create(
                $this->tenant->id,
                $this->user->id,
                $this->negotiation->id,
                $file
            );

            expect($result)->toBeInstanceOf(CRMNegotiationFile::class)
                ->and($result->name)->toBe('document.pdf')
                ->and($result->tenant_id)->toBe($this->tenant->id)
                ->and($result->crm_negotiation_id)->toBe($this->negotiation->id)
                ->and($result->auth_user_id)->toBe($this->user->id)
                ->and($result->mime_type)->toBe('application/pdf');

            Storage::disk('public')->assertExists($result->path);
        });

        it('stores file in correct directory', function (): void {
            $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

            $result = $this->actions->create(
                $this->tenant->id,
                $this->user->id,
                $this->negotiation->id,
                $file
            );

            expect($result->path)->toContain("tenants/{$this->tenant->id}/crm/negotiations/{$this->negotiation->id}");
        });

        it('can create file without user', function (): void {
            $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

            $result = $this->actions->create(
                $this->tenant->id,
                null,
                $this->negotiation->id,
                $file
            );

            expect($result->auth_user_id)->toBeNull();
        });

        it('throws exception for non-existent negotiation', function (): void {
            $fakeNegotiationId = (string) \Illuminate\Support\Str::orderedUuid();
            $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

            $this->actions->create($this->tenant->id, $this->user->id, $fakeNegotiationId, $file);
        })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        it('records correct file size', function (): void {
            $file = UploadedFile::fake()->create('large.pdf', 500, 'application/pdf');

            $result = $this->actions->create(
                $this->tenant->id,
                $this->user->id,
                $this->negotiation->id,
                $file
            );

            expect($result->size)->toBe($file->getSize());
        });
    });

    describe('delete', function (): void {
        it('deletes file record and storage', function (): void {
            $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

            $createdFile = $this->actions->create(
                $this->tenant->id,
                $this->user->id,
                $this->negotiation->id,
                $file
            );

            $path = $createdFile->path;

            Storage::disk('public')->assertExists($path);

            $this->actions->delete($this->tenant->id, $this->negotiation->id, $createdFile->id);

            expect(\Domain\CRM\Models\CRMNegotiationFile::query()->find($createdFile->id))->toBeNull();
            Storage::disk('public')->assertMissing($path);
        });

        it('throws exception for non-existent file', function (): void {
            $fakeFileId = (string) \Illuminate\Support\Str::orderedUuid();

            $this->actions->delete($this->tenant->id, $this->negotiation->id, $fakeFileId);
        })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        it('throws exception for non-existent negotiation', function (): void {
            $file = CRMNegotiationFile::factory()->create([
                'tenant_id' => $this->tenant->id,
                'crm_negotiation_id' => $this->negotiation->id,
            ]);

            $fakeNegotiationId = (string) \Illuminate\Support\Str::orderedUuid();

            $this->actions->delete($this->tenant->id, $fakeNegotiationId, $file->id);
        })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        it('cannot delete file from other tenant', function (): void {
            $otherTenant = PlatformTenant::factory()->create();
            $otherNegotiation = CRMNegotiation::factory()->create(['tenant_id' => $otherTenant->id]);

            $file = CRMNegotiationFile::factory()->create([
                'tenant_id' => $otherTenant->id,
                'crm_negotiation_id' => $otherNegotiation->id,
            ]);

            $this->actions->delete($this->tenant->id, $otherNegotiation->id, $file->id);
        })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});
