<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Platform\Http\Requests\PlatformUazapiConnectRequest;
use Domain\Platform\Http\Requests\PlatformUazapiInstanceRequest;
use Domain\Platform\Http\Requests\PlatformUazapiSendFileRequest;
use Domain\Platform\Http\Requests\PlatformUazapiSendTextRequest;
use Tests\TestCase;

class PlatformUazapiRequestsTest extends TestCase
{
    public function test_instance_request_rules(): void
    {
        $request = new PlatformUazapiInstanceRequest;

        $this->assertSame([
            'name' => ['required', 'string', 'max:100'],
            'system_name' => ['nullable', 'string', 'max:100'],
            'config' => ['nullable', 'array'],
            'config.*' => ['nullable', 'string', 'max:255'],
        ], $request->rules());
    }

    public function test_connect_request_rules(): void
    {
        $request = new PlatformUazapiConnectRequest;

        $this->assertSame([
            'mode' => ['nullable', 'string', 'in:qr,pair'],
            'phone' => ['nullable', 'string', 'regex:/^\\d{10,15}$/'],
        ], $request->rules());
    }

    public function test_send_text_request_rules(): void
    {
        $request = new PlatformUazapiSendTextRequest;

        $this->assertSame([
            'number' => ['required', 'string', 'regex:/^[0-9@.\\w-]{6,}$/'],
            'text' => ['required', 'string'],
            'linkPreview' => ['nullable', 'boolean'],
            'linkPreviewTitle' => ['nullable', 'string'],
            'linkPreviewDescription' => ['nullable', 'string'],
            'linkPreviewImage' => ['nullable', 'string'],
            'linkPreviewLarge' => ['nullable', 'boolean'],
            'replyid' => ['nullable', 'string'],
            'mentions' => ['nullable', 'string'],
        ], $request->rules());
    }

    public function test_send_file_request_rules(): void
    {
        $request = new PlatformUazapiSendFileRequest;

        $this->assertSame([
            'number' => ['required', 'string', 'regex:/^[0-9@.\\w-]{6,}$/'],
            'url' => ['required', 'url'],
            'caption' => ['nullable', 'string'],
        ], $request->rules());
    }

    public function test_instance_request_authorize_returns_false_without_user(): void
    {
        $request = new PlatformUazapiInstanceRequest;
        $request->setUserResolver(fn (): null => null);

        $this->assertFalse($request->authorize());
    }

    public function test_send_text_authorize_returns_false_without_user(): void
    {
        $request = new PlatformUazapiSendTextRequest;
        $request->setUserResolver(fn (): null => null);

        $this->assertFalse($request->authorize());
    }

    public function test_send_file_authorize_returns_false_without_user(): void
    {
        $request = new PlatformUazapiSendFileRequest;
        $request->setUserResolver(fn (): null => null);

        $this->assertFalse($request->authorize());
    }
}
