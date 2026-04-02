import { IsNotEmpty, IsString } from 'class-validator';

/**
 * DTO for creating a customer in Asaas (Brazilian payment gateway).
 *
 * Used when registering a new tenant or user as a customer in Asaas
 * so that payments, invoices, and subscriptions can be managed.
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
