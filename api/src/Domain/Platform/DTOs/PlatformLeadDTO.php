<?php

declare(strict_types=1);

namespace Domain\Platform\DTOs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

/**
 * DTO para criação de lead da plataforma InteraZap.
 *
 * Carrega dados validados do `PlatformLeadStoreRequest` mais
 * metadados de requisição (IP / User-Agent / Referrer) capturados
 * pelo controller a partir do `Request` HTTP.
 *
 * @readonly
 */
final readonly class PlatformLeadDTO
{
    /**
     * @param  string  $name  Nome completo do lead.
     * @param  string  $phone  Telefone (somente dígitos, normalizado).
     * @param  string  $email  E-mail do lead.
     * @param  string|null  $company  Nome da empresa, opcional.
     * @param  string|null  $utmSource  UTM source.
     * @param  string|null  $utmMedium  UTM medium.
     * @param  string|null  $utmCampaign  UTM campaign.
     * @param  string|null  $referrer  Referrer HTTP.
     * @param  string|null  $userAgent  User-Agent do navegador.
     * @param  string|null  $ipAddress  IP de origem.
     * @param  bool  $lgpdConsent  Consentimento LGPD aceito.
     * @param  bool  $isHoneypot  Indica se a requisição caiu no honeypot (silent drop).
     */
    public function __construct(
        public string $name,
        public string $phone,
        public string $email,
        public ?string $company,
        public ?string $utmSource,
        public ?string $utmMedium,
        public ?string $utmCampaign,
        public ?string $referrer,
        public ?string $userAgent,
        public ?string $ipAddress,
        public bool $lgpdConsent,
        public bool $isHoneypot = false,
    ) {}

    /**
     * Cria DTO a partir de um FormRequest validado e do Request HTTP.
     *
     * @param  FormRequest  $request  Form request validado.
     * @param  Request|null  $httpRequest  Request HTTP (para captura de IP/UA/Referrer).
     */
    public static function fromRequest(FormRequest $request, ?Request $httpRequest = null): self
    {
        $data = $request->validated();
        $http = $httpRequest ?? $request;

        $rawPhone = (string) ($data['phone'] ?? '');
        $normalizedPhone = self::normalizePhone($rawPhone);

        $honeypot = trim((string) ($data['website'] ?? '')) !== '';

        return new self(
            name: trim((string) ($data['name'] ?? '')),
            phone: $normalizedPhone,
            email: strtolower(trim((string) ($data['email'] ?? ''))),
            company: isset($data['company']) && trim((string) $data['company']) !== ''
                ? trim((string) $data['company'])
                : null,
            utmSource: self::nullableString($data['utm_source'] ?? null),
            utmMedium: self::nullableString($data['utm_medium'] ?? null),
            utmCampaign: self::nullableString($data['utm_campaign'] ?? null),
            referrer: self::nullableString($data['referrer'] ?? $http->headers->get('referer')),
            userAgent: self::nullableString($data['user_agent'] ?? $http->userAgent()),
            ipAddress: self::nullableString($http->ip()),
            lgpdConsent: (bool) ($data['lgpd_consent'] ?? false),
            isHoneypot: $honeypot,
        );
    }

    /**
     * Representação em array para persistência.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'company' => $this->company,
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'referrer' => $this->referrer,
            'user_agent' => $this->userAgent !== null
                ? mb_substr($this->userAgent, 0, 255)
                : null,
            'ip_address' => $this->ipAddress,
            'lgpd_consent' => $this->lgpdConsent,
        ];
    }

    /**
     * Normaliza telefone para conter somente dígitos.
     */
    public static function normalizePhone(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }

    /**
     * Trata strings vazias/whitespace como null.
     */
    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }
}
