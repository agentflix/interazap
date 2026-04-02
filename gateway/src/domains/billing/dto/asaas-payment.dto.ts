import { Type } from 'class-transformer';
import {
  IsNotEmpty,
  IsNumber,
  IsOptional,
  IsString,
  ValidateNested,
} from 'class-validator';

/**
 * DTO for credit card data submitted with an Asaas credit-card payment.
 *
 * These fields are collected client-side and forwarded to Asaas.
 * Never logged or stored server-side.
 */
export class CreditCardDto {
  @IsString()
  @IsNotEmpty()
  holderName!: string;

  @IsString()
  @IsNotEmpty()
  number!: string;

  @IsString()
  @IsNotEmpty()
  expiryMonth!: string;

  @IsString()
  @IsNotEmpty()
  expiryYear!: string;

  @IsString()
  @IsNotEmpty()
  ccv!: string;
}

/**
 * DTO for credit card holder identity and address information required by Asaas.
 *
 * Required for 3D Secure / authenticated payments. Must match the information
 * registered with the card issuer.
 */
export class CreditCardHolderInfoDto {
  @IsString()
  @IsNotEmpty()
  name!: string;

  @IsString()
  @IsNotEmpty()
  email!: string;

  @IsString()
  @IsNotEmpty()
  cpfCnpj!: string;

  @IsString()
  @IsNotEmpty()
  postalCode!: string;

  @IsString()
  @IsNotEmpty()
  addressNumber!: string;

  @IsString()
  @IsNotEmpty()
  phone!: string;
}

/**
 * DTO for creating a payment in Asaas.
 *
 * Supports both simple payments (billingType + value + dueDate) and
 * full credit-card payments with embedded card and holder info.
 */
export class CreateAsaasPaymentDto {
  @IsString()
  @IsNotEmpty()
  customer!: string;

  @IsString()
  @IsNotEmpty()
  billingType!: string;

  @IsNumber()
  value!: number;

  @IsString()
  @IsNotEmpty()
  dueDate!: string;

  @IsString()
  @IsNotEmpty()
  description!: string;

  @IsString()
  @IsNotEmpty()
  externalReference!: string;

  @IsOptional()
  @ValidateNested()
  @Type(() => CreditCardDto)
  creditCard?: CreditCardDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => CreditCardHolderInfoDto)
  creditCardHolderInfo?: CreditCardHolderInfoDto;
}
