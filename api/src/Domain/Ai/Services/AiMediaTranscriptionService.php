<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\DTOs\AiMediaTranscriptionDTO;
use Domain\Ai\Models\AiModelPricing;
use Domain\Ai\Models\AiUsageLog;
use Domain\Chat\Models\ChatMessage;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Serviço de transcrição e descrição de mídias.
 *
 * Orquestra transcrição de áudio via OpenAI Whisper, descrição de
 * imagem via OpenAI Vision (GPT-4o) e transcrição de vídeo via
 * combinação de FFmpeg + Whisper + Vision. Registra custos e
 * métricas de uso no AiUsageLog.
 */
final class AiMediaTranscriptionService
{
    private const AUDIO_MIME_TYPES = [
        'audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav',
        'audio/webm', 'audio/aac', 'audio/m4a', 'audio/mp4',
    ];

    private const IMAGE_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    ];

    private const VIDEO_MIME_TYPES = [
        'video/mp4', 'video/webm', 'video/quicktime', 'video/3gpp',
    ];

    /**
     * Transcrever áudio via OpenAI Whisper.
     *
     * @param  string  $filePath  Caminho absoluto do arquivo de áudio.
     * @param  string|null  $language  Hint de idioma (ex: 'pt').
     * @return AiMediaTranscriptionDTO Resultado da transcrição.
     */
    public function transcribeAudio(string $filePath, ?string $language = 'pt'): AiMediaTranscriptionDTO
    {
        $startTime = microtime(true);
        $model = (string) config('ai.transcription.whisper_model', 'whisper-1');

        $response = Http::withToken((string) config('services.openai.api_key'))
            ->timeout((int) config('ai.transcription.timeout', 120))
            ->attach('file', (string) file_get_contents($filePath), basename($filePath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => $model,
                'language' => $language,
                'response_format' => 'verbose_json',
            ]);

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            logger()->error('[AiMediaTranscription] Whisper API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return new AiMediaTranscriptionDTO(
                messageId: '',
                tenantId: '',
                mediaType: 'audio',
                filePath: $filePath,
                provider: 'openai',
                modelName: $model,
                latencyMs: $latencyMs,
            );
        }

        $data = $response->json();
        $text = (string) ($data['text'] ?? '');
        $duration = (float) ($data['duration'] ?? 0);

        // Whisper: custo baseado em duração (~$0.006/min)
        $durationMinutes = $duration / 60;
        $cost = round($durationMinutes * 0.006, 6);

        // Estimar tokens equivalentes para tracking
        $estimatedTokens = (int) (strlen($text) / 4);

        return new AiMediaTranscriptionDTO(
            messageId: '',
            tenantId: '',
            mediaType: 'audio',
            filePath: $filePath,
            transcription: $text,
            provider: 'openai',
            modelName: $model,
            inputTokens: $estimatedTokens,
            outputTokens: 0,
            cost: $cost,
            latencyMs: $latencyMs,
            durationSeconds: (int) $duration,
            fileSizeKb: (int) (filesize($filePath) / 1024),
        );
    }

    /**
     * Descrever imagem via OpenAI Vision (GPT-4o).
     *
     * @param  string  $filePath  Caminho absoluto ou URL da imagem.
     * @param  string|null  $customPrompt  Prompt contextual opcional.
     * @return AiMediaTranscriptionDTO Resultado da descrição.
     */
    public function describeImage(string $filePath, ?string $customPrompt = null): AiMediaTranscriptionDTO
    {
        $startTime = microtime(true);
        $model = (string) config('ai.transcription.vision_model', 'gpt-4o');
        $detail = (string) config('ai.transcription.vision_detail', 'low');
        $maxTokens = (int) config('ai.transcription.vision_max_tokens', 300);

        $prompt = $customPrompt ?? 'Descreva esta imagem de forma objetiva e concisa para um atendente virtual. Se contiver texto, transcreva-o. Se for um comprovante de pagamento, extraia: valor, data, banco. Máximo 200 palavras.';

        // Converter arquivo para base64
        $imageData = base64_encode((string) file_get_contents($filePath));
        $mimeType = mime_content_type($filePath) ?: 'image/jpeg';
        $imageUrl = "data:{$mimeType};base64,{$imageData}";

        $response = Http::withToken((string) config('services.openai.api_key'))
            ->timeout((int) config('ai.transcription.timeout', 120))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $imageUrl,
                                    'detail' => $detail,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            logger()->error('[AiMediaTranscription] Vision API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return new AiMediaTranscriptionDTO(
                messageId: '',
                tenantId: '',
                mediaType: 'image',
                filePath: $filePath,
                provider: 'openai',
                modelName: $model,
                latencyMs: $latencyMs,
            );
        }

        $data = $response->json();
        $description = (string) ($data['choices'][0]['message']['content'] ?? '');
        $usage = $data['usage'] ?? [];
        $inputTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $outputTokens = (int) ($usage['completion_tokens'] ?? 0);

        // Calcular custo usando pricing do modelo
        $pricing = AiModelPricing::findByModel($model);
        $cost = $pricing
            ? $pricing->calculateTotalCost($inputTokens, $outputTokens)
            : round(0.01, 6);

        return new AiMediaTranscriptionDTO(
            messageId: '',
            tenantId: '',
            mediaType: 'image',
            filePath: $filePath,
            transcription: $description,
            provider: 'openai',
            modelName: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $cost,
            latencyMs: $latencyMs,
            fileSizeKb: (int) (filesize($filePath) / 1024),
        );
    }

    /**
     * Transcrever vídeo via FFmpeg (extração) + Whisper + Vision.
     *
     * Extrai áudio e frames representativos, depois combina
     * transcrição de áudio com descrição visual dos frames.
     *
     * @param  string  $filePath  Caminho absoluto do vídeo.
     * @param  int  $maxDurationSeconds  Duração máxima a processar.
     * @return AiMediaTranscriptionDTO Resultado combinado.
     */
    public function transcribeVideo(string $filePath, int $maxDurationSeconds = 30): AiMediaTranscriptionDTO
    {
        $startTime = microtime(true);
        $parts = [];
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $totalCost = 0.0;

        // Passo 1: Extrair áudio do vídeo
        $audioPath = $this->extractAudioFromVideo($filePath);
        if ($audioPath !== null) {
            $audioResult = $this->transcribeAudio($audioPath);
            if ($audioResult->transcription !== null && $audioResult->transcription !== '') {
                $parts[] = "Transcrição: {$audioResult->transcription}";
                $totalInputTokens += $audioResult->inputTokens;
                $totalCost += $audioResult->cost;
            }
            // Limpar arquivo temporário
            @unlink($audioPath);
        }

        // Passo 2: Extrair frames representativos
        $frameInterval = (int) config('ai.transcription.video_frame_interval_seconds', 5);
        $framePaths = $this->extractFramesFromVideo($filePath, $frameInterval, $maxDurationSeconds);
        $sceneDescriptions = [];

        foreach ($framePaths as $index => $framePath) {
            $frameResult = $this->describeImage($framePath, 'Descreva brevemente o que se vê neste frame de um vídeo. Máximo 50 palavras.');
            if ($frameResult->transcription !== null && $frameResult->transcription !== '') {
                $sceneDescriptions[] = sprintf('Frame %d: %s', $index + 1, $frameResult->transcription);
                $totalInputTokens += $frameResult->inputTokens;
                $totalOutputTokens += $frameResult->outputTokens;
                $totalCost += $frameResult->cost;
            }
            @unlink($framePath);
        }

        if ($sceneDescriptions !== []) {
            $parts[] = 'Cenas: '.implode('; ', $sceneDescriptions);
        }

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
        $transcription = $parts !== [] ? '[Vídeo] '.implode('. ', $parts) : null;

        return new AiMediaTranscriptionDTO(
            messageId: '',
            tenantId: '',
            mediaType: 'video',
            filePath: $filePath,
            transcription: $transcription,
            provider: 'openai',
            modelName: 'whisper-1+gpt-4o',
            inputTokens: $totalInputTokens,
            outputTokens: $totalOutputTokens,
            cost: $totalCost,
            latencyMs: $latencyMs,
            durationSeconds: $maxDurationSeconds,
            fileSizeKb: (int) (filesize($filePath) / 1024),
        );
    }

    /**
     * Verifica se a transcrição deve ser realizada para este tipo de mídia.
     *
     * Checks: tenant tem transcrição habilitada para o tipo, e mídia
     * está dentro dos limites configurados.
     *
     * @param  PlatformTenant  $tenant  Tenant dono da mensagem.
     * @param  string  $mediaType  Tipo de mídia: audio, image, video.
     * @param  array<string, mixed>  $metadata  Metadados da mídia (duração, etc).
     * @return bool True se deve transcrever.
     */
    public function shouldTranscribe(PlatformTenant $tenant, string $mediaType, array $metadata = []): bool
    {
        return match ($mediaType) {
            'audio' => $this->shouldTranscribeAudio($tenant, $metadata),
            'image' => $this->shouldTranscribeImage($tenant, $metadata),
            'video' => $this->shouldTranscribeVideo($tenant, $metadata),
            default => false,
        };
    }

    /**
     * Estima o custo de transcrição para um tipo de mídia.
     *
     * @param  string  $mediaType  Tipo de mídia.
     * @param  int|null  $durationSeconds  Duração para áudio/vídeo.
     * @param  int  $imageCount  Quantidade de imagens.
     * @return float Custo estimado em USD.
     */
    public function estimateCost(string $mediaType, ?int $durationSeconds = null, int $imageCount = 1): float
    {
        return match ($mediaType) {
            'audio' => round(($durationSeconds ?? 0) / 60 * 0.006, 6),
            'image' => round($imageCount * 0.01, 6),
            'video' => round(($durationSeconds ?? 30) / 30 * 0.03, 6),
            default => 0.0,
        };
    }

    /**
     * Registra o uso de transcrição no AiUsageLog.
     *
     * @param  AiMediaTranscriptionDTO  $dto  Resultado da transcrição.
     * @param  ChatMessage  $message  Mensagem associada.
     */
    public function logUsage(AiMediaTranscriptionDTO $dto, ChatMessage $message): void
    {
        $featureName = "media_transcription_{$dto->mediaType}";
        $pricing = AiModelPricing::findByModel($dto->modelName ?? '');

        $inputCost = $pricing
            ? $pricing->calculateInputCost($dto->inputTokens)
            : $dto->cost;
        $outputCost = $pricing
            ? $pricing->calculateOutputCost($dto->outputTokens)
            : 0.0;

        AiUsageLog::create([
            'tenant_id' => $dto->tenantId,
            'ai_model_pricing_id' => $pricing?->id,
            'model_name' => $dto->modelName ?? 'unknown',
            'provider' => $dto->provider ?? 'openai',
            'input_tokens' => $dto->inputTokens,
            'output_tokens' => $dto->outputTokens,
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'feature' => $featureName,
            'latency_ms' => $dto->latencyMs,
            'usable_type' => ChatMessage::class,
            'usable_id' => $message->id,
            'metadata' => [
                'media_type' => $dto->mediaType,
                'duration_sec' => $dto->durationSeconds,
                'file_size_kb' => $dto->fileSizeKb,
            ],
        ]);
    }

    /**
     * Determina o tipo de mídia a partir do MIME type.
     *
     * @param  string|null  $mimeType  MIME type do arquivo.
     * @return string|null Tipo de mídia: audio, image, video ou null.
     */
    public function resolveMediaType(?string $mimeType): ?string
    {
        if ($mimeType === null) {
            return null;
        }

        if (in_array($mimeType, self::AUDIO_MIME_TYPES, true) || str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if (in_array($mimeType, self::IMAGE_MIME_TYPES, true) || str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (in_array($mimeType, self::VIDEO_MIME_TYPES, true) || str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return null;
    }

    /**
     * Verifica se áudio deve ser transcrito.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function shouldTranscribeAudio(PlatformTenant $tenant, array $metadata): bool
    {
        if (! $tenant->media_transcription_audio_enabled) {
            return false;
        }

        $maxMinutes = $tenant->media_transcription_audio_max_minutes;
        $durationSeconds = (int) ($metadata['duration_seconds'] ?? 0);

        if ($durationSeconds > 0 && $durationSeconds > $maxMinutes * 60) {
            logger()->info('[AiMediaTranscription] Áudio excede limite de duração', [
                'tenant_id' => $tenant->id,
                'duration_seconds' => $durationSeconds,
                'max_minutes' => $maxMinutes,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Verifica se imagem deve ser descrita.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function shouldTranscribeImage(PlatformTenant $tenant, array $metadata): bool
    {
        if (! $tenant->media_transcription_image_enabled) {
            return false;
        }

        $maxPerMessage = $tenant->media_transcription_image_max_per_message;
        $imageIndex = (int) ($metadata['image_index'] ?? 0);

        if ($imageIndex >= $maxPerMessage) {
            logger()->info('[AiMediaTranscription] Imagem excede limite por mensagem', [
                'tenant_id' => $tenant->id,
                'image_index' => $imageIndex,
                'max_per_message' => $maxPerMessage,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Verifica se vídeo deve ser transcrito.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function shouldTranscribeVideo(PlatformTenant $tenant, array $metadata): bool
    {
        if (! $tenant->media_transcription_video_enabled) {
            return false;
        }

        $maxSeconds = $tenant->media_transcription_video_max_seconds;
        $durationSeconds = (int) ($metadata['duration_seconds'] ?? 0);

        if ($durationSeconds > 0 && $durationSeconds > $maxSeconds) {
            logger()->info('[AiMediaTranscription] Vídeo excede limite de duração', [
                'tenant_id' => $tenant->id,
                'duration_seconds' => $durationSeconds,
                'max_seconds' => $maxSeconds,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Extrair áudio de vídeo via FFmpeg.
     *
     * @param  string  $videoPath  Caminho absoluto do vídeo.
     * @return string|null Caminho do arquivo de áudio extraído ou null se falhar.
     */
    private function extractAudioFromVideo(string $videoPath): ?string
    {
        $audioPath = sys_get_temp_dir().'/'.uniqid('audio_', true).'.mp3';

        $result = Process::timeout(60)->run([
            'ffmpeg', '-i', $videoPath, '-vn', '-acodec', 'libmp3lame',
            '-q:a', '4', '-y', $audioPath,
        ]);

        if (! $result->successful() || ! file_exists($audioPath)) {
            logger()->warning('[AiMediaTranscription] FFmpeg audio extraction failed', [
                'video_path' => $videoPath,
                'error' => $result->errorOutput(),
            ]);

            return null;
        }

        return $audioPath;
    }

    /**
     * Extrair frames representativos de vídeo via FFmpeg.
     *
     * @param  string  $videoPath  Caminho absoluto do vídeo.
     * @param  int  $intervalSeconds  Intervalo entre frames.
     * @param  int  $maxDurationSeconds  Duração máxima a extrair.
     * @return list<string> Lista de caminhos dos frames extraídos.
     */
    private function extractFramesFromVideo(string $videoPath, int $intervalSeconds, int $maxDurationSeconds): array
    {
        $tempDir = sys_get_temp_dir().'/frames_'.uniqid();
        @mkdir($tempDir, 0755, true);

        $result = Process::timeout(60)->run([
            'ffmpeg', '-i', $videoPath,
            '-t', (string) $maxDurationSeconds,
            '-vf', "fps=1/{$intervalSeconds}",
            '-q:v', '2',
            "{$tempDir}/frame_%04d.jpg",
        ]);

        if (! $result->successful()) {
            logger()->warning('[AiMediaTranscription] FFmpeg frame extraction failed', [
                'video_path' => $videoPath,
                'error' => $result->errorOutput(),
            ]);

            return [];
        }

        $frames = glob("{$tempDir}/frame_*.jpg") ?: [];
        sort($frames);

        // Limitar a 6 frames no máximo
        return array_slice($frames, 0, 6);
    }
}
