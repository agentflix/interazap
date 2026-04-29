<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO para registro de token de dispositivo.
 *
 * @readonly
 */
final readonly class RegisterDeviceTokenDTO
{
    public function __construct(
        public string $platform,
        public string $token,
        public ?string $deviceName,
    ) {}

    /**
     * Cria DTO a partir do request validado.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            platform: (string) $request->string('platform'),
            token: (string) $request->string('token'),
            deviceName: $request->filled('device_name') ? (string) $request->string('device_name') : null,
        );
    }
}
