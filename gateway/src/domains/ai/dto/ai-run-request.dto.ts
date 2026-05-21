import { IsOptional, IsString, IsUUID } from 'class-validator';

/**
 * DTO mínimo para payloads `ai.run.request` recebidos no gateway.
 *
 * O campo `correlation_id` é opcional por compatibilidade retroativa e,
 * quando ausente, o consumer usa `GatewayMessage.correlationId`.
 */
export class AiRunRequestDto {
  @IsOptional()
  @IsUUID()
  correlation_id?: string;

  @IsOptional()
  @IsString()
  run_id?: string;
}
