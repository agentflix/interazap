import { IsOptional, IsString, Matches } from 'class-validator';

/**
 * Payload for connecting an existing Uazapi instance.
 * Used when reconnecting a WhatsApp device with an existing token.
 */
export class ConnectInstanceDto {
  @IsOptional()
  @IsString()
  @Matches(/^\d{10,15}$/)
  phone?: string;
}
