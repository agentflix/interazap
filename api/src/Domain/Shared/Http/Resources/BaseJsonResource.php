<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource base com helpers para serialização e formatação de datas.
 *
 * Define o contrato de data() para subclasses e fornece o helper
 * iso() para formatar objetos de data como strings ISO 8601.
 */
abstract class BaseJsonResource extends JsonResource
{
    /**
     * Converte o recurso em array delegando para o método data().
     *
     * @param  Request  $request  Requisição HTTP corrente.
     * @return array<string, mixed> Representação em array do recurso.
     */
    public function toArray(Request $request): array
    {
        return $this->data($request);
    }

    /**
     * Retorna os dados serializados do recurso.
     *
     * @param  Request  $request  Requisição HTTP corrente.
     * @return array<string, mixed> Dados do recurso para serialização JSON.
     */
    abstract protected function data(Request $request): array;

    /**
     * Formata uma data como string ISO 8601 (ou null se ausente).
     *
     * @param  DateTimeInterface|null  $value  Data a formatar.
     * @return string|null String ISO 8601 ou null.
     */
    protected function iso(?DateTimeInterface $value): ?string
    {
        return $value?->format(DATE_ATOM);
    }
}
