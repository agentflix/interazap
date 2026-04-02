<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Platform\DTOs\PlatformUazapiInstanceDTO;
use Illuminate\Foundation\Http\FormRequest;
use Mockery;
use Tests\TestCase;

class PlatformUazapiInstanceDTOTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_from_request_and_to_array_with_all_fields(): void
    {
        $request = Mockery::mock(FormRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'name' => 'Main',
            'system_name' => 'uazapi',
            'config' => ['key_a' => 'a', 'key_b' => 'b'],
        ]);

        $dto = PlatformUazapiInstanceDTO::fromRequest($request);

        $this->assertSame('Main', $dto->name);
        $this->assertSame('uazapi', $dto->systemName);
        $this->assertSame(['key_a' => 'a', 'key_b' => 'b'], $dto->config);
        $this->assertSame([
            'name' => 'Main',
            'system_name' => 'uazapi',
            'config' => ['key_a' => 'a', 'key_b' => 'b'],
        ], $dto->toArray());
    }

    public function test_from_request_defaults_optional_fields_to_null(): void
    {
        $request = Mockery::mock(FormRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'name' => 'Only Name',
        ]);

        $dto = PlatformUazapiInstanceDTO::fromRequest($request);

        $this->assertSame('Only Name', $dto->name);
        $this->assertNull($dto->systemName);
        $this->assertSame([], $dto->config);
        $this->assertSame([
            'name' => 'Only Name',
            'system_name' => null,
            'config' => [],
        ], $dto->toArray());
    }
}
