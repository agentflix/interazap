import { IsIn, IsInt, IsOptional, IsString, Max, Min } from 'class-validator';

/**
 * Payload para envio de indicador transitorio de presenca via Uazapi.
 * Representa estado de digitacao ou gravacao, nao presenca global persistente.
 */
export class SendPresenceDto {
  @IsString()
  number!: string;

  @IsString()
  @IsIn(['composing', 'recording', 'paused'])
  presence!: string;

  @IsOptional()
  @IsInt()
  @Min(0)
  @Max(300000)
  delay?: number;
}
