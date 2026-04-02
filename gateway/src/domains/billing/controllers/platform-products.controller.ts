import {
  Body,
  Controller,
  Param,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { AsaasClient } from '../providers/asaas/asaas.client';
import {
  CreateAsaasProductDto,
  UpdateAsaasProductDto,
} from '../dto/asaas-product.dto';

/**
 * PlatformProductsController
 *
 * Gerencia produtos/planos no gateway de pagamentos Asaas.
 */
@Controller({ path: 'internal/platform/products', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class PlatformProductsController {
  constructor(private readonly asaasClient: AsaasClient) {}

  /**
   * Creates a new product in Asaas.
   *
   * @param payload - Product creation data
   * @returns Created product data
   */
  @Post()
  createProduct(@Body() payload: CreateAsaasProductDto) {
    return this.asaasClient.createProduct(payload);
  }

  /**
   * Updates an existing product in Asaas.
   *
   * @param productId - Product ID to update
   * @param payload - Product update data
   * @returns Updated product data
   */
  @Post(':productId')
  updateProduct(
    @Param('productId') productId: string,
    @Body() payload: UpdateAsaasProductDto,
  ) {
    return this.asaasClient.updateProduct(productId, payload);
  }
}
