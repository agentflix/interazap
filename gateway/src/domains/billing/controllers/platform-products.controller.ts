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
@Controller({ path: 'internal/platform/products' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class PlatformProductsController {
  constructor(private readonly asaasClient: AsaasClient) {}

  /**
   * Cria um novo produto no Asaas.
   *
   * @param payload - Dados do produto (nome, descrição, valor, referência externa)
   * @returns Dados do produto criado com ID gerado pelo Asaas
   */
  @Post()
  createProduct(@Body() payload: CreateAsaasProductDto) {
    return this.asaasClient.createProduct(payload);
  }

  /**
   * Atualiza um produto existente no Asaas.
   *
   * @param productId - Identificador do produto a ser atualizado
   * @param payload - Dados de atualização do produto
   * @returns Dados do produto atualizado
   */
  @Post(':productId')
  updateProduct(
    @Param('productId') productId: string,
    @Body() payload: UpdateAsaasProductDto,
  ) {
    return this.asaasClient.updateProduct(productId, payload);
  }
}
