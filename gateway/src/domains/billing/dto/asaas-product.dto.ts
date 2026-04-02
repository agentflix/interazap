import { IsNotEmpty, IsNumber, IsOptional, IsString } from 'class-validator';

/**
 * DTO for creating a product (pre-defined billing item) in Asaas.
 *
 * Products in Asaas represent one-time or recurring billing items
 * that can be referenced when creating payments or subscriptions.
 */
export class CreateAsaasProductDto {
  @IsString()
  @IsNotEmpty()
  name!: string;

  @IsString()
  @IsNotEmpty()
  description!: string;

  @IsNumber()
  value!: number;

  @IsString()
  @IsNotEmpty()
  externalReference!: string;
}

/**
 * DTO for updating an existing product in Asaas.
 *
 * All fields are required. The externalReference field is optional
 * to allow partial updates without changing the reference.
 */
export class UpdateAsaasProductDto {
  @IsString()
  @IsNotEmpty()
  name!: string;

  @IsString()
  @IsNotEmpty()
  description!: string;

  @IsNumber()
  value!: number;

  @IsOptional()
  @IsString()
  externalReference?: string;
}
