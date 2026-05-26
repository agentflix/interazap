import { IsEnum, IsOptional, IsString, Matches } from 'class-validator';

/**
 * Payload para conectar uma instancia Uazapi existente.
 * Utilizado ao reconectar um dispositivo WhatsApp com um token ja existente.
 */
export class ConnectInstanceDto {
  @IsOptional()
  @IsString()
  @Matches(/^\d{10,15}$/)
  phone?: string;

  @IsOptional()
  @IsEnum(['qr', 'pair'])
  mode?: 'qr' | 'pair';
}
