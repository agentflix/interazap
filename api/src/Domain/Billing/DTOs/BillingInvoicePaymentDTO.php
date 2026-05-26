<?php

declare(strict_types=1);

namespace Domain\Billing\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO para pagamento de fatura via gateway.
 *
 * Carrega o método de pagamento (PIX ou CREDIT_CARD) e os dados opcionais
 * de cartão e titular exigidos quando o método é cartão de crédito.
 *
 * @readonly
 *
 * @phpstan-type Card array{holder_name:string,number:string,expiry_month:string,expiry_year:string,cvv:string}
 * @phpstan-type Holder array{name:string,email:string,cpf_cnpj:string,postal_code:string,address_number:string,phone:string}
 */
final readonly class BillingInvoicePaymentDTO
{
    /**
     * @param  Card|null  $card
     * @param  Holder|null  $holderInfo
     */
    public function __construct(
        public string $method,
        public ?array $card,
        public ?array $holderInfo,
    ) {}

    /** Cria o DTO a partir de um FormRequest validado. */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Cria o DTO a partir de um array de dados.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: (string) ($data['method'] ?? ''),
            card: isset($data['card']) && is_array($data['card']) ? $data['card'] : null,
            holderInfo: isset($data['holder_info']) && is_array($data['holder_info']) ? $data['holder_info'] : null,
        );
    }
}
