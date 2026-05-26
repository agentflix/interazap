<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO para resultado de execução de uma tool do Autopilot.
 *
 * @readonly
 */
final readonly class ToolResultDTO
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
    ) {}

    /**
     * Cria um resultado de sucesso.
     *
     * @param  string  $message  Mensagem descritiva do resultado.
     * @param  array<string, mixed>  $data  Dados adicionais retornados pela tool.
     */
    public static function success(string $message, array $data = []): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
        );
    }

    /**
     * Cria um resultado de falha.
     *
     * @param  string  $message  Mensagem descritiva do erro.
     * @param  array<string, mixed>  $data  Dados adicionais do erro.
     */
    public static function failure(string $message, array $data = []): self
    {
        return new self(
            success: false,
            message: $message,
            data: $data,
        );
    }

    /**
     * Converte para array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
