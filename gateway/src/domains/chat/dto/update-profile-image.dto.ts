import { IsNotEmpty, IsString } from 'class-validator';

/**
 * Payload para atualizar a foto de perfil de uma instancia Uazapi.
 * O campo image aceita URL ou string codificada em base64.
 */
export class UpdateProfileImageDto {
  @IsString()
  @IsNotEmpty()
  image!: string;
}
