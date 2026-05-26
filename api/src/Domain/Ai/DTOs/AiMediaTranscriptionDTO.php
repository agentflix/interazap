<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO para resultados de transcrição e descrição de mídias.
 *
 * Encapsula o retorno de transcrição de áudio (Whisper), descrição
 * de imagem (Vision) e transcrição de vídeo (FFmpeg + Whisper + Vision),
 * incluindo métricas de custo, tokens e latência.
 *
 * @readonly
 */
final readonly class AiMediaTranscriptionDTO
{
    /**
     * @param  string  $messageId  UUID da mensagem transcrita.
     * @param  string  $tenantId  UUID do tenant proprietário.
     * @param  string  $mediaType  Tipo de mídia: audio, image, video.
     * @param  string  $filePath  Caminho do arquivo no Storage.
     * @param  string|null  $mimeType  MIME type do arquivo.
     * @param  string|null  $transcription  Texto da transcrição/descrição.
     * @param  string|null  $provider  Provider utilizado (openai).
     * @param  string|null  $modelName  Nome do modelo (whisper-1, gpt-4o).
     * @param  int  $inputTokens  Tokens de entrada consumidos.
     * @param  int  $outputTokens  Tokens de saída consumidos.
     * @param  float  $cost  Custo total da transcrição em USD.
     * @param  int  $latencyMs  Latência em milissegundos.
     * @param  int|null  $durationSeconds  Duração da mídia em segundos (áudio/vídeo).
     * @param  int|null  $fileSizeKb  Tamanho do arquivo em KB.
     */
    public function __construct(
        public string $messageId,
        public string $tenantId,
        public string $mediaType,
        public string $filePath,
        public ?string $mimeType = null,
        public ?string $transcription = null,
        public ?string $provider = 'openai',
        public ?string $modelName = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public float $cost = 0.0,
        public int $latencyMs = 0,
        public ?int $durationSeconds = null,
        public ?int $fileSizeKb = null,
    ) {}

    /**
     * Cria DTO a partir de array de dados.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messageId: (string) ($data['message_id'] ?? ''),
            tenantId: (string) ($data['tenant_id'] ?? ''),
            mediaType: (string) ($data['media_type'] ?? ''),
            filePath: (string) ($data['file_path'] ?? ''),
            mimeType: isset($data['mime_type']) ? (string) $data['mime_type'] : null,
            transcription: isset($data['transcription']) ? (string) $data['transcription'] : null,
            provider: (string) ($data['provider'] ?? 'openai'),
            modelName: isset($data['model_name']) ? (string) $data['model_name'] : null,
            inputTokens: (int) ($data['input_tokens'] ?? 0),
            outputTokens: (int) ($data['output_tokens'] ?? 0),
            cost: (float) ($data['cost'] ?? 0.0),
            latencyMs: (int) ($data['latency_ms'] ?? 0),
            durationSeconds: isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            fileSizeKb: isset($data['file_size_kb']) ? (int) $data['file_size_kb'] : null,
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
            'message_id' => $this->messageId,
            'tenant_id' => $this->tenantId,
            'media_type' => $this->mediaType,
            'file_path' => $this->filePath,
            'mime_type' => $this->mimeType,
            'transcription' => $this->transcription,
            'provider' => $this->provider,
            'model_name' => $this->modelName,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cost' => $this->cost,
            'latency_ms' => $this->latencyMs,
            'duration_seconds' => $this->durationSeconds,
            'file_size_kb' => $this->fileSizeKb,
        ];
    }
}
