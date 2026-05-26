import { IsNotEmpty, IsString } from 'class-validator';

/**
 * DTO para validação de token de cartão via gateway.
 *
 * Recebe o token emitido pelo SDK Asaas no browser e o customer_id
 * para validar o cartão sem cobrar e retornar metadados (brand, last4).
 *
 * @remarks
 * PCI SAQ-A: PAN nunca trafega por este endpoint.
 */
export class ValidatePaymentMethodDto {
  @IsString()
  @IsNotEmpty()
  customer_id!: string;

  @IsString()
  @IsNotEmpty()
  token!: string;
}
