import { Type } from 'class-transformer';
import {
  IsNotEmpty,
  IsNumber,
  IsOptional,
  IsString,
  ValidateNested,
} from 'class-validator';

/**
 * DTO com dados do cartão de crédito para pagamento Asaas.
 *
 * Estes campos são coletados no lado do cliente e encaminhados ao Asaas.
 * Nunca são registrados em log nem armazenados no servidor.
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
 * DTO com dados de identidade e endereço do titular do cartão exigidos pelo Asaas.
 *
 * Obrigatório para pagamentos autenticados com 3D Secure. Os dados devem
 * corresponder às informações cadastradas na operadora do cartão.
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
 * DTO para criação de cobrança no Asaas.
 *
 * Suporta cobranças simples (billingType + valor + vencimento) e
 * cobranças completas via cartão de crédito com dados do titular embutidos.
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
