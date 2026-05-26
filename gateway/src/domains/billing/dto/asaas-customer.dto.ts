import { IsNotEmpty, IsString } from 'class-validator';

/**
 * DTO para criação de cliente no Asaas (gateway de pagamentos brasileiro).
 *
 * Utilizado ao registrar um novo tenant ou usuário como cliente no Asaas
 * para que cobranças, faturas e assinaturas possam ser gerenciadas.
 */
export class CreateAsaasCustomerDto {
  @IsString()
  @IsNotEmpty()
  name!: string;

  @IsString()
  @IsNotEmpty()
  cpfCnpj!: string;

  @IsString()
  @IsNotEmpty()
  email!: string;

  @IsString()
  @IsNotEmpty()
  externalReference!: string;
}
