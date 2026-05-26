import { IsObject, IsOptional, IsString } from 'class-validator';

/**
 * DTO para disparar notificação de cobrança via WhatsApp.
 *
 * Utilizado pelo BillingCollectionController para enviar um lembrete de pagamento
 * ao cliente pelo provedor WhatsApp configurado para o tenant.
 */
export class BillingCollectionSendDto {
  @IsString()
  tenantId!: string;

  @IsString()
  phone!: string;

  @IsString()
  templateId!: string;

  @IsOptional()
  @IsObject()
  variables?: Record<string, string | number | boolean | null>;
}
