import { IsObject, IsOptional, IsString } from 'class-validator';

/**
 * DTO for triggering a WhatsApp billing collection notification.
 *
 * Used by the BillingCollectionController to send a payment reminder
 * to a customer via the configured WhatsApp provider.
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
